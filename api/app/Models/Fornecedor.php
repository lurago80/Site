<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['empresa_id', 'razao_social', 'cnpj', 'contato'])]
class Fornecedor extends Model
{
    //
}
