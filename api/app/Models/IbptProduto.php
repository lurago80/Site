<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Alíquotas aproximadas de tributos por NCM (Lei da Transparência
 * Fiscal, 12.741/2012) - tabela global oficial do IBPT, sem
 * empresa_id/RLS.
 */
#[Fillable([
    'codigo', 'ex', 'tipo', 'descricao',
    'aliquota_nacional_federal', 'aliquota_importados_federal', 'aliquota_estadual', 'aliquota_municipal',
    'vigencia_inicio', 'vigencia_fim', 'chave', 'versao', 'fonte',
])]
class IbptProduto extends Model
{
    protected $table = 'ibpt_produtos';

    protected function casts(): array
    {
        return [
            'aliquota_nacional_federal' => 'decimal:2',
            'aliquota_importados_federal' => 'decimal:2',
            'aliquota_estadual' => 'decimal:2',
            'aliquota_municipal' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }
}
