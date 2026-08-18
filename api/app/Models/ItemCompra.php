<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'compra_id', 'produto_id', 'codigo_produto_fornecedor', 'descricao_xml',
    'quantidade', 'valor_unitario', 'valor_total', 'ncm', 'cfop',
])]
class ItemCompra extends Model
{
    protected $table = 'itens_compra';

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:4',
            'valor_total' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
