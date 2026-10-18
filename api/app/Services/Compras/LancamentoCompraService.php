<?php

namespace App\Services\Compras;

use App\Models\Compra;
use App\Models\LancamentoPlanoConta;
use App\Models\PlanoContas;

/**
 * Lança cada compra confirmada (entrada de nota) no plano de contas, na conta
 * 001.018 (entrada de notas), para compor o balanço/balancete junto das demais
 * despesas (001.015 a 001.017, usadas pelas vendas).
 *
 * Idempotente: refazer o lançamento de uma compra substitui o anterior.
 * Cancelar a compra apaga o lançamento (ver estornar()).
 */
class LancamentoCompraService
{
    public const CONTA_ENTRADA_NOTAS = ['codigo' => '001.018', 'nome' => 'ENTRADA DE NOTAS', 'tipo' => 'despesa'];

    public function registrar(Compra $compra): void
    {
        $this->estornar($compra);

        if ($compra->status !== 'confirmada') {
            return;
        }

        $data = $compra->data_entrada?->toDateString() ?? now()->toDateString();

        $plano = PlanoContas::firstOrCreate(
            ['empresa_id' => $compra->empresa_id, 'codigo' => self::CONTA_ENTRADA_NOTAS['codigo']],
            ['nome' => self::CONTA_ENTRADA_NOTAS['nome'], 'tipo' => self::CONTA_ENTRADA_NOTAS['tipo'], 'ativo' => true],
        );

        LancamentoPlanoConta::create([
            'empresa_id' => $compra->empresa_id,
            'compra_id' => $compra->id,
            'plano_conta_id' => $plano->id,
            'origem' => 'entrada_notas',
            'valor' => round((float) $compra->valor_total, 2),
            'data' => $data,
            'historico' => 'Entrada de nota'.($compra->numero_nota ? " - NF {$compra->numero_nota}" : '')." - compra #{$compra->id}",
        ]);
    }

    public function estornar(Compra $compra): void
    {
        LancamentoPlanoConta::where('compra_id', $compra->id)->delete();
    }
}
