<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Produto;
use Illuminate\Http\Request;

/**
 * Catálogo público de uma empresa: produtos físicos e horários de
 * agendamento em aberto. Não exige login (ver Escopo v2, seção 2.1).
 * O isolamento por empresa já vem garantido pelo RLS + SetTenantContext,
 * então as queries aqui não precisam (e não devem) filtrar por empresa_id
 * manualmente.
 */
class CatalogoController extends Controller
{
    public function produtos(string $empresa)
    {
        return Produto::query()
            ->where('tipo', 'fisico')
            ->orderBy('nome')
            ->get();
    }

    public function agenda(Request $request, string $empresa)
    {
        $data = $request->validate([
            'produto_id' => ['nullable', 'integer'],
            'data' => ['nullable', 'date'],
        ]);

        return AgendaVisitacao::query()
            ->where('status', 'aberta')
            ->when($data['produto_id'] ?? null, fn ($q, $produtoId) => $q->where('produto_id', $produtoId))
            ->when($data['data'] ?? null, fn ($q, $dia) => $q->whereDate('data_hora', $dia))
            ->where('data_hora', '>=', now())
            ->orderBy('data_hora')
            ->get()
            ->map(fn (AgendaVisitacao $agenda) => [
                'id' => $agenda->id,
                'data_hora' => $agenda->data_hora,
                'vagas_disponiveis' => $agenda->vagasDisponiveis(),
                'valor_visita' => $agenda->valor_visita,
            ]);
    }
}
