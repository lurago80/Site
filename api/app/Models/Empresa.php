<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'razao_social', 'cnpj', 'slug', 'segmento', 'modulo_agendamento_ativo', 'plano_id', 'status',
    'uf', 'municipio', 'codigo_ibge_municipio', 'cep', 'logradouro', 'numero', 'bairro', 'complemento',
])]
class Empresa extends Model
{
    protected function casts(): array
    {
        return [
            'modulo_agendamento_ativo' => 'boolean',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
