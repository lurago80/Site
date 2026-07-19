<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'cliente_id', 'venda_id', 'canal', 'tipo', 'telefone',
    'mensagem', 'provider', 'status', 'referencia_externa', 'payload_retorno',
])]
class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected function casts(): array
    {
        return [
            'payload_retorno' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }
}
