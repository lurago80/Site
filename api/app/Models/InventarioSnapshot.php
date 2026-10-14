<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'data', 'produto_id', 'produto_variacao_id', 'nome', 'tamanho', 'estoque'])]
class InventarioSnapshot extends Model
{
    protected $table = 'inventario_snapshots';

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
