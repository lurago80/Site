<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\Cupom;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Jobs\EnviarConfirmacaoAgendamentoJob;
use App\Models\ReservaTemporaria;
use App\Models\Venda;
use App\Services\Agendamento\ReservaVagaService;
use App\Services\Pagamento\PagamentoService;
use App\Services\Vendas\FreteService;
use App\Services\Vendas\KitService;
use App\Services\Vendas\LojaPublicaPresenter;
use App\Services\Vendas\QuantidadeMinimaVendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fecha a compra da loja pública: cadastra/atualiza o cliente, gera a
 * venda e, se houver reserva de vaga, confirma-a (ReservaVagaService).
 *
 * Pagamento (Pix ou cartão) passa pelo PagamentoService de verdade
 * (Escopo v2, decisão de 2026-07-18) - a venda nasce "pendente" e só
 * vira "pago" quando o gateway confirma (instantaneamente, se a
 * empresa ainda não configurou um gateway real e está usando o
 * SimuladoPagamentoGateway). O token do cartão é gerado no front-end
 * (SDK do gateway, ex. Mercado Pago.js/Bricks - este repositório expõe
 * só a API, o front-end de loja pública é um projeto à parte); sem
 * token, o checkout usa o SimuladoPagamentoGateway (aprova na hora),
 * útil para lojas que ainda não integraram a cobrança online no front.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly ReservaVagaService $reservaVagaService,
        private readonly PagamentoService $pagamentoService,
        private readonly QuantidadeMinimaVendaService $quantidadeMinimaVendaService,
        private readonly FreteService $freteService,
        private readonly KitService $kitService,
    ) {}

    public function store(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'cliente.nome' => ['required', 'string', 'max:255'],
            'cliente.cpf_cnpj' => ['required', 'string', 'max:18'],
            'cliente.rg' => ['nullable', 'string', 'max:20'],
            'cliente.inscricao_estadual' => ['nullable', 'string', 'max:20'],
            'cliente.email' => ['required', 'email'],
            'cliente.telefone' => ['required', 'string', 'max:20'],
            'cliente.cep' => ['nullable', 'string', 'max:9'],
            'cliente.logradouro' => ['nullable', 'string', 'max:255'],
            'cliente.numero' => ['nullable', 'string', 'max:20'],
            'cliente.bairro' => ['nullable', 'string', 'max:255'],
            'cliente.municipio' => ['nullable', 'string', 'max:255'],
            'cliente.uf' => ['nullable', 'string', 'max:2'],
            'cliente.codigo_ibge_municipio' => ['nullable', 'string', 'max:7'],
            'cliente.consentimento_lgpd' => ['required', 'accepted'],
            'reserva_id' => ['nullable', 'integer'],
            'itens' => ['nullable', 'array'],
            'itens.*.produto_id' => ['required_with:itens', 'integer'],
            'itens.*.variacao_id' => ['nullable', 'integer'],
            'itens.*.quantidade' => ['required_with:itens', 'integer', 'min:1'],
            'itens.*.escolhas' => ['nullable', 'array'],
            'itens.*.escolhas.*.variacao_id' => ['required_without:itens.*.escolhas.*.produto_id', 'nullable', 'integer'],
            'itens.*.escolhas.*.produto_id' => ['nullable', 'integer'],
            'itens.*.escolhas.*.quantidade' => ['required', 'integer', 'min:1'],
            'forma_pagamento' => ['required', 'string', 'in:pix,cartao'],
            'cartao_token' => ['nullable', 'string'],
            'cartao_parcelas' => ['nullable', 'integer', 'min:1'],
            'cartao_metodo' => ['nullable', 'string', 'in:cartao_credito,cartao_debito'],
            'cupom_codigo' => ['nullable', 'string', 'max:40'],
            'tipo_entrega' => ['nullable', 'string', 'in:entrega,retirada'],
        ]);

        abort_if(
            empty($dados['reserva_id']) && empty($dados['itens']),
            422,
            'Informe uma reserva de vaga ou ao menos um item de produto.'
        );

        $documento = preg_replace('/\D/', '', $dados['cliente']['cpf_cnpj']);
        abort_if(! in_array(strlen($documento), [11, 14], true), 422, 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');

        $pessoaJuridica = strlen($documento) === 14;

        if ($pessoaJuridica) {
            abort_if(empty($dados['cliente']['inscricao_estadual'] ?? null), 422, 'Informe a Inscrição Estadual para pessoa jurídica.');
        } else {
            abort_if(empty($dados['cliente']['rg'] ?? null), 422, 'Informe o RG.');
        }

        $empresaAtual = $request->attributes->get('empresaAtual');

        // Entrega x retirada só se aplica a produto físico - visita agendada
        // sozinha não tem envio. Na retirada o endereço do cliente não é
        // exigido; na entrega é obrigatório (é para onde o pedido vai).
        $tipoEntrega = null;

        if (! empty($dados['itens'])) {
            $tipoEntrega = $dados['tipo_entrega'] ?? 'entrega';

            if ($tipoEntrega === 'retirada') {
                abort_unless($empresaAtual->permite_retirada, 422, 'Esta loja não oferece retirada no local.');
            } else {
                $camposEndereco = ['cep', 'logradouro', 'numero', 'bairro', 'municipio', 'uf', 'codigo_ibge_municipio'];
                foreach ($camposEndereco as $campo) {
                    abort_if(empty($dados['cliente'][$campo] ?? null), 422, 'Informe o endereço completo para envio do produto.');
                }
            }
        }

        $this->quantidadeMinimaVendaService->validar($dados['itens'] ?? []);

        $venda = DB::transaction(function () use ($dados, $empresaAtual, $tipoEntrega) {
            $cliente = $this->localizarOuCriarCliente($empresaAtual->id, $dados['cliente']);

            $venda = Venda::create([
                'empresa_id' => $empresaAtual->id,
                'cliente_id' => $cliente->id,
                'canal' => 'site',
                'tipo_doc' => 'nao_fiscal',
                'status_pagamento' => 'pendente',
                'valor_total' => 0,
                'data_venda' => now(),
            ]);

            $valorVisitas = 0;
            $quantidadeTickets = 0;

            if (! empty($dados['reserva_id'])) {
                [$valorVisitas, $quantidadeTickets] = $this->confirmarReservaEGerarItem($venda, $dados['reserva_id']);
            }

            $valorProdutos = 0;

            foreach ($dados['itens'] ?? [] as $item) {
                $valorProdutos += $this->gerarItemProduto($venda, $item['produto_id'], $item['quantidade'], $item['variacao_id'] ?? null, $item['escolhas'] ?? []);
            }

            $valorDesconto = 0;
            $cupomId = null;

            // Cupom só desconta a parte da visita agendada, nunca produtos
            // (regra de negócio explícita do cliente) - ver
            // Cupom::calcularDescontoVisita para o teto por quantidade de tickets.
            if (! empty($dados['cupom_codigo'])) {
                abort_if($quantidadeTickets === 0, 422, 'O cupom só pode ser aplicado quando há uma visita agendada no carrinho.');
                [$valorDesconto, $cupomId] = $this->aplicarCupom($dados['cupom_codigo'], $valorVisitas, $quantidadeTickets, $cliente->id);
            }

            // Frete calculado aqui no servidor (nunca confia no valor que o
            // navegador mostrou) a partir da UF informada no cadastro.
            $valorFrete = 0;
            $enderecoEntrega = null;

            if ($tipoEntrega === 'entrega') {
                $cotacao = $this->freteService->cotarEntrega($empresaAtual, $dados['cliente']['uf'], (float) $valorProdutos);
                abort_unless($cotacao['disponivel'], 422, $cotacao['mensagem']);
                $valorFrete = $cotacao['valor'];
                $enderecoEntrega = collect($dados['cliente'])->only([
                    'cep', 'logradouro', 'numero', 'bairro', 'municipio', 'uf', 'codigo_ibge_municipio',
                ])->all();
            }

            $venda->update([
                'valor_total' => $valorProdutos + $valorVisitas - $valorDesconto + $valorFrete,
                'valor_desconto' => $valorDesconto ?: null,
                'cupom_id' => $cupomId,
                'tipo_entrega' => $tipoEntrega,
                'valor_frete' => $valorFrete,
                'endereco_entrega' => $enderecoEntrega,
                'status_envio' => $tipoEntrega !== null ? 'a_separar' : null,
            ]);

            return $venda;
        });

        $cobranca = null;

        if ($dados['forma_pagamento'] === 'pix') {
            $cobranca = $this->pagamentoService->criarCobrancaPix($venda);
        } elseif (! empty($dados['cartao_token'])) {
            $cobranca = $this->pagamentoService->criarCobrancaCartao(
                $venda,
                $dados['cartao_token'],
                $dados['cartao_parcelas'] ?? 1,
                $dados['cartao_metodo'] ?? 'cartao_credito',
            );
        } else {
            // Sem token de cartão (front-end ainda não integrou o SDK do
            // gateway) - aprova na hora, como o checkout já fazia antes
            // deste módulo existir.
            $venda->update(['status_pagamento' => 'pago']);
        }

        if ($venda->fresh()->status_pagamento === 'pago') {
            EnviarConfirmacaoAgendamentoJob::dispatch($venda->id);
        }

        $vendaFinal = $venda->fresh()->load('itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao');

        // Lista fechada de campos: quem faz o checkout não recebe de volta o
        // cadastro do cliente (que pode ter vindo de outra compra pelo mesmo
        // CPF) nem os dados internos do produto.
        return response()->json([
            'id' => $vendaFinal->id,
            'status_pagamento' => $vendaFinal->status_pagamento,
            'valor_total' => $vendaFinal->valor_total,
            'valor_desconto' => $vendaFinal->valor_desconto,
            'valor_frete' => $vendaFinal->valor_frete,
            'tipo_entrega' => $vendaFinal->tipo_entrega,
            'data_venda' => $vendaFinal->data_venda,
            'itens' => LojaPublicaPresenter::itens($vendaFinal),
            'cobranca' => $cobranca ? [
                'status' => $cobranca->status,
                'qr_code' => $cobranca->qr_code,
                'qr_code_base64' => $cobranca->qr_code_base64,
                'expira_em' => $cobranca->expira_em,
            ] : null,
        ], 201);
    }

    /**
     * Revalida o cupom aqui dentro (não confia na prévia de
     * CatalogoController::validarCupom) e já reserva o uso com
     * lockForUpdate() - evita que duas compras simultâneas usando o
     * último uso disponível de um cupom limitado passem as duas.
     *
     * @return array{0: float, 1: int} [valor_desconto, cupom_id]
     */
    private function aplicarCupom(string $codigo, float $subtotalVisitas, int $quantidadeTickets, int $clienteId): array
    {
        $cupom = Cupom::whereRaw('lower(codigo) = ?', [mb_strtolower($codigo)])->lockForUpdate()->first();

        abort_if($cupom === null, 422, 'Cupom não encontrado.');
        abort_if($cupom->motivoInvalido() !== null, 422, $cupom->motivoInvalido());

        $cupom->increment('usos_realizados');

        // Registra quem usou e quando - o painel de cupons importados usa isso
        // pra mostrar o status de cada código sem precisar cruzar com vendas.
        // Num cupom de usos múltiplos, guarda sempre o uso mais recente.
        $cupom->update(['usado_em' => now(), 'usado_por_cliente_id' => $clienteId]);

        return [$cupom->calcularDescontoVisita($subtotalVisitas, $quantidadeTickets), $cupom->id];
    }

    private function localizarOuCriarCliente(int $empresaId, array $dadosCliente): Cliente
    {
        $cliente = null;

        if (! empty($dadosCliente['cpf_cnpj'])) {
            $cliente = Cliente::where('cpf_cnpj', $dadosCliente['cpf_cnpj'])->first();
        } elseif (! empty($dadosCliente['email'])) {
            $cliente = Cliente::where('email', $dadosCliente['email'])->first();
        }

        // Nem todo checkout manda endereço/RG/IE (só é obrigatório quando
        // há produto físico ou quando aplicável ao tipo de documento) -
        // por isso, ao atualizar um cliente já cadastrado, mantém o valor
        // anterior no campo que não veio preenchido desta vez.
        $atributos = [
            'empresa_id' => $empresaId,
            'nome' => $dadosCliente['nome'],
            'cpf_cnpj' => $dadosCliente['cpf_cnpj'] ?? $cliente?->cpf_cnpj,
            'rg' => $dadosCliente['rg'] ?? $cliente?->rg,
            'inscricao_estadual' => $dadosCliente['inscricao_estadual'] ?? $cliente?->inscricao_estadual,
            'email' => $dadosCliente['email'] ?? $cliente?->email,
            'telefone' => $dadosCliente['telefone'] ?? $cliente?->telefone,
            'cep' => $dadosCliente['cep'] ?? $cliente?->cep,
            'logradouro' => $dadosCliente['logradouro'] ?? $cliente?->logradouro,
            'numero' => $dadosCliente['numero'] ?? $cliente?->numero,
            'bairro' => $dadosCliente['bairro'] ?? $cliente?->bairro,
            'municipio' => $dadosCliente['municipio'] ?? $cliente?->municipio,
            'uf' => $dadosCliente['uf'] ?? $cliente?->uf,
            'codigo_ibge_municipio' => $dadosCliente['codigo_ibge_municipio'] ?? $cliente?->codigo_ibge_municipio,
            'consentimento_lgpd' => true,
            'consentimento_lgpd_data' => now(),
            'consentimento_lgpd_versao' => 'v1',
        ];

        if ($cliente) {
            $cliente->update($atributos);

            return $cliente;
        }

        return Cliente::create($atributos);
    }

    /**
     * @return array{0: float, 1: int} [valor_total, quantidade_tickets]
     */
    private function confirmarReservaEGerarItem(Venda $venda, int $reservaId): array
    {
        $reserva = ReservaTemporaria::findOrFail($reservaId);
        $agenda = AgendaVisitacao::findOrFail($reserva->agenda_visitacao_id);

        $this->reservaVagaService->confirmar($reservaId);

        $valorTotal = $agenda->valor_visita * $reserva->quantidade;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'agenda_visitacao_id' => $agenda->id,
            'quantidade' => $reserva->quantidade,
            'valor_unitario' => $agenda->valor_visita,
            'valor_total' => $valorTotal,
        ]);

        return [$valorTotal, $reserva->quantidade];
    }

    private function gerarItemProduto(Venda $venda, int $produtoId, int $quantidade, ?int $variacaoId = null, array $escolhas = []): float
    {
        $produto = Produto::findOrFail($produtoId);

        abort_if(! $produto->ativo, 422, "\"{$produto->nome}\" não está mais disponível.");

        if ($produto->eh_kit) {
            $composicao = $this->kitService->consumir($produto, $quantidade, $escolhas);
            $valorTotal = $produto->preco_venda * $quantidade;

            $venda->itens()->create([
                'empresa_id' => $venda->empresa_id,
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'valor_unitario' => $produto->preco_venda,
                'valor_total' => $valorTotal,
                'composicao' => $composicao,
            ]);

            return $valorTotal;
        }

        $variacao = null;

        if ($variacaoId !== null) {
            $variacao = ProdutoVariacao::where('produto_id', $produto->id)->findOrFail($variacaoId);
            abort_if(! $variacao->disponivelParaVenda(), 422, "\"{$produto->nome} ({$variacao->tamanho})\" não está mais disponível.");
            // variação vinculada baixa o estoque do produto real (ex.: CERVEJA PILSEN)
            $variacao->baixar($quantidade, $produto->nome.' (tamanho '.$variacao->tamanho.')');
        } elseif ($produto->estoque_atual !== null) {
            abort_if($produto->estoque_atual < $quantidade, 409, 'Estoque insuficiente para '.$produto->nome);
            $produto->decrement('estoque_atual', $quantidade);
        }

        $valorTotal = $produto->preco_venda * $quantidade;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            // variação vinculada: o item (e a nota) é do produto real, com o preço da vitrine
            'produto_id' => $variacao ? $variacao->produtoParaFaturar($produto)->id : $produto->id,
            'produto_variacao_id' => $variacao?->id,
            'quantidade' => $quantidade,
            'valor_unitario' => $produto->preco_venda,
            'valor_total' => $valorTotal,
        ]);

        return $valorTotal;
    }
}
