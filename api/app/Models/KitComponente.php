<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'kit_id', 'produto_id', 'tipo', 'quantidade'])]
class KitComponente extends Model
{
    protected $table = 'kit_componentes';

    protected function casts(): array
    {
        return ['quantidade' => 'integer'];
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'kit_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
