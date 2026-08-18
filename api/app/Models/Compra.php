<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'fornecedor_id', 'numero_nota', 'serie_nota', 'chave_acesso',
    'tipo_entrada', 'data_emissao', 'data_entrada', 'valor_produtos', 'valor_frete',
    'valor_desconto', 'valor_total', 'status', 'xml_conteudo',
])]
class Compra extends Model
{
    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'data_entrada' => 'date',
            'valor_produtos' => 'decimal:2',
            'valor_frete' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_total' => 'decimal:2',
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

    public function itens(): HasMany
    {
        return $this->hasMany(ItemCompra::class);
    }

    public function devolucoes(): HasMany
    {
        return $this->hasMany(DocumentoFiscal::class)->where('tipo_operacao', 'devolucao');
    }
}
