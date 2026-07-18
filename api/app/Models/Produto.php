<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'fornecedor_id', 'nome', 'codigo', 'descricao', 'categoria', 'tipo', 'unidade',
    'preco_venda', 'preco_custo', 'estoque_atual', 'ativo', 'ncm', 'cfop_padrao',
])]
class Produto extends Model
{
    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
