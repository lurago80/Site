<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'cliente_id', 'valor', 'vencimento', 'status'])]
class ContaReceber extends Model
{
    protected $table = 'contas_receber';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
        ];
    }
}
