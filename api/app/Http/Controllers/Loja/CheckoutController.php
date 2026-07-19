<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\Produto;
use App\Jobs\EnviarConfirmacaoAgendamentoJob;
use App\Models\ReservaTemporaria;
use App\Models\Venda;
use App\Services\Agendamento\ReservaVagaService;
use App\Services\Pagamento\PagamentoService;
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
    ) {}

    public function store(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'cliente.nome' => ['required', 'string', 'max:255'],
            'cliente.cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'cliente.email' => ['nullable', 'email'],
            'cliente.telefone' => ['nullable', 'string', 'max:20'],
            'cliente.consentimento_lgpd' => ['required', 'accepted'],
            'reserva_id' => ['nullable', 'integer'],
            'itens' => ['nullable', 'array'],
            'itens.*.produto_id' => ['required_with:itens', 'integer'],
            'itens.*.quantidade' => ['required_with:itens', 'integer', 'min:1'],
            'forma_pagamento' => ['required', 'string', 'in:pix,cartao'],
            'cartao_token' => ['nullable', 'string'],
            'cartao_parcelas' => ['nullable', 'integer', 'min:1'],
            'cartao_metodo' => ['nullable', 'string', 'in:cartao_credito,cartao_debito'],
        ]);

        abort_if(
            empty($dados['reserva_id']) && empty($dados['itens']),
            422,
            'Informe uma reserva de vaga ou ao menos um item de produto.'
        );

        $empresaAtual = $request->attributes->get('empresaAtual');

        $venda = DB::transaction(function () use ($dados, $empresaAtual) {
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

            $valorTotal = 0;

            if (! empty($dados['reserva_id'])) {
                $valorTotal += $this->confirmarReservaEGerarItem($venda, $dados['reserva_id']);
            }

            foreach ($dados['itens'] ?? [] as $item) {
                $valorTotal += $this->gerarItemProduto($venda, $item['produto_id'], $item['quantidade']);
            }

            $venda->update(['valor_total' => $valorTotal]);

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

        $vendaFinal = $venda->fresh()->load('itens', 'cliente');
        $vendaFinal->setAttribute('cobranca', $cobranca ? [
            'status' => $cobranca->status,
            'qr_code' => $cobranca->qr_code,
            'qr_code_base64' => $cobranca->qr_code_base64,
            'expira_em' => $cobranca->expira_em,
        ] : null);

        return response()->json($vendaFinal, 201);
    }

    private function localizarOuCriarCliente(int $empresaId, array $dadosCliente): Cliente
    {
        $cliente = null;

        if (! empty($dadosCliente['cpf_cnpj'])) {
            $cliente = Cliente::where('cpf_cnpj', $dadosCliente['cpf_cnpj'])->first();
        } elseif (! empty($dadosCliente['email'])) {
            $cliente = Cliente::where('email', $dadosCliente['email'])->first();
        }

        $atributos = [
            'empresa_id' => $empresaId,
            'nome' => $dadosCliente['nome'],
            'cpf_cnpj' => $dadosCliente['cpf_cnpj'] ?? null,
            'email' => $dadosCliente['email'] ?? null,
            'telefone' => $dadosCliente['telefone'] ?? null,
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

    private function confirmarReservaEGerarItem(Venda $venda, int $reservaId): float
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

        return $valorTotal;
    }

    private function gerarItemProduto(Venda $venda, int $produtoId, int $quantidade): float
    {
        $produto = Produto::findOrFail($produtoId);

        if ($produto->estoque_atual !== null) {
            abort_if($produto->estoque_atual < $quantidade, 409, 'Estoque insuficiente para '.$produto->nome);
            $produto->decrement('estoque_atual', $quantidade);
        }

        $valorTotal = $produto->preco_venda * $quantidade;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
            'valor_unitario' => $produto->preco_venda,
            'valor_total' => $valorTotal,
        ]);

        return $valorTotal;
    }
}
