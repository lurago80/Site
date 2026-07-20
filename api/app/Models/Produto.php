<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'fornecedor_id', 'grupo_id', 'nome', 'codigo', 'descricao', 'categoria', 'tipo', 'unidade',
    'preco_venda', 'preco_custo', 'estoque_atual', 'ativo', 'ncm', 'cfop_padrao',

    // Campos gerais
    'estoque_minimo', 'imagem_url', 'pesavel', 'valor_atacado', 'codigo_barras',
    'peso_liquido', 'peso_bruto', 'tipo_produto_fiscal',

    // Regime antigo - ICMS
    'cfop_interestadual', 'cest', 'cst_origem', 'cst_icms', 'aliquota_icms',
    'fcp_percentual', 'mva_percentual', 'reducao_base_calculo_icms', 'grupo_fiscal',
    'codigo_beneficio_fiscal',

    // Regime antigo - PIS/COFINS
    'cst_pis', 'aliquota_pis', 'cst_cofins', 'aliquota_cofins', 'natureza_receita_pis_cofins',

    // Regime antigo - IPI
    'cst_ipi', 'aliquota_ipi', 'codigo_enquadramento_ipi',

    // Novo regime - IBS/CBS
    'situacao_novo_regime', 'cst_ibs_cbs', 'cclasstrib_id', 'aliquota_ibs', 'aliquota_cbs',
    'reducao_base_calculo_ibs', 'reducao_base_calculo_cbs', 'percentual_credito_ibs',
    'percentual_credito_cbs', 'ccredpres_id',

    // Imposto Seletivo
    'sujeito_imposto_seletivo', 'tipo_imposto_seletivo', 'cclasstrib_is', 'aliquota_is',

    // Destinação e crédito
    'destinacao_tributaria', 'tipo_credito',
])]
class Produto extends Model
{
    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'valor_atacado' => 'decimal:2',
            'peso_liquido' => 'decimal:3',
            'peso_bruto' => 'decimal:3',
            'ativo' => 'boolean',
            'pesavel' => 'boolean',
            'sujeito_imposto_seletivo' => 'boolean',
            'aliquota_icms' => 'decimal:2',
            'fcp_percentual' => 'decimal:2',
            'mva_percentual' => 'decimal:2',
            'reducao_base_calculo_icms' => 'decimal:2',
            'aliquota_pis' => 'decimal:2',
            'aliquota_cofins' => 'decimal:2',
            'aliquota_ipi' => 'decimal:2',
            'aliquota_ibs' => 'decimal:2',
            'aliquota_cbs' => 'decimal:2',
            'reducao_base_calculo_ibs' => 'decimal:2',
            'reducao_base_calculo_cbs' => 'decimal:2',
            'percentual_credito_ibs' => 'decimal:2',
            'percentual_credito_cbs' => 'decimal:2',
            'aliquota_is' => 'decimal:2',
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

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function classTrib(): BelongsTo
    {
        return $this->belongsTo(TabClassTrib::class, 'cclasstrib_id');
    }

    public function creditoPresumido(): BelongsTo
    {
        return $this->belongsTo(TabCredPres::class, 'ccredpres_id');
    }
}
