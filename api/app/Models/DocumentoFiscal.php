<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'venda_id', 'modelo', 'serie', 'numero', 'chave_acesso',
    'ambiente', 'status', 'protocolo_autorizacao', 'natureza_operacao', 'cfop_geral',
    'valor_produtos', 'desconto', 'frete', 'total', 'valor_icms', 'valor_pis', 'valor_cofins',
    'xml_path', 'danfe_path', 'motivo_cancelamento', 'data_cancelamento',
])]
class DocumentoFiscal extends Model
{
    protected $table = 'documentos_fiscais';

    protected function casts(): array
    {
        return [
            'valor_produtos' => 'decimal:2',
            'desconto' => 'decimal:2',
            'frete' => 'decimal:2',
            'total' => 'decimal:2',
            'valor_icms' => 'decimal:2',
            'valor_pis' => 'decimal:2',
            'valor_cofins' => 'decimal:2',
            'data_cancelamento' => 'datetime',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(DocumentoFiscalItem::class);
    }
}
