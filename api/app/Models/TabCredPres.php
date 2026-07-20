<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabela oficial de Código de Crédito Presumido do IBS/CBS (cCredPres) -
 * global, mesma lógica de TabClassTrib.
 */
#[Fillable(['codigo', 'descricao', 'ativo'])]
class TabCredPres extends Model
{
    protected $table = 'tab_ccredpres';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
