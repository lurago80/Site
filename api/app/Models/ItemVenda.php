<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'venda_id', 'produto_id', 'agenda_visitacao_id',
    'quantidade', 'valor_unitario', 'valor_total', 'comissao_percentual', 'comissao_valor',
])]
class ItemVenda extends Model
{
    protected $table = 'itens_venda';

    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function agendaVisitacao(): BelongsTo
    {
        return $this->belongsTo(AgendaVisitacao::class);
    }
}
