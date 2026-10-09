<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'nome'])]
class Indicacao extends Model
{
    protected $table = 'indicacoes';
}
