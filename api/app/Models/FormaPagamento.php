<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'descricao', 'codigo_tpag', 'tipo', 'ativo'])]
class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
