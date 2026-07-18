<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'cliente_id', 'vendedor_id', 'forma_pagamento_id',
    'canal', 'tipo_doc', 'status_pagamento', 'valor_total', 'comissao', 'data_venda',
])]
class Venda extends Model
{
    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'comissao' => 'decimal:2',
            'data_venda' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemVenda::class);
    }
}
