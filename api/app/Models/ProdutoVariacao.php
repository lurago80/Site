<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'produto_id', 'tamanho', 'estoque_atual', 'ativo'])]
class ProdutoVariacao extends Model
{
    protected $table = 'produto_variacoes';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
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
