<?php

namespace App\Services\Pdv;

use App\Models\AgendaVisitacao;
use App\Models\Atendente;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\Cupom;
use App\Models\DescontoPdv;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\Venda;
use App\Models\VendaPagamento;
use App\Models\Vendedor;
use App\Services\Agendamento\BrindeVisitaService;
use App\Services\Agendamento\ReservaVagaService;
use App\Services\Fiscal\EmissaoFiscalService;
use App\Services\Alertas\AlertaService;
use App\Services\Vendas\KitService;
use App\Services\Vendas\LancamentoVendaService;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra a finalização de uma venda no PDV (frente de caixa):
 * produtos físicos (com baixa de estoque), visita agendada (via
 * ReservaVagaService - mesma trava anti-overbooking da loja pública) e
 * comissão por vendedor (Escopo v2, seção 2.2). Emite NFC-e na hora
 * quando a venda é fiscal, reaproveitando o EmissaoFiscalService.
 */
class VendaPdvService
{
    public function __construct(
        private readonly ReservaVagaService $reservaVagaService,
        private readonly BrindeVisitaService $brindeVisitaService,
        private readonly EmissaoFiscalService $emissaoFiscalService,
        private readonly CaixaService $caixaService,
        private readonly KitService $kitService,
        private readonly LancamentoVendaService $lancamentoVendaService,
        private readonly AlertaService $alertaService,
    ) {}

    public function finalizar(Empresa $empresa, array $dados, int $usuarioId): Venda
    {
        return DB::transaction(function () use ($empresa, $dados, $usuarioId) {
            $vendedor = ! empty($dados['vendedor_id'])
                ? Vendedor::findOrFail($dados['vendedor_id'])
                : null;

            $atendente = ! empty($dados['atendente_id'])
                ? Atendente::findOrFail($dados['atendente_id'])
                : null;

            $cliente = ! empty($dados['cliente']['nome'] ?? null)
                ? $this->localizarOuCriarCliente($empresa->id, $dados['cliente'])
                : null;

            $venda = Venda::create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente?->id,
                'vendedor_id' => $vendedor?->id,
                'atendente_id' => $atendente?->id,
                // Preenchido depois de processar os pagamentos (null se cortesia ou se
                // a venda tiver mais de uma forma - ver bloco de pagamento misto abaixo).
                'forma_pagamento_id' => null,
                'indicacao_id' => $dados['indicacao_id'] ?? null,
                'canal' => 'pdv',
                'tipo_doc' => $dados['tipo_doc'],
                'status_pagamento' => 'pago',
                'valor_total' => 0,
                'comissao' => 0,
                'data_venda' => now(),
            ]);

            $valorTotal = 0;
            $comissaoTotal = 0;
            $valorDesconto = 0;
            $cupomId = null;
            $valorProdutos = 0;
            $valorVisitas = 0;
            $quantidadeTickets = 0;

            foreach ($dados['itens'] ?? [] as $item) {
                [$valorItem, $comissaoItem] = $this->criarItemProduto($venda, $item, $vendedor, $empresa);
                $valorTotal += $valorItem;
                $valorProdutos += $valorItem;
                $comissaoTotal += $comissaoItem;
            }

            if (! empty($dados['agenda_visitacao_id'])) {
                [$valorItem, $comissaoItem] = $this->criarItemAgenda(
                    $venda,
                    (int) $dados['agenda_visitacao_id'],
                    (int) $dados['agenda_quantidade'],
                    $vendedor,
                );
                $valorTotal += $valorItem;
                $valorVisitas = $valorItem;
                $quantidadeTickets = (int) $dados['agenda_quantidade'];
                $comissaoTotal += $comissaoItem;

                // Toda visita efetivada no PDV já baixa o brinde (ex.: caneca de chopp) na
                // hora - o pagamento no PDV é sempre imediato. Na loja online esse mesmo
                // brinde só é baixado no check-in (ver PdvController::confirmarCheckIn).
                $this->brindeVisitaService->baixar($venda, $empresa, $quantidadeTickets);

                // Cupom só se aplica sobre a visita agendada (pedido do
                // cliente) - uma venda com produto + visita desconta só a
                // parte da visita, não o carrinho inteiro.
                if (! empty($dados['cupom_codigo'])) {
                    [$valorDesconto, $cupomId] = $this->aplicarCupomNaVisita(
                        $dados['cupom_codigo'],
                        $valorItem,
                        (int) $dados['agenda_quantidade'],
                        $cliente?->id,
                    );
                }
            }

            // Desconto manual do PDV (só produtos ou só visitas, conforme o
            // cadastro). Nunca combina com cupom na visita - o controller já
            // recusa essa combinação; aqui só calcula.
            $descontoPdvId = null;

            if (! empty($dados['desconto_pdv_id'])) {
                $descontoPdv = DescontoPdv::where('ativo', true)->find($dados['desconto_pdv_id']);

                abort_if($descontoPdv === null, 422, 'Desconto não encontrado ou desativado.');
                abort_if(
                    $descontoPdv->aplicaEmVisitas() ? $valorVisitas <= 0 : $valorProdutos <= 0,
                    422,
                    $descontoPdv->aplicaEmVisitas()
                        ? 'Este desconto só pode ser usado quando há uma visita na venda.'
                        : 'Este desconto só pode ser usado quando há produtos na venda.'
                );

                $valorDesconto += $descontoPdv->calcular($valorProdutos, $valorVisitas, $quantidadeTickets);
                $descontoPdvId = $descontoPdv->id;
            }

            // Cortesia: desconta 100% da venda (produtos e visitas).
            $cortesia = (bool) ($dados['cortesia'] ?? false);
            if ($cortesia) {
                $valorDesconto = $valorTotal;
            }

            $valorDesconto = min($valorDesconto, $valorTotal);

            $venda->update([
                'valor_total' => $valorTotal - $valorDesconto,
                'comissao' => $comissaoTotal > 0 ? $comissaoTotal : null,
                'cupom_id' => $cupomId,
                'desconto_pdv_id' => $descontoPdvId,
                'valor_desconto' => $valorDesconto ?: null,
                'cortesia' => $cortesia,
                'motivo_desconto' => ($cortesia || $descontoPdvId) ? ($dados['motivo_desconto'] ?? null) : null,
            ]);

            // Cortesia não é cobrada: sem pagamento nenhum, mesmo que o operador tenha
            // mandado algo (o controller já valida isso antes de chegar aqui). Precisa
            // rodar ANTES do lançamento no plano de contas: ehConsumoInterno() olha
            // formaPagamento, que só fica definitivo depois de processar os pagamentos.
            $this->processarPagamentos($venda, $empresa, $usuarioId, $cortesia ? [] : ($dados['pagamentos'] ?? []), $valorTotal - $valorDesconto);

            // Plano de contas: receita de produtos/serviços e descontos concedidos.
            // (recarrega a venda por dentro, já pegando a forma de pagamento definida acima)
            $this->lancamentoVendaService->registrar($venda);

            // Cortesia e desconto alto avisam o administrador.
            $this->alertaService->vendaComDesconto($venda, $empresa, auth()->user()?->name);

            if ($dados['tipo_doc'] === 'fiscal') {
                $this->emissaoFiscalService->emitir($venda->fresh('itens'), 65);
            }

            return $venda->fresh([
                'itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente', 'vendedor',
                'atendente', 'formaPagamento', 'pagamentos.formaPagamento', 'descontoPdv', 'empresa', 'documentoFiscal',
            ]);
        });
    }

    /**
     * Pagamento misto: grava uma linha em `venda_pagamentos` por forma usada
     * (dinheiro, cartão, Pix...), confere que a soma bate com o total da
     * venda e lança no caixa físico só a parcela em dinheiro (nunca mais de
     * uma linha de caixa por venda, mesmo com várias formas).
     *
     * @param  array<int, array{forma_pagamento_id: int, valor: float, valor_recebido?: ?float}>  $pagamentos
     */
    private function processarPagamentos(Venda $venda, Empresa $empresa, int $usuarioId, array $pagamentos, float $valorAPagar): void
    {
        if (empty($pagamentos)) {
            return;
        }

        // Com 1 forma só, o valor é opcional - cobre o total inteiro (o operador não
        // precisa digitar um valor óbvio). Com 2+, cada linha precisa do seu valor.
        if (count($pagamentos) === 1 && ! isset($pagamentos[0]['valor'])) {
            $pagamentos[0]['valor'] = $valorAPagar;
        }

        abort_if(
            count($pagamentos) > 1 && collect($pagamentos)->contains(fn ($p) => ! isset($p['valor'])),
            422,
            'Informe o valor de cada forma de pagamento quando usar mais de uma.'
        );

        $somaPagamentos = round(array_sum(array_column($pagamentos, 'valor')), 2);
        abort_if(
            abs($somaPagamentos - round($valorAPagar, 2)) > 0.01,
            422,
            "A soma das formas de pagamento (R$ {$somaPagamentos}) não bate com o total da venda (R$ {$valorAPagar})."
        );

        $formasPagamento = FormaPagamento::whereIn('id', array_column($pagamentos, 'forma_pagamento_id'))->get()->keyBy('id');
        $valorDinheiro = 0.0;

        foreach ($pagamentos as $pagamento) {
            $forma = $formasPagamento->get($pagamento['forma_pagamento_id']);
            abort_if($forma === null, 422, 'Forma de pagamento inválida.');
            abort_if($forma->tipo === 'carteira' && $venda->cliente_id === null, 422, 'Informe o cliente para vender na carteira.');

            VendaPagamento::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'forma_pagamento_id' => $forma->id,
                'valor' => $pagamento['valor'],
                'valor_recebido' => $forma->tipo === 'dinheiro' ? ($pagamento['valor_recebido'] ?? $pagamento['valor']) : null,
            ]);

            if ($forma->tipo === 'dinheiro') {
                $valorDinheiro += (float) $pagamento['valor'];
            }

            // Carteira não entra no caixa físico nem é recebida na hora - é crédito
            // ao cliente, cobrado depois via contas a receber (vencimento padrão de
            // 15 dias, igual prazo combinado com o cliente).
            if ($forma->tipo === 'carteira') {
                ContaReceber::create([
                    'empresa_id' => $venda->empresa_id,
                    'cliente_id' => $venda->cliente_id,
                    'venda_id' => $venda->id,
                    'historico' => "Venda PDV #{$venda->id} - carteira",
                    'valor' => $pagamento['valor'],
                    'vencimento' => now()->addDays(15),
                    'status' => 'em_aberto',
                ]);
            }
        }

        // Forma única (caso mais comum) continua preenchendo vendas.forma_pagamento_id,
        // para quem ainda lê essa coluna direto (recibo, fallback fiscal). Com 2+
        // formas, fica null - o detalhe mora em venda_pagamentos.
        if (count($pagamentos) === 1) {
            $venda->update(['forma_pagamento_id' => $pagamentos[0]['forma_pagamento_id']]);
        }

        if ($valorDinheiro > 0) {
            $this->caixaService->registrarVenda($empresa, $usuarioId, $valorDinheiro, "Venda #{$venda->id}");
        }
    }

    /**
     * @return array{0: float, 1: float} [valorItem, comissaoItem]
     */
    private function criarItemProduto(Venda $venda, array $item, ?Vendedor $vendedor, Empresa $empresa): array
    {
        $produto = Produto::findOrFail($item['produto_id']);
        abort_if(! $produto->ativo, 422, "\"{$produto->nome}\" está desativado e não pode ser vendido.");
        abort_if($produto->somente_loja_virtual, 422, "\"{$produto->nome}\" só é vendido na loja virtual.");
        $quantidade = (int) $item['quantidade'];

        if ($produto->eh_kit) {
            // Preço fixo do kit; a nota rateia esse valor entre os componentes (composicao).
            $composicao = $this->kitService->consumir($produto, $quantidade, $item['escolhas'] ?? []);
            $valorItem = (float) $produto->preco_venda * $quantidade;
            $comissaoItem = $vendedor ? round($valorItem * (float) $vendedor->percentual_comissao / 100, 2) : 0;

            $venda->itens()->create([
                'empresa_id' => $venda->empresa_id,
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'valor_unitario' => $produto->preco_venda,
                'valor_total' => $valorItem,
                'comissao_percentual' => $vendedor?->percentual_comissao,
                'comissao_valor' => $comissaoItem ?: null,
                'composicao' => $composicao,
            ]);

            return [$valorItem, $comissaoItem];
        }

        $variacao = null;
        if (! empty($item['variacao_id'])) {
            $variacao = ProdutoVariacao::where('produto_id', $produto->id)->findOrFail($item['variacao_id']);
            abort_if(! $variacao->disponivelParaVenda(), 422, "\"{$produto->nome} ({$variacao->tamanho})\" está desativado e não pode ser vendido.");
            $variacao->baixar($quantidade, "{$produto->nome} ({$variacao->tamanho})", (bool) $empresa->estoque_permite_negativo);
        } elseif ($produto->estoque_atual !== null) {
            abort_if(
                ! $empresa->estoque_permite_negativo && $produto->estoque_atual < $quantidade,
                409,
                "Estoque insuficiente para {$produto->nome}."
            );
            $produto->decrement('estoque_atual', $quantidade);
        }

        $valorItem = (float) $produto->preco_venda * $quantidade;
        $comissaoItem = $vendedor ? round($valorItem * (float) $vendedor->percentual_comissao / 100, 2) : 0;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'produto_id' => $variacao ? $variacao->produtoParaFaturar($produto)->id : $produto->id,
            'produto_variacao_id' => $variacao?->id,
            'quantidade' => $quantidade,
            'valor_unitario' => $produto->preco_venda,
            'valor_total' => $valorItem,
            'comissao_percentual' => $vendedor?->percentual_comissao,
            'comissao_valor' => $comissaoItem ?: null,
        ]);

        return [$valorItem, $comissaoItem];
    }

    /**
     * @return array{0: float, 1: float} [valorItem, comissaoItem]
     */
    private function criarItemAgenda(Venda $venda, int $agendaVisitacaoId, int $quantidade, ?Vendedor $vendedor): array
    {
        $reserva = $this->reservaVagaService->reservar($agendaVisitacaoId, $quantidade);
        $this->reservaVagaService->confirmar($reserva->id);

        $agenda = AgendaVisitacao::findOrFail($agendaVisitacaoId);
        $valorItem = (float) $agenda->valor_visita * $quantidade;
        $comissaoItem = $vendedor ? round($valorItem * (float) $vendedor->percentual_comissao / 100, 2) : 0;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'agenda_visitacao_id' => $agenda->id,
            'quantidade' => $quantidade,
            'valor_unitario' => $agenda->valor_visita,
            'valor_total' => $valorItem,
            'comissao_percentual' => $vendedor?->percentual_comissao,
            'comissao_valor' => $comissaoItem ?: null,
        ]);

        return [$valorItem, $comissaoItem];
    }

    /**
     * Mesma regra de validação/consumo do cupom usada no checkout da loja
     * pública (CheckoutController::aplicarCupom) - trava a linha para
     * evitar que duas vendas simultâneas consumam o último uso disponível
     * de um cupom limitado.
     *
     * @return array{0: float, 1: int} [valor_desconto, cupom_id]
     */
    private function aplicarCupomNaVisita(string $codigo, float $subtotalVisita, int $quantidadeTickets, ?int $clienteId): array
    {
        $cupom = Cupom::whereRaw('lower(codigo) = ?', [mb_strtolower($codigo)])->lockForUpdate()->first();

        abort_if($cupom === null, 422, 'Cupom não encontrado.');
        abort_if($cupom->motivoInvalido() !== null, 422, $cupom->motivoInvalido());

        $cupom->increment('usos_realizados');
        $cupom->update(['usado_em' => now(), 'usado_por_cliente_id' => $clienteId]);

        return [$cupom->calcularDescontoVisita($subtotalVisita, $quantidadeTickets), $cupom->id];
    }

    private function localizarOuCriarCliente(int $empresaId, array $dadosCliente): Cliente
    {
        $cliente = null;

        if (! empty($dadosCliente['cpf_cnpj'])) {
            $cliente = Cliente::where('cpf_cnpj', $dadosCliente['cpf_cnpj'])->first();
        }

        $atributos = [
            'empresa_id' => $empresaId,
            'nome' => $dadosCliente['nome'],
            'cpf_cnpj' => $dadosCliente['cpf_cnpj'] ?? null,
            'telefone' => $dadosCliente['telefone'] ?? null,
        ];

        if ($cliente) {
            $cliente->update($atributos);

            return $cliente;
        }

        return Cliente::create($atributos + ['consentimento_lgpd' => false]);
    }
}
