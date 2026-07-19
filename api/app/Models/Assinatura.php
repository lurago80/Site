<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'plano_id', 'status_pagamento', 'inicio', 'proxima_cobranca', 'asaas_subscription_id'])]
class Assinatura extends Model
{
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'proxima_cobranca' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }
}
