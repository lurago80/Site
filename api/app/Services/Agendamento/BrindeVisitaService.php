<?php

namespace App\Services\Agendamento;

use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Venda;

/**
 * Baixa o produto-brinde da visita (ex.: caneca de chopp), configurado nos parâmetros
 * da empresa (Empresa::produto_brinde_visita_id). Um por visitante.
 *
 * Momento da baixa é diferente por canal (pedido do cliente):
 * - PDV: na hora da venda (pagamento sempre imediato) - ver VendaPdvService.
 * - Loja online: só na validação do ticket na portaria - ver
 *   PdvController::confirmarCheckIn.
 *
 * Sem produto configurado ou sem controle de estoque nesse produto, não faz nada.
 */
class BrindeVisitaService
{
    public function baixar(Venda $venda, Empresa $empresa, int $quantidade): void
    {
        if (empty($empresa->produto_brinde_visita_id)) {
            return;
        }

        $produto = Produto::query()->lockForUpdate()->find($empresa->produto_brinde_visita_id);
        if ($produto === null || $produto->estoque_atual === null) {
            return;
        }

        abort_if(
            ! $empresa->estoque_permite_negativo && $produto->estoque_atual < $quantidade,
            409,
            "Estoque insuficiente de \"{$produto->nome}\" para a visita."
        );

        $produto->decrement('estoque_atual', $quantidade);

        // Item com valor zero só para rastreio/estorno - o CancelamentoVendaPdvService já
        // sabe devolver estoque de um item de produto comum ao cancelar a venda.
        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
            'valor_unitario' => 0,
            'valor_total' => 0,
        ]);
    }
}
