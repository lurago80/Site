<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Atendente;
use App\Models\DescontoPdv;
use App\Models\FormaPagamento;
use App\Models\ItemVenda;
use App\Models\Produto;
use App\Models\Vendedor;
use App\Models\Venda;
use App\Services\Alertas\AlertaService;
use App\Services\Pdv\CaixaService;
use App\Services\Pdv\CancelamentoVendaPdvService;
use App\Services\Pdv\VendaPdvService;
use App\Services\Vendas\KitService;
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
        private readonly CancelamentoVendaPdvService $cancelamentoVendaPdvService,
        private readonly KitService $kitService,
        private readonly AlertaService $alertaService,
    ) {}

    public function caixa(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return view('pdv.caixa', [
            'empresaSlug' => $empresa,
            'podeCancelarVenda' => $this->podeCancelarVendas($request),
            'pdvImpressaoDireta' => $empresaAtual?->pdv_impressao_direta ?? false,
            'logoEmpresaUrl' => $empresaAtual?->logo_url,
        ]);
    }

    /**
     * Lista de contingência (queda de internet): pedidos da loja virtual com
     * visita paga, de hoje em diante, agrupados por horário. O número do
     * pedido é o mesmo usado para validar o ticket na entrada.
     */
    public function visitasPagas(Request $request, string $empresa)
    {
        // Só as visitas de hoje (dia em Brasília; data_hora é guardada em UTC).
        $inicioHoje = now('America/Sao_Paulo')->startOfDay()->utc();
        $fimHoje = now('America/Sao_Paulo')->endOfDay()->utc();

        $itens = ItemVenda::query()
            ->whereNotNull('agenda_visitacao_id')
            ->whereHas('agendaVisitacao', fn ($q) => $q->whereBetween('data_hora', [$inicioHoje, $fimHoje]))
            ->whereHas('venda', fn ($q) => $q->whereIn('canal', ['site', 'pdv'])->where('status_pagamento', 'pago'))
            ->with(['agendaVisitacao', 'venda.cliente', 'venda.formaPagamento'])
            ->get();

        $horarios = $itens
            ->groupBy('agenda_visitacao_id')
            ->map(function ($grupo) {
                $linhas = $grupo->groupBy('venda_id')->map(function ($doVenda) {
                    $venda = $doVenda->first()->venda;

                    return (object) [
                        'venda' => $venda,
                        'tickets' => $doVenda->sum('quantidade'),
                        'valor' => $doVenda->sum(fn ($i) => (float) $i->valor_total),
                    ];
                })->sortBy(fn ($l) => $l->venda->id)->values();

                return (object) [
                    'data_hora' => $grupo->first()->agendaVisitacao->data_hora,
                    'linhas' => $linhas,
                    'tickets' => $linhas->sum('tickets'),
                    'valor' => $linhas->sum('valor'),
                ];
            })
            ->sortBy('data_hora')
            ->values();

        return view('pdv.visitas-pagas', [
            'empresaNome' => $request->attributes->get('empresaAtual')?->nome_fantasia
                ?? $request->attributes->get('empresaAtual')?->razao_social
                ?? $empresa,
            'horarios' => $horarios,
            'geradoEm' => now('America/Sao_Paulo'),
        ]);
    }

    // ---- Verificação de ticket/recibo na chegada da visita (check-in) ----

    public function verificarTicket(string $empresa)
    {
        return view('pdv.verificar', ['empresaSlug' => $empresa]);
    }

    public function buscarTicket(Request $request, string $empresa, int $vendaId)
    {
        $venda = Venda::with(['itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente', 'checkInUsuario', 'vendedor', 'atendente', 'indicacao'])
            ->find($vendaId);

        abort_if($venda === null, 404, 'Pedido não encontrado nesta empresa.');

        return response()->json($venda);
    }

    public function confirmarCheckIn(Request $request, string $empresa, int $vendaId)
    {
        $dados = $request->validate([
            'vendedor_id' => ['nullable', 'integer'],
            'atendente_id' => ['required', 'integer'],
            'indicacao' => ['nullable', 'string', 'max:120'],
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
            'indicacao_id' => $this->resolverIndicacao($request->attributes->get('empresaAtual')->id, $dados['indicacao'] ?? null) ?? $venda->indicacao_id,
            'check_in_em' => now(),
            'check_in_usuario_id' => $request->user()->id,
        ]);

        return response()->json($venda->fresh(['checkInUsuario', 'vendedor', 'atendente', 'indicacao']));
    }

    /** Informa/corrige a indicação de um pedido pago (inclusive depois da entrada confirmada). */
    public function atualizarIndicacao(Request $request, string $empresa, int $vendaId)
    {
        $dados = $request->validate([
            'indicacao' => ['nullable', 'string', 'max:120'],
        ]);

        $venda = Venda::find($vendaId);
        abort_if($venda === null, 404, 'Pedido não encontrado nesta empresa.');
        abort_if($venda->status_pagamento !== 'pago', 422, 'Só é possível informar a indicação de um pedido pago.');

        // Texto vazio remove a indicação do pedido.
        $venda->update([
            'indicacao_id' => $this->resolverIndicacao($request->attributes->get('empresaAtual')->id, $dados['indicacao'] ?? null),
        ]);

        return response()->json($venda->fresh(['itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente', 'checkInUsuario', 'vendedor', 'atendente', 'indicacao']));
    }

    /** Indicações já cadastradas (para a busca/sugestão no check-in). */
    public function indicacoes(Request $request, string $empresa)
    {
        return response()->json(
            \App\Models\Indicacao::query()
                ->when($request->query('busca'), fn ($q, $t) => $q->where('nome', 'ilike', '%'.$this->normalizarIndicacao($t).'%'))
                ->orderBy('nome')
                ->limit(500)
                ->get(['id', 'nome'])
        );
    }

    /** Padrão do cadastro: MAIÚSCULAS, sem acentos e sem espaços repetidos (facilita a busca). */
    private function normalizarIndicacao(?string $nome): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', \Illuminate\Support\Str::ascii((string) $nome))));
    }

    /** Reaproveita a indicação já cadastrada (sem diferenciar maiúsculas) ou cadastra uma nova. */
    private function resolverIndicacao(int $empresaId, ?string $nome): ?int
    {
        $nome = $this->normalizarIndicacao($nome);
        if ($nome === '') {
            return null;
        }

        $existente = \App\Models\Indicacao::where('empresa_id', $empresaId)
            ->whereRaw('lower(nome) = ?', [mb_strtolower($nome)])
            ->first();

        return ($existente ?? \App\Models\Indicacao::create(['empresa_id' => $empresaId, 'nome' => $nome]))->id;
    }

    public function produtos(Request $request, string $empresa)
    {
        $busca = $request->query('busca');

        return response()->json(
            Produto::query()
                ->where(fn ($q) => $q->where('tipo', 'fisico')->orWhere('eh_kit', true))
                ->where('ativo', true)
                ->where('somente_loja_virtual', false)
                ->when($busca, fn ($q, $termo) => $q->where('nome', 'ilike', "%{$termo}%"))
                ->with(['variacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tamanho')])
                ->orderBy('nome')
                ->get()
                // Kit: manda a composição (itens fixos + grupos de escolha) para o caixa montar o kit.
                ->map(fn (Produto $produto) => $produto->eh_kit
                    ? array_merge($produto->toArray(), ['kit' => $this->kitService->resumo($produto)])
                    : $produto)
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
            // o operador do caixa não precisa (nem deve) ver a chave PIX do vendedor
            Vendedor::where('ativo', true)->orderBy('nome')->get()->each->makeHidden('chave_pix')
        );
    }

    /**
     * Cadastro rápido de vendedor pelo caixa (o atendente não acessa a
     * retaguarda). Comissão fixa em 5%; ajustes ficam com o administrador.
     */
    public function criarVendedor(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'chave_pix' => ['required', 'string', 'max:77'],
        ]);

        $vendedor = Vendedor::create([
            'empresa_id' => $request->attributes->get('empresaAtual')->id,
            'nome' => trim($dados['nome']),
            'telefone' => trim($dados['telefone']),
            'chave_pix' => trim($dados['chave_pix']),
            'percentual_comissao' => 5,
            'ativo' => true,
        ]);

        return response()->json($vendedor->makeHidden('chave_pix'), 201);
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
            // Cortesia não tem cobrança: não precisa de forma de pagamento.
            'forma_pagamento_id' => [\Illuminate\Validation\Rule::requiredIf(fn () => ! $request->boolean('cortesia')), 'nullable', 'integer'],
            'cliente.nome' => ['nullable', 'string', 'max:255'],
            'cliente.cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'cliente.telefone' => ['nullable', 'string', 'max:20'],
            'itens' => ['nullable', 'array'],
            'itens.*.produto_id' => ['required_with:itens', 'integer'],
            'itens.*.variacao_id' => ['nullable', 'integer'],
            'itens.*.escolhas' => ['nullable', 'array'],
            'itens.*.escolhas.*.variacao_id' => ['required_without:itens.*.escolhas.*.produto_id', 'nullable', 'integer'],
            'itens.*.escolhas.*.produto_id' => ['nullable', 'integer'],
            'itens.*.escolhas.*.quantidade' => ['required', 'integer', 'min:1'],
            'itens.*.quantidade' => ['required_with:itens', 'integer', 'min:1'],
            'agenda_visitacao_id' => ['nullable', 'integer'],
            'agenda_quantidade' => ['nullable', 'required_with:agenda_visitacao_id', 'integer', 'min:1'],
            'cupom_codigo' => ['nullable', 'string', 'max:40'],
            'desconto_pdv_id' => ['nullable', 'integer'],
            'cortesia' => ['nullable', 'boolean'],
            'motivo_desconto' => ['nullable', 'string', 'max:255'],
        ]);

        // Cortesia = desconto de 100%: não combina com outro desconto, cupom nem nota fiscal
        // (NFC-e não aceita valor zero) e exige o motivo, como todo desconto manual do PDV.
        $cortesia = (bool) ($dados['cortesia'] ?? false);
        $dados['motivo_desconto'] = trim((string) ($dados['motivo_desconto'] ?? ''));

        abort_if($cortesia && ! empty($dados['desconto_pdv_id']), 422, 'Cortesia não combina com outro desconto. Escolha um dos dois.');
        abort_if($cortesia && ! empty($dados['cupom_codigo']), 422, 'Cortesia não combina com cupom. Escolha um dos dois.');
        abort_if($cortesia && $dados['tipo_doc'] === 'fiscal', 422, 'Cortesia não pode ter nota fiscal (valor zero). Use o documento não fiscal.');
        abort_if(
            ($cortesia || ! empty($dados['desconto_pdv_id'])) && $dados['motivo_desconto'] === '',
            422,
            'Informe o motivo do desconto.'
        );

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

    /** Venda do PDV (hoje) com os dados completos para reimprimir o cupom. */
    public function mostrarVenda(Request $request, string $empresa, int $vendaId)
    {
        abort_unless($this->podeCancelarVendas($request), 403, 'Seu usuário não tem permissão para reimprimir vendas.');

        $venda = Venda::query()
            ->where('empresa_id', $request->attributes->get('empresaAtual')->id)
            ->where('canal', 'pdv')
            ->with([
                'itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente', 'vendedor',
                'atendente', 'formaPagamento', 'descontoPdv', 'empresa', 'documentoFiscal',
            ])
            ->findOrFail($vendaId);

        return response()->json($venda);
    }

    // ---- Cancelamento de venda (administrador; perfil Caixa só se o parâmetro da empresa liberar) ----

    /** Administrador e gerente sempre; Caixa quando o administrador ligou "pdv_caixa_pode_cancelar" nos parâmetros. */
    private function podeCancelarVendas(Request $request): bool
    {
        $perfil = $request->user()->perfil;

        if (in_array($perfil, ['admin', 'gerente'], true)) {
            return true;
        }

        return $perfil === 'caixa' && (bool) $request->attributes->get('empresaAtual')?->pdv_caixa_pode_cancelar;
    }

    /** Vendas do PDV de hoje (a mais recente primeiro), com o que é preciso para decidir o cancelamento. */
    public function vendasDoDia(Request $request, string $empresa)
    {
        abort_unless($this->podeCancelarVendas($request), 403, 'Seu usuário não tem permissão para cancelar vendas.');

        $vendas = Venda::query()
            ->where('empresa_id', $request->attributes->get('empresaAtual')->id)
            ->where('canal', 'pdv')
            ->where('data_venda', '>=', now('America/Sao_Paulo')->startOfDay()->utc())
            ->with(['formaPagamento:id,descricao,tipo', 'documentoFiscal', 'canceladaPor:id,name', 'itens.produto:id,nome', 'itens.agendaVisitacao:id,data_hora'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json($vendas->map(fn (Venda $v) => [
            'id' => $v->id,
            'data_venda' => $v->data_venda,
            'valor_total' => $v->valor_total,
            'forma_pagamento' => $v->formaPagamento?->descricao,
            'itens' => $v->itens->map(fn (ItemVenda $i) => ($i->produto?->nome ?? 'Visita agendada').' x'.(int) $i->quantidade)->values(),
            'documento' => $v->documentoFiscal ? [
                'modelo' => $v->documentoFiscal->modelo,
                'numero' => $v->documentoFiscal->numero,
                'status' => $v->documentoFiscal->status,
            ] : null,
            'cancelada' => $v->status_pagamento === 'cancelado',
            'cancelada_em' => $v->cancelada_em,
            'cancelada_por' => $v->canceladaPor?->name,
            'motivo_cancelamento' => $v->motivo_cancelamento,
        ]));
    }

    public function cancelarVenda(Request $request, string $empresa, int $vendaId)
    {
        abort_unless($this->podeCancelarVendas($request), 403, 'Seu usuário não tem permissão para cancelar vendas.');

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $venda = Venda::query()
            ->where('empresa_id', $request->attributes->get('empresaAtual')->id)
            ->findOrFail($vendaId);

        try {
            $venda = $this->cancelamentoVendaPdvService->cancelar($venda, $request->user()->id, $dados['motivo']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->alertaService->vendaCancelada($venda, $request->user()->name, $dados['motivo']);

        return response()->json($venda);
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

    /** Conferência do fechamento: o que a gaveta deve ter agora (abertura + dinheiro + suprimentos - sangrias). */
    public function caixaResumo(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        abort_if($this->caixaService->statusAtual($empresaAtual->id)['status'] !== 'aberto', 422, 'Não há caixa aberto.');

        $resumo = $this->caixaService->resumoTurno($empresaAtual->id);

        return response()->json([
            'aberto_em' => $resumo['abertura']->data_hora,
            'abertura' => (float) $resumo['abertura']->valor,
            'vendas_dinheiro' => $resumo['vendas_dinheiro'],
            'suprimentos' => $resumo['suprimentos'],
            'sangrias' => $resumo['sangrias'],
            'saldo_esperado' => $resumo['saldo_esperado'],
            'total_vendas' => $resumo['total_vendas'],
            'por_especie' => $resumo['por_especie'],
        ]);
    }

    public function caixaFechar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        // Diferença entre o dinheiro contado e o esperado exige o motivo (fica gravado no fechamento).
        $resumo = $this->caixaService->statusAtual($empresaAtual->id)['status'] === 'aberto'
            ? $this->caixaService->resumoTurno($empresaAtual->id)
            : null;

        if ($resumo !== null && abs((float) $dados['valor'] - $resumo['saldo_esperado']) > 0.009 && trim((string) ($dados['observacao'] ?? '')) === '') {
            return response()->json([
                'message' => 'O valor contado difere do esperado (R$ '.number_format($resumo['saldo_esperado'], 2, ',', '.').'). Informe o motivo da diferença.',
            ], 422);
        }

        try {
            $caixa = $this->caixaService->fechar($empresaAtual, $request->user()->id, $dados['valor'], $dados['observacao'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($resumo !== null) {
            $this->alertaService->diferencaDeCaixa($empresaAtual, (float) $dados['valor'] - $resumo['saldo_esperado'], $request->user()->name);
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

        if ($tipo === 'sangria') {
            $this->alertaService->sangria($empresaAtual, (float) $dados['valor'], $request->user()->name, $dados['observacao'] ?? null);
        }

        return response()->json($caixa, 201);
    }

    /** Extrato imprimível do turno de caixa (o último, ou o da abertura `?abertura=ID`). */
    public function caixaExtratoImpressao(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $aberturaId = $request->integer('abertura') ?: null;
        $data = $request->input('data');
        $turnosDoDia = collect();

        // Escolha de um único dia (dia de Brasília; data_hora é guardada em UTC): lista os turnos
        // abertos nessa data e, sem `abertura` explícita, mostra o último deles.
        if ($data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $inicio = \Illuminate\Support\Carbon::parse($data, 'America/Sao_Paulo')->startOfDay()->utc();
            $fim = $inicio->copy()->addDay();
            $turnosDoDia = \App\Models\Caixa::where('empresa_id', $empresaAtual->id)
                ->where('tipo', 'abertura')
                ->where('data_hora', '>=', $inicio)->where('data_hora', '<', $fim)
                ->orderBy('id')->get(['id', 'data_hora']);

            if ($turnosDoDia->isEmpty()) {
                $aberturaId = -1; // nenhum turno nesse dia
            } elseif (! $aberturaId || ! $turnosDoDia->contains('id', $aberturaId)) {
                $aberturaId = $turnosDoDia->last()->id;
            }
        } else {
            $data = null;
        }

        return view('pdv.caixa-extrato', [
            'empresaNome' => $empresaAtual?->nome_fantasia ?? $empresaAtual?->razao_social ?? $empresa,
            'resumo' => $aberturaId === -1 ? null : $this->caixaService->resumoTurno($empresaAtual->id, $aberturaId),
            'geradoEm' => now('America/Sao_Paulo'),
            'data' => $data,
            'turnosDoDia' => $turnosDoDia,
            'aberturaSelecionada' => $aberturaId,
        ]);
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
