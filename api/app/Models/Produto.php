<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'fornecedor_id', 'nome', 'descricao', 'tipo', 'preco_venda', 'estoque_atual'])]
class Produto extends Model
{
    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
