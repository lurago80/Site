<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'banco_id', 'conta_pagar_id', 'conta_receber_id', 'data_movimento', 'tipo', 'valor', 'descricao', 'origem'])]
class GravaBanco extends Model
{
    protected $table = 'grava_banco';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_movimento' => 'date',
        ];
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class);
    }

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class);
    }
}
