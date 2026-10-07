<?php

namespace App\Services\Vendas;

use App\Models\KitComponente;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use Illuminate\Support\Collection;

/**
 * Regras do kit (ex.: 1 caneca + 3 cervejas à escolha): valida a escolha do
 * cliente, baixa o estoque de cada componente e devolve a composição que
 * fica registrada no item da venda. Preço é o do próprio kit (fixo).
 *
 * Itens "à escolha" podem ser produtos com variações (o cliente escolhe a
 * variação) ou produtos simples (a opção é o próprio produto). Itens com o
 * mesmo `grupo` formam uma escolha só, com limite conjunto.
 *
 * Deve rodar dentro de DB::transaction - qualquer abort desfaz as baixas.
 */
class KitService
{
    /**
     * Payload do kit para o catálogo/PDV: itens fixos, grupos de escolha
     * (com as opções e o estoque de cada uma) e se há estoque para vender.
     */
    public function resumo(Produto $kit): array
    {
        $kit->loadMissing([
            'componentes.produto.variacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tamanho'),
            'componentes.produto.variacoes.produtoVinculado',
        ]);

        $fixos = [];
        $escolhas = [];
        $disponivel = $kit->componentes->isNotEmpty();
        $escolhaLivre = $kit->kit_total_escolhas !== null;

        foreach ($kit->componentes->where('tipo', 'fixo') as $componente) {
            $produto = $componente->produto;

            $fixos[] = ['produto_id' => $produto->id, 'nome' => $produto->nome, 'quantidade' => $componente->quantidade];
            $disponivel = $disponivel && ($produto->estoque_atual === null || $produto->estoque_atual >= $componente->quantidade);
        }

        foreach ($this->grupos($kit) as $componentes) {
            $opcoes = collect();

            foreach ($componentes as $componente) {
                $produto = $componente->produto;

                if ($produto->variacoes->isEmpty()) {
                    // produto simples: a opção é o próprio produto
                    $opcoes->push([
                        'id' => null, 'variacao_id' => null, 'produto_id' => $produto->id, 'simples' => true,
                        'tamanho' => $produto->nome,
                        'estoque_atual' => $produto->ativo ? ($produto->estoque_atual ?? ProdutoVariacao::ESTOQUE_ILIMITADO_EXIBIDO) : 0,
                    ]);

                    continue;
                }

                foreach ($produto->variacoes->filter(fn ($v) => $v->disponivelParaVenda()) as $v) {
                    $opcoes->push([
                        'id' => $v->id, 'variacao_id' => $v->id, 'produto_id' => null, 'simples' => false,
                        'tamanho' => $v->tamanho, 'estoque_atual' => $v->estoqueParaExibir(),
                    ]);
                }
            }

            $quantidade = $this->quantidadeDoGrupo($componentes);

            $escolhas[] = [
                'produto_id' => $componentes->first()->produto_id,
                'nome' => $this->nomeDoGrupo($componentes),
                'quantidade' => $quantidade,
                'variacoes' => $opcoes->values(),
            ];

            // na escolha livre a quantidade do grupo é só um teto - quem confere o estoque é o total, abaixo
            $disponivel = $disponivel && ($escolhaLivre || $opcoes->sum('estoque_atual') >= $quantidade);
        }

        if ($escolhaLivre) {
            $disponivel = $disponivel && collect($escolhas)->sum(fn ($g) => min($g['quantidade'], $g['variacoes']->sum('estoque_atual'))) >= $kit->kit_total_escolhas;
        }

        return ['fixos' => $fixos, 'escolhas' => $escolhas, 'total_escolhas' => $kit->kit_total_escolhas, 'disponivel' => $disponivel];
    }

    /**
     * @param  array<int, array{variacao_id?: int|null, produto_id?: int|null, quantidade: int}>  $escolhas
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

        $grupos = $this->grupos($kit);
        $componentesEscolha = $kit->componentes->where('tipo', 'escolha')->keyBy('produto_id');
        $grupoDoProduto = collect();
        foreach ($grupos as $chave => $itens) {
            foreach ($itens as $c) {
                $grupoDoProduto->put($c->produto_id, $chave);
            }
        }

        // A mesma opção pode vir repetida (ou o cliente pode repetir o mesmo
        // sabor) - soma antes de validar/baixar.
        $porOpcao = [];
        foreach ($escolhas as $escolha) {
            $chave = ! empty($escolha['variacao_id']) ? 'v:'.(int) $escolha['variacao_id'] : 'p:'.(int) ($escolha['produto_id'] ?? 0);
            $porOpcao[$chave] = ($porOpcao[$chave] ?? 0) + (int) $escolha['quantidade'];
        }

        $totalPorGrupo = [];

        foreach ($porOpcao as $chave => $quantidade) {
            [$tipoOpcao, $id] = explode(':', $chave);
            $id = (int) $id;

            if ($tipoOpcao === 'p') {
                // opção = produto simples do kit
                $produto = Produto::lockForUpdate()->where('ativo', true)->find($id);

                abort_if(
                    $produto === null || ! $grupoDoProduto->has($id) || $produto->variacoes()->where('ativo', true)->exists(),
                    422,
                    "Escolha inválida para o kit \"{$kit->nome}\"."
                );

                if ($produto->estoque_atual !== null) {
                    abort_if($produto->estoque_atual < $quantidade, 409, "Estoque insuficiente para {$produto->nome}.");
                    $produto->decrement('estoque_atual', $quantidade);
                }

                $totalPorGrupo[$grupoDoProduto[$id]] = ($totalPorGrupo[$grupoDoProduto[$id]] ?? 0) + $quantidade;

                $composicao[] = [
                    'produto_id' => $produto->id, 'nome' => $produto->nome,
                    'variacao_id' => null, 'tamanho' => null, 'quantidade' => $quantidade,
                ];

                continue;
            }

            $variacao = ProdutoVariacao::lockForUpdate()->where('ativo', true)->find($id);

            abort_if(
                $variacao === null || ! $grupoDoProduto->has($variacao->produto_id) || ! $variacao->disponivelParaVenda(),
                422,
                "Escolha inválida para o kit \"{$kit->nome}\"."
            );

            $pai = $componentesEscolha[$variacao->produto_id]->produto;
            $nomeProduto = $pai->nome;

            // vinculada: baixa o estoque do produto real e a composição registra esse produto
            $variacao->baixar($quantidade, "{$nomeProduto} ({$variacao->tamanho})");
            $vinculada = $variacao->produto_vinculado_id !== null;
            $produtoDaLinha = $variacao->produtoParaFaturar($pai);

            $chaveGrupo = $grupoDoProduto[$variacao->produto_id];
            $totalPorGrupo[$chaveGrupo] = ($totalPorGrupo[$chaveGrupo] ?? 0) + $quantidade;

            $composicao[] = [
                'produto_id' => $produtoDaLinha->id,
                'nome' => $produtoDaLinha->nome,
                'variacao_id' => $variacao->id,
                'tamanho' => $vinculada ? null : $variacao->tamanho,
                'quantidade' => $quantidade,
            ];
        }

        if ($kit->kit_total_escolhas !== null) {
            $esperadoTotal = $kit->kit_total_escolhas * $quantidadeKits;

            abort_if(
                array_sum($totalPorGrupo) !== $esperadoTotal,
                422,
                "Escolha exatamente {$esperadoTotal} item(ns) para o kit \"{$kit->nome}\"."
            );

            foreach ($grupos as $chave => $componentes) {
                $maximo = $this->quantidadeDoGrupo($componentes) * $quantidadeKits;

                abort_if(
                    ($totalPorGrupo[$chave] ?? 0) > $maximo,
                    422,
                    "O kit \"{$kit->nome}\" aceita no máximo {$maximo} unidade(s) de {$this->nomeDoGrupo($componentes)}."
                );
            }

            return $composicao;
        }

        foreach ($grupos as $chave => $componentes) {
            $esperado = $this->quantidadeDoGrupo($componentes) * $quantidadeKits;

            abort_if(
                ($totalPorGrupo[$chave] ?? 0) !== $esperado,
                422,
                "Escolha exatamente {$esperado} unidade(s) de {$this->nomeDoGrupo($componentes)} para o kit \"{$kit->nome}\"."
            );
        }

        return $composicao;
    }

    /**
     * Desfaz a baixa de `consumir()` (cancelamento de venda): devolve ao estoque
     * cada componente gravado na composição do item. Variação própria volta na
     * variação; variação vinculada e item fixo voltam no produto da linha.
     *
     * @param  array<int, array{produto_id: int, variacao_id?: int|null, tamanho?: string|null, quantidade: int}>  $composicao
     */
    public function devolver(array $composicao): void
    {
        foreach ($composicao as $componente) {
            $quantidade = (int) $componente['quantidade'];

            if (! empty($componente['variacao_id']) && ! empty($componente['tamanho'])) {
                ProdutoVariacao::query()->whereKey($componente['variacao_id'])->increment('estoque_atual', $quantidade);

                continue;
            }

            $produto = Produto::query()->lockForUpdate()->find($componente['produto_id']);

            if ($produto !== null && $produto->estoque_atual !== null) {
                $produto->increment('estoque_atual', $quantidade);
            }
        }
    }

    /**
     * Grupos de escolha do kit, na ordem de cadastro: itens com o mesmo
     * `grupo` ficam juntos; sem grupo, cada produto é o seu próprio grupo.
     *
     * @return Collection<string, Collection<int, KitComponente>>
     */
    private function grupos(Produto $kit): Collection
    {
        $kit->loadMissing('componentes.produto');

        return $kit->componentes->where('tipo', 'escolha')->groupBy(
            fn (KitComponente $c) => filled($c->grupo) ? 'g:'.mb_strtolower(trim($c->grupo)) : 'p:'.$c->produto_id
        );
    }

    private function quantidadeDoGrupo(Collection $componentes): int
    {
        return (int) $componentes->max('quantidade');
    }

    private function nomeDoGrupo(Collection $componentes): string
    {
        $primeiro = $componentes->first();

        return filled($primeiro->grupo) ? trim($primeiro->grupo) : $primeiro->produto->nome;
    }
}
