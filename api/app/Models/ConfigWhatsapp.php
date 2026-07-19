<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * token e client_token usam o cast 'encrypted' nativo do Laravel
 * (mesmo padrão do ConfigPagamento) - nunca ficam em texto puro no
 * banco.
 */
#[Fillable(['empresa_id', 'provider', 'instance_id', 'token', 'client_token', 'ativo'])]
#[Hidden(['token', 'client_token'])]
class ConfigWhatsapp extends Model
{
    protected $table = 'config_whatsapp';

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'client_token' => 'encrypted',
            'ativo' => 'boolean',
        ];
    }
}
