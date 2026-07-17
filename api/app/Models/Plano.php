<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'valor_mensal', 'limites'])]
class Plano extends Model
{
    protected function casts(): array
    {
        return [
            'limites' => 'array',
        ];
    }
}
