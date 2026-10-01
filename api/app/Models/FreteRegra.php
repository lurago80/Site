<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'uf', 'valor', 'prazo_dias'])]
class FreteRegra extends Model
{
    protected $table = 'frete_regras';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'prazo_dias' => 'integer',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
