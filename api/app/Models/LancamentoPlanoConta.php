<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lançamento no plano de contas gerado automaticamente por uma venda (ver
 * LancamentoVendaService) ou por uma compra/entrada de nota (ver LancamentoCompraService).
 */
#[Fillable(['empresa_id', 'venda_id', 'compra_id', 'plano_conta_id', 'origem', 'valor', 'data', 'historico'])]
class LancamentoPlanoConta extends Model
{
    protected $table = 'lancamentos_plano_contas';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data' => 'date',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function planoContas(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }
}
