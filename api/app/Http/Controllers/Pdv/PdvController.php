<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Atendente;
use App\Models\DescontoPdv;
use App\Models\FormaPagamento;
use App\Models\Produto;
use App\Models\Vendedor;
use App\Models\Venda;
use App\Services\Pdv\CaixaService;
use App\Services\Pdv\VendaPdvService;
use App\Services\Vendas\QuantidadeMinimaVendaService;
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
        private readonly QuantidadeMinimaVendaService $quantidadeMinimaVendaService,
    ) {}

    public function caixa(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return view('pdv.caixa', [
            'empresaSlug' => $empresa,
            'pdvImpressaoDireta' => $empresaAtual?->pdv_impressao_direta ?? false,
            'logoEmpresaUrl' => $empresaAtual?->logo_url,
        ]);
    }

    // ---- Verificação de ticket/recibo na chegada da visita (check-in) ----

    public function verificarTicket(string $empresa)
    {
        return view('pdv.verificar', ['empresaSlug' => $empresa]);
    }

    public function buscarTicket(Request $request, string $empresa, int $vendaId)
    {
        $venda = Venda::with(['itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente', 'checkInUsuario', 'vendedor', 'atendente'])
            ->find($vendaId);

        abort_if($venda === null, 404, 'Pedido não encontrado nesta empresa.');

        return response()->json($venda);
    }

    public function confirmarCheckIn(Request $request, string $empresa, int $vendaId)
    {
        $dados = $request->validate([
            'vendedor_id' => ['nullable', 'integer'],
            'atendente_id' => ['required', 'integer'],
        ]);

        $venda = Venda::find($vendaId);
        abort_if($venda === null, 404, 'Pedido não encontrado nesta empresa.');

        abort_if($venda->status_pagamento !== 'pago', 422, 'Este pedido ainda não está pago - não é possível confirmar a entrada.');

        if ($venda->check_in_em !== null) {
            abort(422, 'Este ticket já teve entrada confirmada em '.$venda->check_in_em->format('d/m/Y H:i').'.');
        }

        $venda->update([
            'vendedor_id' => $dados['vendedor_id'] ?? $venda->vendedor_id,
            'atendente_id' => $dados['atendente_id'],
            'check_in_em' => now(),
            'check_in_usuario_id' => $request->user()->id,
        ]);

        return response()->json($venda->fresh(['checkInUsuario', 'vendedor', 'atendente']));
    }

    public function produtos(Request $request, string $empresa)
    {
        $busca = $request->query('busca');

        return response()->json(
            Produto::query()
                ->where('tipo', 'fisico')
                ->where('ativo', true)
                ->where('eh_kit', false)
                ->where('somente_loja_virtual', false)
                ->when($busca, fn ($q, $termo) => $q->where('nome', 'ilike', "%{$termo}%"))
                ->with(['variacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tamanho')])
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

    public function descontos(Request $request, string $empresa)
    {
        return response()->json(
            DescontoPdv::where('ativo', true)->orderBy('descricao')->get()
        );
    }

    public function finalizar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'tipo_doc' => ['required', 'in:fiscal,nao_fiscal'],
            'vendedor_id' => ['nullable', 'integer'],
            'atendente_id' => ['required', 'integer'],
            'forma_pagamento_id' => ['required', 'integer'],
            'cliente.nome' => ['nullable', 'string', 'max:255'],
            'cliente.cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'cliente.telefone' => ['nullable', 'string', 'max:20'],
            'itens' => ['nullable', 'array'],
            'itens.*.produto_id' => ['required_with:itens', 'integer'],
            'itens.*.variacao_id' => ['nullable', 'integer'],
            'itens.*.quantidade' => ['required_with:itens', 'integer', 'min:1'],
            'agenda_visitacao_id' => ['nullable', 'integer'],
            'agenda_quantidade' => ['nullable', 'required_with:agenda_visitacao_id', 'integer', 'min:1'],
            'cupom_codigo' => ['nullable', 'string', 'max:40'],
            'desconto_pdv_id' => ['nullable', 'integer'],
        ]);

        abort_if(
            empty($dados['itens']) && empty($dados['agenda_visitacao_id']),
            422,
            'Adicione ao menos um produto ou uma visita à venda.'
        );

        abort_if(
            ! empty($dados['cupom_codigo']) && empty($dados['agenda_visitacao_id']),
            422,
            'O cupom só pode ser aplicado quando há uma visita agendada no carrinho.'
        );

        // Desconto que incide em visitas não combina com cupom (ambos
        // descontam a visita) - regra de negócio explícita do cliente.
        if (! empty($dados['desconto_pdv_id']) && ! empty($dados['cupom_codigo'])) {
            $descontoPdv = DescontoPdv::where('ativo', true)->find($dados['desconto_pdv_id']);

            abort_if(
                $descontoPdv?->aplicaEmVisitas(),
                422,
                'O desconto em visitas não pode ser usado junto com cupom. Escolha um dos dois.'
            );
        }

        $this->quantidadeMinimaVendaService->validar($dados['itens'] ?? []);

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $venda = $this->vendaPdvService->finalizar($empresaAtual, $dados, $request->user()->id);
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
