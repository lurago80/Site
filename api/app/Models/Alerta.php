<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aviso ao administrador/gerente sobre uma operação fora do normal (ver AlertaService). */
#[Fillable(['empresa_id', 'tipo', 'mensagem', 'valor', 'venda_id', 'usuario_id', 'lida_em'])]
class Alerta extends Model
{
    protected $table = 'alertas';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'lida_em' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
