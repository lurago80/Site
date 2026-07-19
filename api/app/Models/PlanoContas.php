<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'codigo', 'nome', 'tipo', 'ativo'])]
class PlanoContas extends Model
{
    protected $table = 'plano_contas';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
