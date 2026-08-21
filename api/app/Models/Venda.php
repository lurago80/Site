<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'empresa_id', 'cliente_id', 'vendedor_id', 'atendente_id', 'forma_pagamento_id',
    'canal', 'tipo_doc', 'status_pagamento', 'valor_total', 'comissao', 'data_venda',
    'cupom_id', 'valor_desconto',
])]
class Venda extends Model
{
    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'comissao' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'data_venda' => 'datetime',
        ];
    }

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class);
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

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Atendente::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemVenda::class);
    }

    public function documentoFiscal(): HasOne
    {
        return $this->hasOne(DocumentoFiscal::class)
            ->whereIn('tipo_operacao', ['venda', 'regularizacao_nfce'])
            ->latest('id');
    }

    public function devolucoes(): HasMany
    {
        return $this->hasMany(DocumentoFiscal::class)->where('tipo_operacao', 'devolucao');
    }
}
