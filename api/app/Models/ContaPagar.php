<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'fornecedor_id', 'valor', 'vencimento', 'status'])]
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
}
