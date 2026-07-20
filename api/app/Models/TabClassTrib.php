<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabela oficial de Classificação Tributária (cClassTrib) do IBS/CBS -
 * global, igual para todas as empresas (não é multi-tenant, sem RLS -
 * mesma lógica de Plano).
 */
#[Fillable(['codigo', 'descricao', 'anexo_lc214', 'ativo'])]
class TabClassTrib extends Model
{
    protected $table = 'tab_cclasstrib';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
