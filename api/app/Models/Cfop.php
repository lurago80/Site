<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Código Fiscal de Operações e Prestações (Ajuste SINIEF s/nº de
 * 15/12/1970) - tabela global oficial, sem empresa_id/RLS (mesma
 * lógica de ibpt_produtos/tab_cclasstrib/tab_ccredpres).
 */
#[Fillable(['codigo', 'descricao', 'ativo'])]
class Cfop extends Model
{
    protected $table = 'cfops';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
