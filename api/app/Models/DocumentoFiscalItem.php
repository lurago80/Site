<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'documento_fiscal_id', 'item_venda_id', 'produto_id',
    'ncm', 'cfop', 'cst_csosn', 'quantidade', 'valor_unitario', 'valor_total',
    'base_calculo_icms', 'aliquota_icms', 'valor_icms', 'valor_pis', 'valor_cofins',
])]
class DocumentoFiscalItem extends Model
{
    protected $table = 'documento_fiscal_itens';

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'base_calculo_icms' => 'decimal:2',
            'aliquota_icms' => 'decimal:2',
            'valor_icms' => 'decimal:2',
            'valor_pis' => 'decimal:2',
            'valor_cofins' => 'decimal:2',
        ];
    }

    public function documentoFiscal(): BelongsTo
    {
        return $this->belongsTo(DocumentoFiscal::class);
    }
}
