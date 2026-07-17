<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'empresa_id', 'crt', 'inscricao_estadual', 'inscricao_municipal',
    'serie_nfe_atual', 'numero_nfe_atual', 'serie_nfce_atual', 'numero_nfce_atual',
    'csc_nfce', 'id_token_csc', 'ambiente_ativo',
])]
class ConfigFiscal extends Model
{
    protected $table = 'config_fiscal';
}
