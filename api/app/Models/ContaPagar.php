<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'fornecedor_id', 'historico', 'plano_conta_id', 'banco_id', 'valor', 'vencimento', 'status'])]
class ContaPagar extends Model
{
    protected $table = 'contas_pagar';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
        ];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function planoContas(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }
}
