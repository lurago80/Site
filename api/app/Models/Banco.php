<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'nome', 'codigo_banco', 'agencia', 'numero_conta', 'tipo_conta', 'saldo_inicial', 'ativo'])]
class Banco extends Model
{
    protected $table = 'bancos';

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(GravaBanco::class);
    }
}
