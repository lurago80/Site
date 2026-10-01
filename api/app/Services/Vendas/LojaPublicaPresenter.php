<?php

namespace App\Services\Vendas;

use App\Models\Produto;
use App\Models\Venda;

/**
 * Monta as respostas da loja pública (sem login) com uma lista fechada de
 * campos. Nunca devolva o model inteiro aqui: produto carrega preço de custo
 * e dados fiscais, e cliente carrega CPF, RG e endereço.
 */
class LojaPublicaPresenter
{
    /**
     * @param  array<string, mixed>|null  $kit  composição já montada pelo KitService::resumo()
     */
    public static function produto(Produto $produto, ?array $kit = null): array
    {
        $dados = [
            'id' => $produto->id,
            'nome' => $produto->nome,
            'descricao' => $produto->descricao,
            'preco_venda' => $produto->preco_venda,
            'estoque_atual' => $produto->estoque_atual,
            'imagem_url' => $produto->imagem_url,
            'quantidade_minima_venda' => $produto->quantidade_minima_venda,
            'eh_kit' => $produto->eh_kit,
            'variacoes' => $produto->variacoes->map(fn ($v) => [
                'id' => $v->id,
                'tamanho' => $v->tamanho,
                'estoque_atual' => $v->estoque_atual,
                'ativo' => $v->ativo,
            ])->values()->all(),
        ];

        if ($kit !== null) {
            $dados['kit'] = $kit;
        }

        return $dados;
    }

    /**
     * Itens do pedido como o recibo e a confirmação mostram: nome do produto,
     * tipo/tamanho, data da visita e, no kit, o que o cliente escolheu.
     */
    public static function itens(Venda $venda): array
    {
        return $venda->itens->map(fn ($item) => [
            'id' => $item->id,
            'quantidade' => $item->quantidade,
            'valor_unitario' => $item->valor_unitario,
            'valor_total' => $item->valor_total,
            'composicao' => $item->composicao,
            'produto' => $item->produto ? ['nome' => $item->produto->nome] : null,
            'produto_variacao' => $item->produtoVariacao ? ['tamanho' => $item->produtoVariacao->tamanho] : null,
            'agenda_visitacao' => $item->agendaVisitacao ? ['data_hora' => $item->agendaVisitacao->data_hora] : null,
        ])->values()->all();
    }
}
