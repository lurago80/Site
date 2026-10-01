<?php

namespace App\Services\Pdv;

use App\Models\AgendaVisitacao;
use App\Models\Atendente;
use App\Models\Cliente;
use App\Models\Cupom;
use App\Models\DescontoPdv;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Services\Agendamento\ReservaVagaService;
use App\Services\Fiscal\EmissaoFiscalService;
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
        private readonly EmissaoFiscalService $emissaoFiscalService,
        private readonly CaixaService $caixaService,
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
                'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
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

            $valorDesconto = min($valorDesconto, $valorTotal);

            $venda->update([
                'valor_total' => $valorTotal - $valorDesconto,
                'comissao' => $comissaoTotal > 0 ? $comissaoTotal : null,
                'cupom_id' => $cupomId,
                'desconto_pdv_id' => $descontoPdvId,
                'valor_desconto' => $valorDesconto ?: null,
            ]);

            $formaPagamento = ! empty($dados['forma_pagamento_id'])
                ? FormaPagamento::find($dados['forma_pagamento_id'])
                : null;

            if ($formaPagamento?->tipo === 'dinheiro' && $valorTotal - $valorDesconto > 0) {
                $this->caixaService->registrarVenda($empresa, $usuarioId, $valorTotal - $valorDesconto, "Venda #{$venda->id}");
            }

            if ($dados['tipo_doc'] === 'fiscal') {
                $this->emissaoFiscalService->emitir($venda->fresh('itens'), 65);
            }

            return $venda->fresh([
                'itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente', 'vendedor',
                'atendente', 'formaPagamento', 'descontoPdv', 'empresa', 'documentoFiscal',
            ]);
        });
    }

    /**
     * @return array{0: float, 1: float} [valorItem, comissaoItem]
     */
    private function criarItemProduto(Venda $venda, array $item, ?Vendedor $vendedor, Empresa $empresa): array
    {
        $produto = Produto::findOrFail($item['produto_id']);
        abort_if(! $produto->ativo, 422, "\"{$produto->nome}\" está desativado e não pode ser vendido.");
        abort_if($produto->eh_kit, 422, "O kit \"{$produto->nome}\" só é vendido na loja virtual.");
        abort_if($produto->somente_loja_virtual, 422, "\"{$produto->nome}\" só é vendido na loja virtual.");
        $quantidade = (int) $item['quantidade'];

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
