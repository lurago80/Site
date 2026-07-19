<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * access_token e client_secret usam o cast 'encrypted' nativo do
 * Laravel (mesmo padrão do CertificadoDigital::senha_criptografada) -
 * nunca ficam em texto puro no banco.
 */
#[Fillable(['empresa_id', 'gateway', 'ambiente', 'access_token', 'public_key', 'client_id', 'client_secret', 'ativo'])]
#[Hidden(['access_token', 'client_secret'])]
class ConfigPagamento extends Model
{
    protected $table = 'config_pagamento';

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'client_secret' => 'encrypted',
            'ativo' => 'boolean',
        ];
    }
}
