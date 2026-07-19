<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'usuario_id', 'tipo', 'valor', 'data_hora', 'observacao'])]
class Caixa extends Model
{
    protected $table = 'caixas';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_hora' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
