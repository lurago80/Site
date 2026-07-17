<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'tipo', 'arquivo_referencia', 'senha_criptografada', 'validade'])]
class CertificadoDigital extends Model
{
    protected $table = 'certificados_digitais';

    protected function casts(): array
    {
        return [
            'validade' => 'date',
        ];
    }
}
