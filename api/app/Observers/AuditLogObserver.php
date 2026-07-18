<?php

namespace App\Observers;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Grava em `logs` toda criação/alteração/exclusão de um model marcado
 * com App\Models\Concerns\HasAuditLog - reforço obrigatório para dados
 * fiscais, financeiros e de cliente (Escopo v2, seção 4).
 *
 * Usa DB::table (query builder), não Eloquent, para não disparar
 * eventos de model e cair em recursão.
 */
class AuditLogObserver
{
    private const CAMPOS_SENSIVEIS = ['password', 'remember_token', 'senha_criptografada'];

    public function created(Model $model): void
    {
        $this->registrar('create', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->registrar('update', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->registrar('delete', $model, $model->getOriginal(), null);
    }

    private function registrar(string $acao, Model $model, ?array $anteriores, ?array $novos): void
    {
        // Empresa não tem coluna empresa_id (ela É o tenant) - sem este
        // caso especial, o log ficava com empresa_id nulo e violava o
        // RLS de `logs` sempre que um admin comum (não super_admin)
        // editava os dados da própria empresa.
        $empresaId = $model instanceof Empresa ? $model->getKey() : $model->getAttribute('empresa_id');

        DB::table('logs')->insert([
            'empresa_id' => $empresaId,
            'usuario_id' => Auth::id(),
            'acao' => $acao,
            'tabela_afetada' => $model->getTable(),
            'registro_id' => $model->getKey(),
            'dados_anteriores' => $anteriores ? json_encode($this->redigir($anteriores)) : null,
            'dados_novos' => $novos ? json_encode($this->redigir($novos)) : null,
            'data_hora' => now(),
        ]);
    }

    private function redigir(array $dados): array
    {
        foreach (self::CAMPOS_SENSIVEIS as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = '[redigido]';
            }
        }

        return $dados;
    }
}
