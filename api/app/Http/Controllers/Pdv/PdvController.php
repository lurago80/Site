<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Produto;
use App\Models\Vendedor;
use App\Services\Pdv\VendaPdvService;
use Illuminate\Http\Request;

/**
 * PDV (frente de caixa) - Escopo v2, seção 2.2: vendas fiscais e não
 * fiscais, incluindo venda de experiências agendadas com comissão por
 * vendedor. O tenant vem sempre do usuário autenticado (ver
 * App\Http\Middleware\SetTenantContext).
 */
class PdvController extends Controller
{
    public function __construct(private readonly VendaPdvService $vendaPdvService) {}

    public function caixa(string $empresa)
    {
        return view('pdv.caixa', ['empresaSlug' => $empresa]);
    }

    public function produtos(Request $request, string $empresa)
    {
        $busca = $request->query('busca');

        return response()->json(
            Produto::query()
                ->where('tipo', 'fisico')
                ->when($busca, fn ($q, $termo) => $q->where('nome', 'ilike', "%{$termo}%"))
                ->orderBy('nome')
                ->get()
        );
    }

    public function agenda(Request $request, string $empresa)
    {
        return response()->json(
            AgendaVisitacao::query()
                ->where('status', 'aberta')
                ->where('data_hora', '>=', now())
                ->orderBy('data_hora')
                ->get()
                ->map(fn (AgendaVisitacao $agenda) => [
                    'id' => $agenda->id,
                    'data_hora' => $agenda->data_hora,
                    'vagas_disponiveis' => $agenda->vagasDisponiveis(),
                    'valor_visita' => $agenda->valor_visita,
                ])
        );
    }

    public function vendedores(Request $request, string $empresa)
    {
        return response()->json(
            Vendedor::where('ativo', true)->orderBy('nome')->get()
        );
    }

    public function finalizar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'tipo_doc' => ['required', 'in:fiscal,nao_fiscal'],
            'vendedor_id' => ['nullable', 'integer'],
            'cliente.nome' => ['nullable', 'string', 'max:255'],
            'cliente.cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'cliente.telefone' => ['nullable', 'string', 'max:20'],
            'itens' => ['nullable', 'array'],
            'itens.*.produto_id' => ['required_with:itens', 'integer'],
            'itens.*.quantidade' => ['required_with:itens', 'integer', 'min:1'],
            'agenda_visitacao_id' => ['nullable', 'integer'],
            'agenda_quantidade' => ['required_with:agenda_visitacao_id', 'integer', 'min:1'],
        ]);

        abort_if(
            empty($dados['itens']) && empty($dados['agenda_visitacao_id']),
            422,
            'Adicione ao menos um produto ou uma visita à venda.'
        );

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $venda = $this->vendaPdvService->finalizar($empresaAtual, $dados);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($venda, 201);
    }
}
