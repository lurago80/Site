<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'nome', 'cpf_cnpj', 'telefone', 'email', 'endereco',
    'consentimento_lgpd', 'consentimento_lgpd_data', 'consentimento_lgpd_versao',
])]
class Cliente extends Model
{
    protected function casts(): array
    {
        return [
            'consentimento_lgpd' => 'boolean',
            'consentimento_lgpd_data' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
