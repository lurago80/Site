<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Atendente;
use App\Models\FormaPagamento;
use App\Models\Produto;
use App\Models\Vendedor;
use App\Services\Pdv\CaixaService;
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
    public function __construct(
        private readonly VendaPdvService $vendaPdvService,
        private readonly CaixaService $caixaService,
    ) {}

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

    public function atendentes(Request $request, string $empresa)
    {
        return response()->json(
            Atendente::where('ativo', true)->orderBy('nome')->get()
        );
    }

    public function formasPagamento(Request $request, string $empresa)
    {
        return response()->json(
            FormaPagamento::where('ativo', true)->orderBy('descricao')->get()
        );
    }

    public function finalizar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'tipo_doc' => ['required', 'in:fiscal,nao_fiscal'],
            'vendedor_id' => ['nullable', 'integer'],
            'atendente_id' => ['nullable', 'integer'],
            'forma_pagamento_id' => ['nullable', 'integer'],
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

    // ---- Controle de caixa (abertura, fechamento, sangria, suprimento) ----

    public function caixaStatus(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json($this->caixaService->statusAtual($empresaAtual->id));
    }

    public function caixaAbrir(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $caixa = $this->caixaService->abrir($empresaAtual, $request->user()->id, $dados['valor'], $dados['observacao'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($caixa, 201);
    }

    public function caixaFechar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $caixa = $this->caixaService->fechar($empresaAtual, $request->user()->id, $dados['valor'], $dados['observacao'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($caixa, 201);
    }

    public function caixaSangria(Request $request, string $empresa)
    {
        return $this->caixaMovimento($request, 'sangria');
    }

    public function caixaSuprimento(Request $request, string $empresa)
    {
        return $this->caixaMovimento($request, 'suprimento');
    }

    private function caixaMovimento(Request $request, string $tipo)
    {
        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0.01'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $caixa = $this->caixaService->registrarMovimento(
                $empresaAtual, $request->user()->id, $tipo, $dados['valor'], $dados['observacao'] ?? null
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($caixa, 201);
    }

    public function caixaExtrato(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            \App\Models\Caixa::where('empresa_id', $empresaAtual->id)
                ->with('usuario:id,name')
                ->orderByDesc('data_hora')
                ->limit(200)
                ->get()
        );
    }
}
