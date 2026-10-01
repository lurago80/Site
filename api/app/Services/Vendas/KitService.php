<?php

namespace App\Services\Vendas;

use App\Models\Produto;
use App\Models\ProdutoVariacao;

/**
 * Regras do kit (ex.: 1 caneca + 3 cervejas à escolha): valida a escolha do
 * cliente, baixa o estoque de cada componente e devolve a composição que
 * fica registrada no item da venda. Preço é o do próprio kit (fixo).
 * Deve rodar dentro de DB::transaction - qualquer abort desfaz as baixas.
 */
class KitService
{
    /**
     * Payload do kit para o catálogo público: itens fixos, grupos de escolha
     * (com as variações e o estoque de cada uma) e se há estoque para vender.
     */
    public function resumo(Produto $kit): array
    {
        $kit->loadMissing(['componentes.produto.variacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tamanho')]);

        $fixos = [];
        $escolhas = [];
        $disponivel = $kit->componentes->isNotEmpty();

        foreach ($kit->componentes as $componente) {
            $produto = $componente->produto;

            if ($componente->tipo === 'fixo') {
                $fixos[] = ['produto_id' => $produto->id, 'nome' => $produto->nome, 'quantidade' => $componente->quantidade];
                $disponivel = $disponivel && ($produto->estoque_atual === null || $produto->estoque_atual >= $componente->quantidade);

                continue;
            }

            $variacoes = $produto->variacoes->map(fn ($v) => [
                'id' => $v->id, 'tamanho' => $v->tamanho, 'estoque_atual' => $v->estoque_atual,
            ])->values();

            $escolhas[] = [
                'produto_id' => $produto->id,
                'nome' => $produto->nome,
                'quantidade' => $componente->quantidade,
                'variacoes' => $variacoes,
            ];
            $disponivel = $disponivel && $variacoes->sum('estoque_atual') >= $componente->quantidade;
        }

        return ['fixos' => $fixos, 'escolhas' => $escolhas, 'disponivel' => $disponivel];
    }

    /**
     * @param  array<int, array{variacao_id: int, quantidade: int}>  $escolhas
     * @return array<int, array{produto_id: int, nome: string, variacao_id: int|null, tamanho: string|null, quantidade: int}>
     */
    public function consumir(Produto $kit, int $quantidadeKits, array $escolhas): array
    {
        $kit->loadMissing('componentes.produto');

        abort_if($kit->componentes->isEmpty(), 422, "O kit \"{$kit->nome}\" ainda não está configurado.");

        $composicao = [];

        foreach ($kit->componentes->where('tipo', 'fixo') as $componente) {
            $produto = Produto::lockForUpdate()->findOrFail($componente->produto_id);
            $quantidade = $componente->quantidade * $quantidadeKits;

            if ($produto->estoque_atual !== null) {
                abort_if($produto->estoque_atual < $quantidade, 409, "Estoque insuficiente para {$produto->nome} (kit {$kit->nome}).");
                $produto->decrement('estoque_atual', $quantidade);
            }

            $composicao[] = [
                'produto_id' => $produto->id, 'nome' => $produto->nome,
                'variacao_id' => null, 'tamanho' => null, 'quantidade' => $quantidade,
            ];
        }

        $grupos = $kit->componentes->where('tipo', 'escolha')->keyBy('produto_id');

        // A mesma variação pode vir repetida (ou o cliente pode repetir o
        // mesmo sabor) - soma antes de validar/baixar.
        $porVariacao = [];
        foreach ($escolhas as $escolha) {
            $porVariacao[(int) $escolha['variacao_id']] = ($porVariacao[(int) $escolha['variacao_id']] ?? 0) + (int) $escolha['quantidade'];
        }

        $totalPorGrupo = [];

        foreach ($porVariacao as $variacaoId => $quantidade) {
            $variacao = ProdutoVariacao::lockForUpdate()->where('ativo', true)->find($variacaoId);

            abort_if($variacao === null || ! $grupos->has($variacao->produto_id), 422, "Escolha inválida para o kit \"{$kit->nome}\".");

            $nomeProduto = $grupos[$variacao->produto_id]->produto->nome;

            abort_if($variacao->estoque_atual < $quantidade, 409, "Estoque insuficiente para {$nomeProduto} ({$variacao->tamanho}).");
            $variacao->decrement('estoque_atual', $quantidade);

            $totalPorGrupo[$variacao->produto_id] = ($totalPorGrupo[$variacao->produto_id] ?? 0) + $quantidade;

            $composicao[] = [
                'produto_id' => $variacao->produto_id, 'nome' => $nomeProduto,
                'variacao_id' => $variacao->id, 'tamanho' => $variacao->tamanho, 'quantidade' => $quantidade,
            ];
        }

        foreach ($grupos as $produtoId => $grupo) {
            $esperado = $grupo->quantidade * $quantidadeKits;

            abort_if(
                ($totalPorGrupo[$produtoId] ?? 0) !== $esperado,
                422,
                "Escolha exatamente {$esperado} unidade(s) de {$grupo->produto->nome} para o kit \"{$kit->nome}\"."
            );
        }

        return $composicao;
    }
}
