<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'venda_id', 'forma_pagamento_id', 'valor', 'valor_recebido'])]
class VendaPagamento extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_recebido' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }
}
