<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'usuario_id', 'nome', 'telefone',
    'comissao_produtos_ativa', 'percentual_comissao_produtos',
    'comissao_visitas_ativa', 'percentual_comissao_visitas',
    'ativo',
])]
class Atendente extends Model
{
    protected $table = 'atendentes';

    protected function casts(): array
    {
        return [
            'percentual_comissao_produtos' => 'decimal:2',
            'percentual_comissao_visitas' => 'decimal:2',
            'comissao_produtos_ativa' => 'boolean',
            'comissao_visitas_ativa' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
