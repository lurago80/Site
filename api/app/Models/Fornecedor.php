<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'empresa_id', 'razao_social', 'nome_fantasia', 'cnpj', 'contato',
    'telefone', 'email', 'endereco', 'inscricao_estadual',
])]
class Fornecedor extends Model
{
    protected $table = 'fornecedores';
}
