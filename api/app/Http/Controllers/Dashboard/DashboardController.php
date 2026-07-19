<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Banco;
use App\Models\CertificadoDigital;
use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\ConfigPagamento;
use App\Models\ConfigWhatsapp;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;
use App\Models\GravaBanco;
use App\Models\Grupo;
use App\Models\PlanoContas;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use NFePHP\Common\Certificate;

/**
 * Dashboard administrativo (Escopo v2, seção 2.2): cadastros, agenda,
 * financeiro e relatórios da própria empresa. O tenant vem sempre do
 * usuário autenticado (ver SetTenantContext), mesmo padrão dos demais
 * painéis do sistema interno.
 */
class DashboardController extends Controller
{
    public function painel(string $empresa)
    {
        return view('dashboard.painel', ['empresaSlug' => $empresa]);
    }

    public function indicadores(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $vagasHoje = AgendaVisitacao::where('empresa_id', $empresaAtual->id)
            ->whereDate('data_hora', today())
            ->selectRaw('COALESCE(SUM(vagas_reservadas), 0) as total')
            ->value('total');

        $vendasMes = Venda::where('empresa_id', $empresaAtual->id)
            ->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('valor_total');

        $agendaFutura = AgendaVisitacao::where('empresa_id', $empresaAtual->id)
            ->where('data_hora', '>=', now())
            ->get(['vagas_total', 'vagas_reservadas']);
        $ocupacaoMedia = $agendaFutura->isEmpty()
            ? 0
            : round($agendaFutura->avg(fn ($a) => $a->vagas_total > 0 ? $a->vagas_reservadas / $a->vagas_total * 100 : 0), 1);

        $comissoesAPagar = Venda::where('empresa_id', $empresaAtual->id)
            ->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('comissao');

        $proximasVisitas = AgendaVisitacao::where('empresa_id', $empresaAtual->id)
            ->where('data_hora', '>=', now())
            ->orderBy('data_hora')
            ->limit(5)
            ->get(['id', 'data_hora', 'vagas_total', 'vagas_reservadas', 'status']);

        return response()->json([
            'vagas_hoje' => (int) $vagasHoje,
            'vendas_mes' => $vendasMes,
            'ocupacao_media' => $ocupacaoMedia,
            'comissoes_a_pagar' => $comissoesAPagar,
            'proximas_visitas' => $proximasVisitas,
        ]);
    }

    // ---- Agenda de visitas ----

    public function agenda(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            AgendaVisitacao::where('empresa_id', $empresaAtual->id)->orderByDesc('data_hora')->get()
        );
    }

    public function criarAgenda(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_hora' => ['required', 'date'],
            'vagas_total' => ['required', 'integer', 'min:1'],
            'valor_visita' => ['required', 'numeric', 'min:0'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $agenda = AgendaVisitacao::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'vagas_reservadas' => 0,
            'status' => 'aberta',
        ]);

        return response()->json($agenda, 201);
    }

    // ---- Produtos ----

    public function produtos(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Produto::where('empresa_id', $empresaAtual->id)->with('fornecedor')->orderBy('nome')->get()
        );
    }

    public function criarProduto(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'grupo_id' => ['nullable', 'integer'],
            'tipo' => ['required', 'in:fisico,agendamento'],
            'unidade' => ['nullable', 'string', 'max:6'],
            'preco_venda' => ['required', 'numeric', 'min:0'],
            'preco_custo' => ['nullable', 'numeric', 'min:0'],
            'estoque_atual' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
            'fornecedor_id' => ['nullable', 'integer'],
            'ncm' => ['nullable', 'string', 'max:8'],
            'cfop_padrao' => ['nullable', 'string', 'max:4'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $produto = Produto::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'unidade' => $dados['unidade'] ?? 'UN',
            'ativo' => $dados['ativo'] ?? true,
        ]);

        return response()->json($produto, 201);
    }

    public function atualizarProduto(Request $request, string $empresa, int $produtoId)
    {
        $produto = Produto::findOrFail($produtoId);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'grupo_id' => ['nullable', 'integer'],
            'unidade' => ['nullable', 'string', 'max:6'],
            'preco_venda' => ['sometimes', 'numeric', 'min:0'],
            'preco_custo' => ['nullable', 'numeric', 'min:0'],
            'estoque_atual' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
            'fornecedor_id' => ['nullable', 'integer'],
            'ncm' => ['nullable', 'string', 'max:8'],
            'cfop_padrao' => ['nullable', 'string', 'max:4'],
        ]);

        $produto->update($dados);

        return response()->json($produto->fresh());
    }

    // ---- Clientes ----

    public function clientes(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Cliente::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get()
        );
    }

    public function criarCliente(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'uf' => ['nullable', 'string', 'max:2'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'codigo_ibge_municipio' => ['nullable', 'string', 'max:7'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
            'consentimento_lgpd' => ['sometimes', 'boolean'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $cliente = Cliente::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'consentimento_lgpd' => $dados['consentimento_lgpd'] ?? false,
            'consentimento_lgpd_data' => ($dados['consentimento_lgpd'] ?? false) ? now() : null,
            'consentimento_lgpd_versao' => ($dados['consentimento_lgpd'] ?? false) ? 'v1' : null,
        ]);

        return response()->json($cliente, 201);
    }

    /**
     * NFe (modelo 55) exige destinatário com endereço completo - a
     * loja pública e o PDV só coletam nome/CPF na hora da venda, então
     * o dashboard precisa permitir completar isso depois.
     */
    public function atualizarCliente(Request $request, string $empresa, int $clienteId)
    {
        $cliente = Cliente::findOrFail($clienteId);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'uf' => ['nullable', 'string', 'max:2'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'codigo_ibge_municipio' => ['nullable', 'string', 'max:7'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
        ]);

        $cliente->update($dados);

        return response()->json($cliente->fresh());
    }

    // ---- Fornecedores ----

    public function fornecedores(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Fornecedor::where('empresa_id', $empresaAtual->id)->orderBy('razao_social')->get()
        );
    }

    public function criarFornecedor(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contato' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'endereco' => ['nullable', 'string'],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $fornecedor = Fornecedor::create($dados + ['empresa_id' => $empresaAtual->id]);

        return response()->json($fornecedor, 201);
    }

    public function atualizarFornecedor(Request $request, string $empresa, int $fornecedorId)
    {
        $fornecedor = Fornecedor::findOrFail($fornecedorId);

        $dados = $request->validate([
            'razao_social' => ['sometimes', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contato' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'endereco' => ['nullable', 'string'],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
        ]);

        $fornecedor->update($dados);

        return response()->json($fornecedor->fresh());
    }

    // ---- Vendedores ----

    public function vendedores(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Vendedor::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get()
        );
    }

    public function criarVendedor(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'percentual_comissao' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $vendedor = Vendedor::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($vendedor, 201);
    }

    // ---- Financeiro ----

    public function contasPagar(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            ContaPagar::where('empresa_id', $empresaAtual->id)->with(['fornecedor', 'planoContas', 'banco'])->orderBy('vencimento')->get()
        );
    }

    public function criarContaPagar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'fornecedor_id' => ['nullable', 'integer'],
            'plano_conta_id' => ['nullable', 'integer'],
            'valor' => ['required', 'numeric', 'min:0'],
            'vencimento' => ['required', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $conta = ContaPagar::create($dados + ['empresa_id' => $empresaAtual->id, 'status' => 'em_aberto']);

        return response()->json($conta, 201);
    }

    /**
     * Ao informar um banco, lança automaticamente o movimento de débito
     * correspondente em `grava_banco` (Escopo v2, decisão de 2026-07-21) -
     * evita ter que lançar a mesma saída duas vezes (aqui e no extrato
     * bancário).
     */
    public function marcarContaPagarPaga(Request $request, string $empresa, int $contaId)
    {
        $dados = $request->validate(['banco_id' => ['nullable', 'integer']]);
        $empresaAtual = $request->attributes->get('empresaAtual');

        $conta = ContaPagar::findOrFail($contaId);

        DB::transaction(function () use ($conta, $dados, $empresaAtual) {
            $conta->update(['status' => 'pago', 'banco_id' => $dados['banco_id'] ?? $conta->banco_id]);

            if (! empty($dados['banco_id'])) {
                GravaBanco::create([
                    'empresa_id' => $empresaAtual->id,
                    'banco_id' => $dados['banco_id'],
                    'conta_pagar_id' => $conta->id,
                    'data_movimento' => now()->toDateString(),
                    'tipo' => 'debito',
                    'valor' => $conta->valor,
                    'descricao' => "Pagamento conta a pagar #{$conta->id}",
                    'origem' => 'conta_pagar',
                ]);
            }
        });

        return response()->json($conta->fresh());
    }

    public function contasReceber(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            ContaReceber::where('empresa_id', $empresaAtual->id)->with(['cliente', 'planoContas', 'banco'])->orderBy('vencimento')->get()
        );
    }

    public function criarContaReceber(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'plano_conta_id' => ['nullable', 'integer'],
            'valor' => ['required', 'numeric', 'min:0'],
            'vencimento' => ['required', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $conta = ContaReceber::create($dados + ['empresa_id' => $empresaAtual->id, 'status' => 'em_aberto']);

        return response()->json($conta, 201);
    }

    public function marcarContaReceberPaga(Request $request, string $empresa, int $contaId)
    {
        $dados = $request->validate(['banco_id' => ['nullable', 'integer']]);
        $empresaAtual = $request->attributes->get('empresaAtual');

        $conta = ContaReceber::findOrFail($contaId);

        DB::transaction(function () use ($conta, $dados, $empresaAtual) {
            $conta->update(['status' => 'pago', 'banco_id' => $dados['banco_id'] ?? $conta->banco_id]);

            if (! empty($dados['banco_id'])) {
                GravaBanco::create([
                    'empresa_id' => $empresaAtual->id,
                    'banco_id' => $dados['banco_id'],
                    'conta_receber_id' => $conta->id,
                    'data_movimento' => now()->toDateString(),
                    'tipo' => 'credito',
                    'valor' => $conta->valor,
                    'descricao' => "Recebimento conta a receber #{$conta->id}",
                    'origem' => 'conta_receber',
                ]);
            }
        });

        return response()->json($conta->fresh());
    }

    // ---- Grupos de produto ----

    public function grupos(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Grupo::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get()
        );
    }

    public function criarGrupo(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $grupo = Grupo::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($grupo, 201);
    }

    public function atualizarGrupo(Request $request, string $empresa, int $grupoId)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $grupo = Grupo::findOrFail($grupoId);
        $grupo->update($dados);

        return response()->json($grupo->fresh());
    }

    /**
     * Relatório simples de grupo: quantidade de produtos e valor de
     * estoque (preço de custo x estoque atual) por grupo.
     */
    public function relatorioGrupos(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $grupos = Grupo::where('empresa_id', $empresaAtual->id)
            ->withCount('produtos')
            ->with(['produtos' => fn ($q) => $q->select('id', 'grupo_id', 'preco_custo', 'estoque_atual')])
            ->orderBy('nome')
            ->get()
            ->map(fn (Grupo $g) => [
                'id' => $g->id,
                'nome' => $g->nome,
                'produtos_count' => $g->produtos_count,
                'valor_estoque' => $g->produtos->sum(fn ($p) => (float) $p->preco_custo * (int) ($p->estoque_atual ?? 0)),
            ]);

        return response()->json($grupos);
    }

    // ---- Plano de contas ----

    public function planoContas(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            PlanoContas::where('empresa_id', $empresaAtual->id)->orderBy('codigo')->get()
        );
    }

    public function criarPlanoContas(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'codigo' => ['nullable', 'string', 'max:50'],
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:receita,despesa'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $plano = PlanoContas::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($plano, 201);
    }

    public function atualizarPlanoContas(Request $request, string $empresa, int $planoContaId)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'codigo' => ['nullable', 'string', 'max:50'],
            'nome' => ['sometimes', 'string', 'max:255'],
            'tipo' => ['sometimes', 'in:receita,despesa'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $plano = PlanoContas::findOrFail($planoContaId);
        $plano->update($dados);

        return response()->json($plano->fresh());
    }

    /**
     * Relatório por categoria: soma de contas a pagar/receber lançadas
     * em cada conta do plano, dentro de um período opcional
     * (filtra por vencimento).
     */
    public function relatorioPlanoContas(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $periodo = fn ($query) => $query
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('vencimento', '>=', $d))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('vencimento', '<=', $d));

        $planos = PlanoContas::where('empresa_id', $empresaAtual->id)->orderBy('codigo')->get();

        $relatorio = $planos->map(function (PlanoContas $plano) use ($periodo) {
            $query = $plano->tipo === 'despesa'
                ? ContaPagar::where('plano_conta_id', $plano->id)
                : ContaReceber::where('plano_conta_id', $plano->id);

            $query = $periodo($query);

            return [
                'id' => $plano->id,
                'codigo' => $plano->codigo,
                'nome' => $plano->nome,
                'tipo' => $plano->tipo,
                'total' => (clone $query)->sum('valor'),
                'total_pago' => (clone $query)->where('status', 'pago')->sum('valor'),
                'total_em_aberto' => (clone $query)->where('status', 'em_aberto')->sum('valor'),
            ];
        });

        return response()->json($relatorio);
    }

    // ---- Bancos ----

    public function bancos(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Banco::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get()
        );
    }

    public function criarBanco(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigo_banco' => ['nullable', 'string', 'max:20'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero_conta' => ['nullable', 'string', 'max:30'],
            'tipo_conta' => ['required', 'in:corrente,poupanca'],
            'saldo_inicial' => ['nullable', 'numeric'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $banco = Banco::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($banco, 201);
    }

    public function atualizarBanco(Request $request, string $empresa, int $bancoId)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'codigo_banco' => ['nullable', 'string', 'max:20'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero_conta' => ['nullable', 'string', 'max:30'],
            'tipo_conta' => ['sometimes', 'in:corrente,poupanca'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $banco = Banco::findOrFail($bancoId);
        $banco->update($dados);

        return response()->json($banco->fresh());
    }

    public function lancarMovimentoBancario(Request $request, string $empresa, int $bancoId)
    {
        $dados = $request->validate([
            'data_movimento' => ['required', 'date'],
            'tipo' => ['required', 'in:credito,debito'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        Banco::findOrFail($bancoId);

        $movimento = GravaBanco::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'banco_id' => $bancoId,
            'origem' => 'manual',
        ]);

        return response()->json($movimento, 201);
    }

    /**
     * Extrato: movimentos do banco no período, com saldo corrente
     * (saldo inicial da conta + acumulado até cada linha).
     */
    public function extratoBanco(Request $request, string $empresa, int $bancoId)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        $banco = Banco::findOrFail($bancoId);

        $somaAnterior = 0;
        if (! empty($dados['data_inicio'])) {
            $somaAnterior = GravaBanco::where('banco_id', $bancoId)
                ->where('data_movimento', '<', $dados['data_inicio'])
                ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END), 0) as total")
                ->value('total') ?? 0;
        }

        $saldoAnterior = (float) $banco->saldo_inicial + (float) $somaAnterior;

        $movimentos = GravaBanco::where('banco_id', $bancoId)
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('data_movimento', '>=', $d))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data_movimento', '<=', $d))
            ->orderBy('data_movimento')
            ->orderBy('id')
            ->get();

        $saldo = $saldoAnterior;
        $linhas = $movimentos->map(function (GravaBanco $m) use (&$saldo) {
            $saldo += $m->tipo === 'credito' ? (float) $m->valor : -(float) $m->valor;

            return [
                'id' => $m->id,
                'data_movimento' => $m->data_movimento,
                'tipo' => $m->tipo,
                'valor' => $m->valor,
                'descricao' => $m->descricao,
                'origem' => $m->origem,
                'saldo_apos' => $saldo,
            ];
        });

        return response()->json([
            'banco' => $banco->only(['id', 'nome', 'agencia', 'numero_conta']),
            'saldo_anterior' => $saldoAnterior,
            'saldo_atual' => $saldo,
            'movimentos' => $linhas,
        ]);
    }

    // ---- Usuários (apenas admin) ----

    public function usuarios(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            User::where('empresa_id', $empresaAtual->id)->orderBy('name')->get()
        );
    }

    public function criarUsuario(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'perfil' => ['required', 'in:admin,caixa,atendente'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $usuario = User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'password' => Hash::make($dados['password']),
            'perfil' => $dados['perfil'],
            'empresa_id' => $empresaAtual->id,
            'ativo' => true,
        ]);

        return response()->json($usuario, 201);
    }

    public function atualizarUsuario(Request $request, string $empresa, int $usuarioId)
    {
        $this->exigirAdmin($request);

        $usuario = User::findOrFail($usuarioId);

        $dados = $request->validate([
            'ativo' => ['sometimes', 'boolean'],
            'perfil' => ['sometimes', 'in:admin,caixa,atendente'],
        ]);

        $usuario->update($dados);

        return response()->json($usuario->fresh());
    }

    // ---- Configuração fiscal (emitente) ----

    /**
     * Dados do emitente exigidos para emitir NFe/NFC-e: endereço fiscal
     * completo da empresa (Empresa) + regime tributário/numeração
     * (ConfigFiscal). Sem isso cadastrado, o NfePhpFiscalGateway rejeita
     * a emissão - ver Escopo v2, seção 4.2.
     */
    public function configFiscal(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $config = ConfigFiscal::where('empresa_id', $empresaAtual->id)->first();

        return response()->json([
            'empresa' => $empresaAtual->only([
                'razao_social', 'cnpj', 'uf', 'municipio', 'codigo_ibge_municipio',
                'cep', 'logradouro', 'numero', 'bairro', 'complemento',
            ]),
            'config_fiscal' => $config,
        ]);
    }

    public function atualizarConfigFiscal(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'uf' => ['nullable', 'string', 'max:2'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'codigo_ibge_municipio' => ['nullable', 'string', 'max:7'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'crt' => ['nullable', 'string', 'max:255'],
            'inscricao_estadual' => ['nullable', 'string', 'max:255'],
            'inscricao_municipal' => ['nullable', 'string', 'max:255'],
            'ambiente_ativo' => ['required', 'in:producao,homologacao'],
            'csc_nfce' => ['nullable', 'string', 'max:255'],
            'id_token_csc' => ['nullable', 'string', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $empresaAtual->update(array_intersect_key($dados, array_flip([
            'uf', 'municipio', 'codigo_ibge_municipio', 'cep', 'logradouro', 'numero', 'bairro',
        ])));

        $config = ConfigFiscal::updateOrCreate(
            ['empresa_id' => $empresaAtual->id],
            array_intersect_key($dados, array_flip([
                'crt', 'inscricao_estadual', 'inscricao_municipal', 'ambiente_ativo', 'csc_nfce', 'id_token_csc',
            ]))
        );

        return response()->json(['empresa' => $empresaAtual->fresh(), 'config_fiscal' => $config]);
    }

    // ---- Certificado digital ----

    public function certificado(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $certificado = CertificadoDigital::where('empresa_id', $empresaAtual->id)->first();

        if ($certificado === null) {
            return response()->json(['cadastrado' => false]);
        }

        return response()->json([
            'cadastrado' => true,
            'tipo' => $certificado->tipo,
            'validade' => $certificado->validade,
            'expirado' => $certificado->validade?->isPast() ?? false,
        ]);
    }

    /**
     * Faz upload do .pfx, valida a senha lendo o certificado de verdade
     * (NFePHP\Common\Certificate) antes de salvar - evita guardar um
     * certificado/senha que não funciona e só descobrir isso na hora de
     * emitir. A validade é extraída do próprio certificado, não digitada.
     */
    public function salvarCertificado(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            // 'mimes:pfx,p12' não funciona aqui - PKCS12 não tem um MIME
            // type padronizado no mapa do Laravel/Symfony, então a regra
            // rejeitava certificados .pfx reais mesmo com extensão certa.
            // A validação de verdade é tentar abrir o certificado abaixo.
            'arquivo' => ['required', 'file', function ($attribute, $value, $fail) {
                if (! in_array(strtolower($value->getClientOriginalExtension()), ['pfx', 'p12'], true)) {
                    $fail('O arquivo deve ter extensão .pfx ou .p12.');
                }
            }],
            'senha' => ['required', 'string'],
            'tipo' => ['required', 'in:A1,A3'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $conteudo = file_get_contents($dados['arquivo']->getRealPath());

        try {
            $certificadoPfx = Certificate::readPfx($conteudo, $dados['senha']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Não foi possível ler o certificado - senha incorreta ou arquivo inválido.',
            ], 422);
        }

        $validade = $certificadoPfx->getValidTo();

        $caminhoRelativo = "certificados/{$empresaAtual->id}.pfx";
        Storage::put($caminhoRelativo, $conteudo);

        $certificado = CertificadoDigital::updateOrCreate(
            ['empresa_id' => $empresaAtual->id],
            [
                'tipo' => $dados['tipo'],
                'arquivo_referencia' => Storage::path($caminhoRelativo),
                'senha_criptografada' => $dados['senha'],
                'validade' => $validade,
            ]
        );

        return response()->json([
            'cadastrado' => true,
            'tipo' => $certificado->tipo,
            'validade' => $certificado->validade,
            'expirado' => false,
        ], 201);
    }

    // ---- Formas de pagamento ----

    public function formasPagamento(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            FormaPagamento::where('empresa_id', $empresaAtual->id)->orderBy('descricao')->get()
        );
    }

    public function criarFormaPagamento(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:dinheiro,pix,cartao_credito,cartao_debito,outro'],
            'codigo_tpag' => ['required', 'string', 'max:2'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $forma = FormaPagamento::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($forma, 201);
    }

    public function atualizarFormaPagamento(Request $request, string $empresa, int $formaId)
    {
        $this->exigirAdmin($request);

        $forma = FormaPagamento::findOrFail($formaId);

        $dados = $request->validate([
            'descricao' => ['sometimes', 'string', 'max:255'],
            'tipo' => ['sometimes', 'in:dinheiro,pix,cartao_credito,cartao_debito,outro'],
            'codigo_tpag' => ['sometimes', 'string', 'max:2'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $forma->update($dados);

        return response()->json($forma->fresh());
    }

    // ---- Configuração de gateway de pagamento ----

    /**
     * Gateway de pagamento escolhido POR EMPRESA (Escopo v2, decisão de
     * 2026-07-18: cada empresa cliente pode ter taxas melhores em
     * gateways diferentes) - mesmo padrão do Config. Fiscal, admin-only.
     */
    public function configPagamento(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $config = ConfigPagamento::where('empresa_id', $empresaAtual->id)->first();

        return response()->json($config ? [
            'gateway' => $config->gateway,
            'ambiente' => $config->ambiente,
            'ativo' => $config->ativo,
            'tem_credenciais' => ! empty($config->access_token) || ! empty($config->client_secret),
            'public_key' => $config->public_key,
            'client_id' => $config->client_id,
        ] : null);
    }

    public function atualizarConfigPagamento(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'gateway' => ['required', 'in:mercadopago,pagseguro,cielo'],
            'ambiente' => ['required', 'in:sandbox,producao'],
            'access_token' => ['nullable', 'string'],
            'public_key' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        // Só sobrescreve credenciais quando o operador realmente digitou
        // algo novo - o front-end nunca reenvia o token existente (ele
        // não é devolvido pela API por segurança, ver configPagamento()).
        if (empty($dados['access_token'])) {
            unset($dados['access_token']);
        }
        if (empty($dados['client_secret'])) {
            unset($dados['client_secret']);
        }

        $config = ConfigPagamento::updateOrCreate(['empresa_id' => $empresaAtual->id], $dados);

        return response()->json([
            'gateway' => $config->gateway,
            'ambiente' => $config->ambiente,
            'ativo' => $config->ativo,
            'tem_credenciais' => ! empty($config->access_token) || ! empty($config->client_secret),
        ]);
    }

    // ---- Configuração de notificação WhatsApp ----

    /**
     * Provedor de WhatsApp escolhido POR EMPRESA (Escopo v2, decisão de
     * 2026-07-19: o cliente decide entre Z-API pago ou Baileys gratuito
     * mas fora dos Termos de Uso do WhatsApp) - mesmo padrão do Config.
     * Pagamento, admin-only.
     */
    public function configWhatsapp(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $config = ConfigWhatsapp::where('empresa_id', $empresaAtual->id)->first();

        return response()->json($config ? [
            'provider' => $config->provider,
            'ativo' => $config->ativo,
            'tem_credenciais' => ! empty($config->token) || ! empty($config->client_token),
            'instance_id' => $config->instance_id,
        ] : null);
    }

    public function atualizarConfigWhatsapp(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'provider' => ['required', 'in:zapi,baileys'],
            'instance_id' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string'],
            'client_token' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        // Só sobrescreve credenciais quando o operador realmente digitou
        // algo novo - o front-end nunca reenvia o token existente (ele
        // não é devolvido pela API por segurança, ver configWhatsapp()).
        if (empty($dados['token'])) {
            unset($dados['token']);
        }
        if (empty($dados['client_token'])) {
            unset($dados['client_token']);
        }

        $config = ConfigWhatsapp::updateOrCreate(['empresa_id' => $empresaAtual->id], $dados);

        return response()->json([
            'provider' => $config->provider,
            'ativo' => $config->ativo,
            'tem_credenciais' => ! empty($config->token) || ! empty($config->client_token),
        ]);
    }

    // ---- Pareamento da sessão Baileys (WhatsApp gratuito via QR code) ----

    /**
     * Proxy fino para o microserviço Node.js (whatsapp-service/) que
     * roda o Baileys - o Laravel não fala o protocolo do WhatsApp Web
     * diretamente, só repassa a ação para o serviço interno.
     */
    public function baileysStatus(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);
        $empresaAtual = $request->attributes->get('empresaAtual');

        return $this->proxyBaileys('get', "/empresas/{$empresaAtual->id}/status");
    }

    public function baileysIniciar(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);
        $empresaAtual = $request->attributes->get('empresaAtual');

        return $this->proxyBaileys('post', "/empresas/{$empresaAtual->id}/iniciar");
    }

    public function baileysDesconectar(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);
        $empresaAtual = $request->attributes->get('empresaAtual');

        return $this->proxyBaileys('post', "/empresas/{$empresaAtual->id}/desconectar");
    }

    private function proxyBaileys(string $metodo, string $caminho)
    {
        $url = rtrim(config('services.baileys.url'), '/').$caminho;

        try {
            $resposta = Http::withHeaders(['x-internal-token' => config('services.baileys.token')])
                ->timeout(15)
                ->{$metodo}($url);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return response()->json([
                'erro' => 'Não foi possível conectar ao serviço de WhatsApp (whatsapp-service). Verifique se ele está rodando.',
            ], 503);
        }

        return response()->json($resposta->json(), $resposta->status());
    }

    private function exigirAdmin(Request $request): void
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem gerenciar usuários.');
    }
}
