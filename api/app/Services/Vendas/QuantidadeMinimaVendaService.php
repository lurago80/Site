<?php

namespace App\Services\Vendas;

use App\Models\Produto;

/**
 * Valida a venda mínima por produto (Escopo v2, decisão de 2026-09-29):
 * alguns produtos só podem ser vendidos em lote fechado (ex.: caixa de 6
 * cervejas), mas o cliente pode misturar livremente as variações/sabores
 * dentro desse lote - por isso a regra soma a quantidade de todas as
 * variações de um mesmo produto, não valida variação por variação.
 * Compartilhado entre o checkout da loja pública e o PDV.
 */
class QuantidadeMinimaVendaService
{
    /**
     * @param  array<int, array{produto_id: int, quantidade: int}>  $itens
     */
    public function validar(array $itens): void
    {
        $totalPorProduto = [];

        foreach ($itens as $item) {
            $produtoId = (int) $item['produto_id'];
            $totalPorProduto[$produtoId] = ($totalPorProduto[$produtoId] ?? 0) + (int) $item['quantidade'];
        }

        if ($totalPorProduto === []) {
            return;
        }

        $produtos = Produto::whereIn('id', array_keys($totalPorProduto))
            ->whereNotNull('quantidade_minima_venda')
            ->get(['id', 'nome', 'quantidade_minima_venda']);

        foreach ($produtos as $produto) {
            $total = $totalPorProduto[$produto->id];

            abort_if(
                $total < $produto->quantidade_minima_venda,
                422,
                "A venda mínima de \"{$produto->nome}\" é {$produto->quantidade_minima_venda} unidades (pode misturar as variações) - ".
                    "há apenas {$total} no carrinho."
            );
        }
    }
}
