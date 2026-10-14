<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'fornecedor_id', 'atendente_id', 'vendedor_id', 'historico', 'plano_conta_id',
    'banco_id', 'forma_pagamento_id', 'valor', 'vencimento', 'status', 'pago_em',
])]
class ContaPagar extends Model
{
    protected $table = 'contas_pagar';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
        ];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Atendente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function planoContas(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }

    /** Nome de quem recebeu, seja fornecedor, atendente ou vendedor. */
    public function beneficiarioNome(): ?string
    {
        return $this->fornecedor?->razao_social ?? $this->atendente?->nome ?? $this->vendedor?->nome;
    }
}
