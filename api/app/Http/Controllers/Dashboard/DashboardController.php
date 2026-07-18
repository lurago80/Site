<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            ContaPagar::where('empresa_id', $empresaAtual->id)->with('fornecedor')->orderBy('vencimento')->get()
        );
    }

    public function criarContaPagar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'fornecedor_id' => ['nullable', 'integer'],
            'valor' => ['required', 'numeric', 'min:0'],
            'vencimento' => ['required', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $conta = ContaPagar::create($dados + ['empresa_id' => $empresaAtual->id, 'status' => 'em_aberto']);

        return response()->json($conta, 201);
    }

    public function marcarContaPagarPaga(Request $request, string $empresa, int $contaId)
    {
        $conta = ContaPagar::findOrFail($contaId);
        $conta->update(['status' => 'pago']);

        return response()->json($conta->fresh());
    }

    public function contasReceber(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            ContaReceber::where('empresa_id', $empresaAtual->id)->with('cliente')->orderBy('vencimento')->get()
        );
    }

    public function criarContaReceber(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'valor' => ['required', 'numeric', 'min:0'],
            'vencimento' => ['required', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $conta = ContaReceber::create($dados + ['empresa_id' => $empresaAtual->id, 'status' => 'em_aberto']);

        return response()->json($conta, 201);
    }

    public function marcarContaReceberPaga(Request $request, string $empresa, int $contaId)
    {
        $conta = ContaReceber::findOrFail($contaId);
        $conta->update(['status' => 'pago']);

        return response()->json($conta->fresh());
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

    private function exigirAdmin(Request $request): void
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem gerenciar usuários.');
    }
}
