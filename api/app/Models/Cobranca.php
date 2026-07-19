<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'venda_id', 'gateway', 'metodo', 'referencia_externa', 'status',
    'valor', 'qr_code', 'qr_code_base64', 'link_pagamento', 'payload_retorno', 'expira_em',
])]
class Cobranca extends Model
{
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'payload_retorno' => 'array',
            'expira_em' => 'datetime',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }
}
