<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'empresa_id', 'modelo', 'serie', 'numero_inicial', 'numero_final',
    'justificativa', 'status', 'protocolo', 'motivo',
])]
class NumeracaoInutilizada extends Model
{
    protected $table = 'numeracao_inutilizada';
}
