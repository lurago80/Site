<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * senha_criptografada usa o cast nativo 'encrypted' do Laravel (APP_KEY) -
 * fica cifrada em repouso no banco e só é decifrada em memória quando o
 * model é lido, nunca aparece em texto puro em dump/backup do banco.
 */
#[Fillable(['empresa_id', 'tipo', 'arquivo_referencia', 'senha_criptografada', 'validade'])]
#[Hidden(['senha_criptografada'])]
class CertificadoDigital extends Model
{
    protected $table = 'certificados_digitais';

    protected function casts(): array
    {
        return [
            'validade' => 'date',
            'senha_criptografada' => 'encrypted',
        ];
    }
}
