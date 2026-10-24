<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'cliente_id', 'venda_id', 'historico', 'plano_conta_id', 'banco_id', 'valor', 'vencimento', 'status', 'recebido_em'])]
class ContaReceber extends Model
{
    protected $table = 'contas_receber';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'recebido_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function planoContas(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(GravaBanco::class, 'conta_receber_id');
    }
}
