<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração ÚNICA e global (não por empresa cliente) - é a
 * plataforma cobrando cada empresa pela mensalidade, não uma empresa
 * cobrando seu cliente final (isso é ConfigPagamento). api_key usa o
 * cast 'encrypted' nativo do Laravel, mesmo padrão das demais
 * credenciais de gateway do sistema.
 */
#[Fillable(['provider', 'ambiente', 'api_key', 'ativo'])]
#[Hidden(['api_key'])]
class ConfigAssinatura extends Model
{
    protected $table = 'config_assinatura';

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'ativo' => 'boolean',
        ];
    }
}
