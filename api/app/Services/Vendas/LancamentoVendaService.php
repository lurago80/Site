<?php

namespace App\Services\Vendas;

use App\Models\ItemVenda;
use App\Models\LancamentoPlanoConta;
use App\Models\PlanoContas;
use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lança cada venda paga no plano de contas:
 *  - produtos  -> 002.001 (receita de produtos): venda - custo - desconto dos produtos
 *  - visitas   -> 002.002 (receita de serviços): venda - custo - desconto das visitas
 *  - desconto  -> 001.015 (descontos concedidos): valor total do desconto
 *
 * Venda paga com a forma de pagamento "CONSUMO INTERNO" não é uma venda de
 * verdade (o produto foi usado pela própria empresa): não gera receita nem
 * desconto, só a despesa do custo dos itens em 001.016.
 *
 * Idempotente: refazer o lançamento de uma venda substitui os anteriores.
 * Cancelar a venda apaga os lançamentos (ver estornar()).
 */
class LancamentoVendaService
{
    public const CONTA_RECEITA_PRODUTOS = ['codigo' => '002.001', 'nome' => 'RECEITA PRODUTOS', 'tipo' => 'receita'];

    public const CONTA_RECEITA_SERVICOS = ['codigo' => '002.002', 'nome' => 'RECEITA SERVIÇOS', 'tipo' => 'receita'];

    public const CONTA_DESCONTOS = ['codigo' => '001.015', 'nome' => 'DESCONTOS CONCEDIDOS', 'tipo' => 'despesa'];

    public const CONTA_CONSUMO_INTERNO = ['codigo' => '001.016', 'nome' => 'CONSUMO INTERNO', 'tipo' => 'despesa'];

    public function registrar(Venda $venda): void
    {
        $venda = Venda::with(['itens.produto', 'itens.agendaVisitacao', 'formaPagamento'])->findOrFail($venda->id);

        $this->estornar($venda);

        if ($venda->status_pagamento !== 'pago' || $venda->cancelada_em !== null) {
            return;
        }

        foreach ($venda->itens as $item) {
            $custo = $this->custoDoItem($item);
            if ($item->custo_total === null || (float) $item->custo_total !== $custo) {
                $item->forceFill(['custo_total' => $custo])->save();
            }
        }

        $data = $venda->data_venda->copy()->setTimezone('America/Sao_Paulo')->toDateString();

        if ($this->ehConsumoInterno($venda)) {
            $custoTotal = round((float) $venda->itens->sum('custo_total'), 2);

            if ($custoTotal > 0) {
                $this->lancar($venda, self::CONTA_CONSUMO_INTERNO, 'consumo_interno', $custoTotal, $data, 'Consumo interno');
            }

            return;
        }

        $produtos = $venda->itens->whereNull('agenda_visitacao_id');
        $visitas = $venda->itens->whereNotNull('agenda_visitacao_id');

        $desconto = (float) $venda->valor_desconto;
        [$descontoProdutos, $descontoVisitas] = $this->ratearDesconto($venda, $produtos, $visitas, $desconto);

        $receitaProdutos = $this->margem($produtos) - $descontoProdutos;
        $receitaVisitas = $this->margem($visitas) - $descontoVisitas;

        if ($produtos->isNotEmpty()) {
            $this->lancar($venda, self::CONTA_RECEITA_PRODUTOS, 'receita_produtos', $receitaProdutos, $data, 'Receita de produtos');
        }
        if ($visitas->isNotEmpty()) {
            $this->lancar($venda, self::CONTA_RECEITA_SERVICOS, 'receita_servicos', $receitaVisitas, $data, 'Receita de visitas');
        }
        if ($desconto > 0) {
            $this->lancar($venda, self::CONTA_DESCONTOS, 'desconto', $desconto, $data, $venda->cortesia ? 'Cortesia' : 'Desconto concedido');
        }
    }

    private function ehConsumoInterno(Venda $venda): bool
    {
        return Str::upper(trim((string) $venda->formaPagamento?->descricao)) === 'CONSUMO INTERNO';
    }

    public function estornar(Venda $venda): void
    {
        LancamentoPlanoConta::where('venda_id', $venda->id)->delete();
    }

    /** Soma de (valor da venda - custo) das linhas. */
    private function margem(Collection $itens): float
    {
        return round($itens->sum(fn (ItemVenda $i) => (float) $i->valor_total - (float) $i->custo_total), 2);
    }

    /**
     * Quanto do desconto incide em produtos e em visitas: cortesia desconta tudo; desconto do
     * PDV incide só onde o cadastro manda (produtos ou visitas); cupom só desconta visitas.
     *
     * @return array{0: float, 1: float} [descontoProdutos, descontoVisitas]
     */
    private function ratearDesconto(Venda $venda, Collection $produtos, Collection $visitas, float $desconto): array
    {
        if ($desconto <= 0) {
            return [0.0, 0.0];
        }

        $brutoProdutos = (float) $produtos->sum('valor_total');
        $brutoVisitas = (float) $visitas->sum('valor_total');

        if ($venda->cortesia) {
            return [min($desconto, $brutoProdutos), min($desconto - min($desconto, $brutoProdutos), $brutoVisitas)];
        }

        if ($venda->descontoPdv?->aplica_em === 'produtos' && $brutoProdutos > 0) {
            return [$desconto, 0.0];
        }

        return $brutoVisitas > 0 ? [0.0, $desconto] : [$desconto, 0.0];
    }

    /** Custo da linha: produto, kit (soma dos componentes) ou visita (custo por ticket x tickets). */
    private function custoDoItem(ItemVenda $item): float
    {
        if ($item->agenda_visitacao_id !== null) {
            return round((float) $item->agendaVisitacao?->custo_visita * $item->quantidade, 2);
        }

        if (! empty($item->composicao)) {
            $custoPorProduto = Produto::whereIn('id', collect($item->composicao)->pluck('produto_id'))->pluck('preco_custo', 'id');

            return round(collect($item->composicao)->sum(
                fn ($c) => (float) ($custoPorProduto[$c['produto_id']] ?? 0) * (int) $c['quantidade']
            ), 2);
        }

        return round((float) $item->produto?->preco_custo * $item->quantidade, 2);
    }

    private function lancar(Venda $venda, array $conta, string $origem, float $valor, string $data, string $historico): void
    {
        $plano = PlanoContas::firstOrCreate(
            ['empresa_id' => $venda->empresa_id, 'codigo' => $conta['codigo']],
            ['nome' => $conta['nome'], 'tipo' => $conta['tipo'], 'ativo' => true],
        );

        LancamentoPlanoConta::create([
            'empresa_id' => $venda->empresa_id,
            'venda_id' => $venda->id,
            'plano_conta_id' => $plano->id,
            'origem' => $origem,
            'valor' => round($valor, 2),
            'data' => $data,
            'historico' => "{$historico} - venda #{$venda->id}",
        ]);
    }
}
