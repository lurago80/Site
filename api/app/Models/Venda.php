<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'empresa_id', 'cliente_id', 'vendedor_id', 'atendente_id', 'forma_pagamento_id',
    'canal', 'tipo_doc', 'status_pagamento', 'valor_total', 'comissao', 'data_venda',
    'cupom_id', 'desconto_pdv_id', 'valor_desconto', 'check_in_em', 'check_in_usuario_id',
    'tipo_entrega', 'valor_frete', 'endereco_entrega', 'status_envio', 'codigo_rastreio',
    'cancelada_em', 'cancelada_por_usuario_id', 'motivo_cancelamento', 'indicacao_id',
    'cortesia', 'motivo_desconto',
])]
class Venda extends Model
{
    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'comissao' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_frete' => 'decimal:2',
            'cortesia' => 'boolean',
            'endereco_entrega' => 'array',
            'data_venda' => 'datetime',
            'check_in_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por_usuario_id');
    }

    /** Venda paga com a forma de pagamento "CONSUMO INTERNO": a empresa usou o produto, não vendeu. */
    public function ehConsumoInterno(): bool
    {
        return mb_strtoupper(trim((string) $this->formaPagamento?->descricao)) === 'CONSUMO INTERNO';
    }

    /** Consumo interno ou cortesia: aparece nos relatórios, mas não é receita nem entra nos totais de vendas. */
    public function semReceita(): bool
    {
        return $this->cortesia || $this->ehConsumoInterno();
    }

    /** 'Cortesia', 'Consumo interno' ou null para venda normal. */
    public function tipoSemReceita(): ?string
    {
        return $this->cortesia ? 'Cortesia' : ($this->ehConsumoInterno() ? 'Consumo interno' : null);
    }

    public function descontoPdv(): BelongsTo
    {
        return $this->belongsTo(DescontoPdv::class);
    }

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function indicacao(): BelongsTo
    {
        return $this->belongsTo(Indicacao::class);
    }

    public function checkInUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'check_in_usuario_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Atendente::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }

    public function lancamentosPlanoContas(): HasMany
    {
        return $this->hasMany(LancamentoPlanoConta::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemVenda::class);
    }

    public function documentoFiscal(): HasOne
    {
        return $this->hasOne(DocumentoFiscal::class)
            ->whereIn('tipo_operacao', ['venda', 'regularizacao_nfce'])
            ->latest('id');
    }

    /** Última NFe (modelo 55) de venda emitida para este pedido. */
    public function nfe(): HasOne
    {
        return $this->hasOne(DocumentoFiscal::class)
            ->where('modelo', 55)
            ->where('tipo_operacao', 'venda')
            ->latest('id');
    }

    public function devolucoes(): HasMany
    {
        return $this->hasMany(DocumentoFiscal::class)->where('tipo_operacao', 'devolucao');
    }
}
