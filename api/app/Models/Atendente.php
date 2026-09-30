<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'usuario_id', 'nome', 'telefone', 'percentual_comissao', 'ativo'])]
class Atendente extends Model
{
    protected $table = 'atendentes';

    protected function casts(): array
    {
        return [
            'percentual_comissao' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
