<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Atendente;
use App\Models\Banco;
use App\Models\CertificadoDigital;
use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\ConfigPagamento;
use App\Models\ConfigWhatsapp;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Cupom;
use App\Jobs\EnviarPedidoEnviadoJob;
use App\Models\Empresa;
use App\Support\ErroCertificado;
use App\Models\DescontoPdv;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;
use App\Models\FreteRegra;
use App\Models\KitComponente;
use App\Models\GravaBanco;
use App\Models\Grupo;
use App\Models\ItemVenda;
use App\Models\PlanoContas;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            AgendaVisitacao::where('empresa_id', $empresaAtual->id)
                ->with(['vendedor:id,nome', 'atendente:id,nome'])
                ->orderByDesc('data_hora')
                ->get()
        );
    }

    public function criarAgenda(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $dados = $request->validate([
            'data_hora' => ['required', 'date'],
            'vagas_total' => ['required', 'integer', 'min:1'],
            'valor_visita' => ['required', 'numeric', 'min:0'],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('vendedores', 'id')->where('empresa_id', $empresaAtual->id)],
            'atendente_id' => ['nullable', 'integer', Rule::exists('atendentes', 'id')->where('empresa_id', $empresaAtual->id)],
        ]);

        $agenda = AgendaVisitacao::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'vagas_reservadas' => 0,
            'status' => 'aberta',
        ]);

        return response()->json($agenda, 201);
    }

    public function atualizarAgenda(Request $request, string $empresa, int $agendaId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $dados = $request->validate([
            'data_hora' => ['sometimes', 'date'],
            'vagas_total' => ['sometimes', 'integer', 'min:1'],
            'valor_visita' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:aberta,lotada,cancelada'],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('vendedores', 'id')->where('empresa_id', $empresaAtual->id)],
            'atendente_id' => ['nullable', 'integer', Rule::exists('atendentes', 'id')->where('empresa_id', $empresaAtual->id)],
        ]);

        $agenda = AgendaVisitacao::where('empresa_id', $empresaAtual->id)->findOrFail($agendaId);
        $agenda->update($dados);

        return response()->json($agenda);
    }

    public function excluirAgenda(Request $request, string $empresa, int $agendaId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $agenda = AgendaVisitacao::where('empresa_id', $empresaAtual->id)->findOrFail($agendaId);

        if ($agenda->vagas_reservadas > 0) {
            abort(422, 'Não é possível excluir um horário com vagas já reservadas - cancele-o em vez disso.');
        }

        $agenda->delete();

        return response()->json(null, 204);
    }

    // ---- Produtos ----

    public function produtos(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Produto::where('empresa_id', $empresaAtual->id)
                ->with(['fornecedor', 'grupo', 'classTrib', 'creditoPresumido', 'variacoes'])
                ->orderBy('nome')
                ->get()
        );
    }

    /**
     * Variações de um produto (tamanho P/M/G/GG, sabor, etc.), cada uma
     * com estoque próprio - usado por vestuário/suvenir e por produtos
     * vendidos em caixa fechada com sabores variados (ex.: cerveja).
     */
    public function variacoesProduto(Request $request, string $empresa, int $produtoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $produto = Produto::where('empresa_id', $empresaAtual->id)->findOrFail($produtoId);

        return response()->json($produto->variacoes()->orderBy('tamanho')->get());
    }

    public function criarVariacaoProduto(Request $request, string $empresa, int $produtoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $produto = Produto::where('empresa_id', $empresaAtual->id)->findOrFail($produtoId);

        $dados = $request->validate([
            'tamanho' => ['required', 'string', 'max:40'],
            'estoque_atual' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $variacao = ProdutoVariacao::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'produto_id' => $produto->id,
            'estoque_atual' => $dados['estoque_atual'] ?? 0,
            'ativo' => $dados['ativo'] ?? true,
        ]);

        return response()->json($variacao, 201);
    }

    public function atualizarVariacaoProduto(Request $request, string $empresa, int $produtoId, int $variacaoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $variacao = ProdutoVariacao::where('empresa_id', $empresaAtual->id)
            ->where('produto_id', $produtoId)
            ->findOrFail($variacaoId);

        $dados = $request->validate([
            'tamanho' => ['sometimes', 'string', 'max:40'],
            'estoque_atual' => ['sometimes', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $variacao->update($dados);

        return response()->json($variacao->fresh());
    }

    public function excluirVariacaoProduto(Request $request, string $empresa, int $produtoId, int $variacaoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $variacao = ProdutoVariacao::where('empresa_id', $empresaAtual->id)
            ->where('produto_id', $produtoId)
            ->findOrFail($variacaoId);

        $variacao->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Composição do kit (ex.: 1 caneca + 3 cervejas à escolha). Itens "fixos"
     * vêm sempre; em "escolha" o cliente distribui `quantidade` unidades entre
     * as variações do produto (pode repetir). O preço é o do próprio produto-kit.
     */
    public function kitProduto(Request $request, string $empresa, int $produtoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $produto = Produto::where('empresa_id', $empresaAtual->id)->findOrFail($produtoId);

        return response()->json($this->payloadKit($produto));
    }

    public function atualizarKitProduto(Request $request, string $empresa, int $produtoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $produto = Produto::where('empresa_id', $empresaAtual->id)->findOrFail($produtoId);

        $dados = $request->validate([
            'eh_kit' => ['required', 'boolean'],
            'componentes' => ['present', 'array'],
            'componentes.*.tipo' => ['required', 'string', 'in:fixo,escolha'],
            'componentes.*.produto_id' => ['required', 'integer'],
            'componentes.*.quantidade' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if ($dados['eh_kit']) {
            abort_if($dados['componentes'] === [], 422, 'Informe ao menos um item do kit.');
            abort_if($produto->variacoes()->exists(), 422, 'Um kit não pode ter variações próprias - remova os tamanhos deste produto.');

            $ids = collect($dados['componentes'])->pluck('produto_id');
            abort_if($ids->count() !== $ids->unique()->count(), 422, 'Há produto repetido na composição do kit.');
            abort_if($ids->contains($produto->id), 422, 'O kit não pode conter ele mesmo.');

            $componentes = Produto::where('empresa_id', $empresaAtual->id)->whereIn('id', $ids)->withCount([
                'variacoes as variacoes_ativas_count' => fn ($q) => $q->where('ativo', true),
            ])->get()->keyBy('id');

            foreach ($dados['componentes'] as $componente) {
                $item = $componentes->get($componente['produto_id']);

                abort_if($item === null, 422, 'Produto do kit não encontrado.');
                abort_if($item->eh_kit, 422, "\"{$item->nome}\" é um kit e não pode compor outro kit.");
                abort_if(
                    $componente['tipo'] === 'escolha' && $item->variacoes_ativas_count === 0,
                    422,
                    "\"{$item->nome}\" precisa ter variações (sabores/tamanhos) para ser escolhido no kit."
                );
                abort_if(
                    $componente['tipo'] === 'fixo' && $item->variacoes_ativas_count > 0,
                    422,
                    "\"{$item->nome}\" tem variações - use o tipo \"escolha\" para ele."
                );
            }
        }

        DB::transaction(function () use ($dados, $produto, $empresaAtual) {
            $produto->update(['eh_kit' => $dados['eh_kit']]);
            KitComponente::where('kit_id', $produto->id)->delete();

            if (! $dados['eh_kit']) {
                return;
            }

            foreach ($dados['componentes'] as $componente) {
                KitComponente::create([
                    'empresa_id' => $empresaAtual->id,
                    'kit_id' => $produto->id,
                    'produto_id' => $componente['produto_id'],
                    'tipo' => $componente['tipo'],
                    'quantidade' => $componente['quantidade'],
                ]);
            }
        });

        return response()->json($this->payloadKit($produto->fresh()));
    }

    private function payloadKit(Produto $produto): array
    {
        return [
            'eh_kit' => $produto->eh_kit,
            'componentes' => $produto->componentes()->with('produto:id,nome')->get()->map(fn ($c) => [
                'tipo' => $c->tipo,
                'produto_id' => $c->produto_id,
                'nome' => $c->produto?->nome,
                'quantidade' => $c->quantidade,
            ])->values(),
        ];
    }

    /**
     * Tabelas auxiliares oficiais (globais, sem empresa_id) usadas nos
     * selects de cClassTrib/cCredPres do cadastro de produto.
     */
    public function tabClassTrib(Request $request, string $empresa)
    {
        return response()->json(
            \App\Models\TabClassTrib::where('ativo', true)->orderBy('codigo')->get()
        );
    }

    public function tabCredPres(Request $request, string $empresa)
    {
        return response()->json(
            \App\Models\TabCredPres::where('ativo', true)->orderBy('codigo')->get()
        );
    }

    public function criarProduto(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:fisico,agendamento'],
            'preco_venda' => ['required', 'numeric', 'min:0'],
            ...$this->regrasFiscaisProduto(),
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        unset($dados['imagem']);
        if ($request->hasFile('imagem')) {
            $dados['imagem_url'] = $this->armazenarImagemProduto($request->file('imagem'));
        }

        $somenteLoja = (bool) ($dados['somente_loja_virtual'] ?? false);

        // produto só da loja virtual precisa, claro, aparecer na loja
        // ($dados + [...] mantém o valor enviado, por isso força aqui)
        if ($somenteLoja) {
            $dados['loja_virtual'] = true;
        }

        $produto = Produto::create($dados + [
            'empresa_id' => $empresaAtual->id,
            'unidade' => $dados['unidade'] ?? 'UN',
            'ativo' => $dados['ativo'] ?? true,
            'loja_virtual' => $dados['loja_virtual'] ?? true,
            'somente_loja_virtual' => $somenteLoja,
        ]);

        return response()->json($produto, 201);
    }

    public function atualizarProduto(Request $request, string $empresa, int $produtoId)
    {
        $produto = Produto::findOrFail($produtoId);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'preco_venda' => ['sometimes', 'numeric', 'min:0'],
            ...$this->regrasFiscaisProduto(),
        ]);

        unset($dados['imagem']);
        if ($request->hasFile('imagem')) {
            $dados['imagem_url'] = $this->armazenarImagemProduto($request->file('imagem'));
        }

        if (! empty($dados['somente_loja_virtual'])) {
            $dados['loja_virtual'] = true;
        }

        $produto->update($dados);

        return response()->json($produto->fresh());
    }

    /**
     * Salva o arquivo de imagem enviado no disco público (storage/app/public/produtos)
     * e devolve a URL acessível publicamente para gravar em imagem_url.
     */
    private function armazenarImagemProduto(\Illuminate\Http\UploadedFile $arquivo): string
    {
        return $this->armazenarImagem($arquivo, 'produtos');
    }

    /**
     * Salva o logo do emitente no disco público (storage/app/public/logos) -
     * usado tanto no PDV (topo) quanto na loja pública (Header.tsx via logo_url).
     */
    private function armazenarImagemEmpresa(\Illuminate\Http\UploadedFile $arquivo): string
    {
        return $this->armazenarImagem($arquivo, 'logos');
    }

    private function armazenarImagem(\Illuminate\Http\UploadedFile $arquivo, string $pasta): string
    {
        $caminho = $arquivo->store($pasta, 'public');

        return \Illuminate\Support\Facades\Storage::disk('public')->url($caminho);
    }

    /**
     * Campos fiscais do produto (Escopo v2, decisão de 2026-07-25 -
     * Reforma Tributária/LC 214/2025): regime atual (ICMS/PIS/COFINS/
     * IPI), novo regime (IBS/CBS) e Imposto Seletivo. Compartilhado
     * entre criarProduto/atualizarProduto para não duplicar ~40 regras.
     */
    private function regrasFiscaisProduto(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:255'],
            'codigo_barras' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'grupo_id' => ['nullable', 'integer'],
            'unidade' => ['nullable', 'string', 'max:6'],
            'preco_custo' => ['nullable', 'numeric', 'min:0'],
            'valor_atacado' => ['nullable', 'numeric', 'min:0'],
            'estoque_atual' => ['nullable', 'integer', 'min:0'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
            'quantidade_minima_venda' => ['nullable', 'integer', 'min:1'],
            'ativo' => ['sometimes', 'boolean'],
            'loja_virtual' => ['sometimes', 'boolean'],
            'somente_loja_virtual' => ['sometimes', 'boolean'],
            'pesavel' => ['sometimes', 'boolean'],
            'imagem_url' => ['nullable', 'string', 'max:255'],
            'imagem' => ['nullable', 'file', 'image', 'max:5120'],
            'peso_liquido' => ['nullable', 'numeric', 'min:0'],
            'peso_bruto' => ['nullable', 'numeric', 'min:0'],
            'fornecedor_id' => ['nullable', 'integer'],
            'ncm' => ['nullable', 'string', 'max:8'],
            'cfop_padrao' => ['nullable', 'string', 'max:4'],
            'tipo_produto_fiscal' => ['nullable', 'in:consumo,materia_prima,produto,servico,brinde'],

            // ICMS
            'cfop_interestadual' => ['nullable', 'string', 'max:4'],
            'cest' => ['nullable', 'string', 'max:7'],
            'cst_origem' => ['nullable', 'string', 'max:1'],
            'cst_icms' => ['nullable', 'string', 'max:2'],
            'aliquota_icms' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fcp_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mva_percentual' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'reducao_base_calculo_icms' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grupo_fiscal' => ['nullable', 'string', 'max:255'],
            'codigo_beneficio_fiscal' => ['nullable', 'string', 'max:255'],

            // PIS/COFINS
            'cst_pis' => ['nullable', 'string', 'max:2'],
            'aliquota_pis' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cst_cofins' => ['nullable', 'string', 'max:2'],
            'aliquota_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'natureza_receita_pis_cofins' => ['nullable', 'string', 'max:255'],

            // IPI
            'cst_ipi' => ['nullable', 'string', 'max:2'],
            'aliquota_ipi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'codigo_enquadramento_ipi' => ['nullable', 'string', 'max:255'],

            // IBS/CBS (novo regime)
            'situacao_novo_regime' => ['nullable', 'in:0,1,2'],
            'cst_ibs_cbs' => ['nullable', 'string', 'max:3'],
            'cclasstrib_id' => ['nullable', 'integer'],
            'aliquota_ibs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'aliquota_cbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reducao_base_calculo_ibs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reducao_base_calculo_cbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentual_credito_ibs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentual_credito_cbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ccredpres_id' => ['nullable', 'integer'],

            // Imposto Seletivo
            'sujeito_imposto_seletivo' => ['sometimes', 'boolean'],
            'tipo_imposto_seletivo' => ['nullable', 'in:veiculos,cigarros,bebidas_alcoolicas,bebidas_acucaradas,combustiveis_fosseis,bens_minerais'],
            'cclasstrib_is' => ['nullable', 'string', 'max:6'],
            'aliquota_is' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Destinação e crédito
            'destinacao_tributaria' => ['nullable', 'in:RV,UC,AT,SV'],
            'tipo_credito' => ['nullable', 'in:IN,PA,NE'],
        ];
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
            'telefone' => ['nullable', 'string', 'max:20'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $dados['percentual_comissao'] ??= 5;

        $empresaAtual = $request->attributes->get('empresaAtual');

        $vendedor = Vendedor::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($vendedor, 201);
    }

    // ---- Atendentes (quem opera a venda no PDV - diferente do vendedor/guia) ----

    public function atendentes(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Atendente::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get()
        );
    }

    public function criarAtendente(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $dados['percentual_comissao'] ??= 3;

        $empresaAtual = $request->attributes->get('empresaAtual');

        $atendente = Atendente::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($atendente, 201);
    }

    public function atualizarAtendente(Request $request, string $empresa, int $atendenteId)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'percentual_comissao' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $atendente = Atendente::findOrFail($atendenteId);
        $atendente->update($dados);

        return response()->json($atendente->fresh());
    }

    /**
     * Relatório simples: quantidade de itens vendidos e valor total
     * processado por atendente (quem operou o caixa), dentro de um período
     * opcional - com filtro por tipo de item (produto físico e/ou visita
     * agendada), já que uma venda pode misturar os dois.
     */
    public function relatorioAtendentes(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'tipo' => ['nullable', 'in:todos,produtos,visitacoes'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            $this->relatorioVendasPorPessoa('atendente_id', Atendente::class, $empresaAtual, $dados)
        );
    }

    /**
     * Mesmo relatório do atendente, mas para o vendedor (guia da visita,
     * recebe comissão) - ver DashboardController::relatorioAtendentes.
     */
    public function relatorioVendedores(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'tipo' => ['nullable', 'in:todos,produtos,visitacoes'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            $this->relatorioVendasPorPessoa('vendedor_id', Vendedor::class, $empresaAtual, $dados)
        );
    }

    /**
     * Agrega itens_venda (não vendas inteiras) por pessoa, porque uma
     * mesma venda pode ter produtos físicos e visitas agendadas ao mesmo
     * tempo - o filtro "tipo" (produtos/visitações) só faz sentido no
     * nível do item, não da venda como um todo.
     */
    private function relatorioVendasPorPessoa(string $colunaPessoa, string $modeloPessoa, Empresa $empresaAtual, array $dados)
    {
        $tipo = $dados['tipo'] ?? 'todos';

        return $modeloPessoa::where('empresa_id', $empresaAtual->id)
            ->orderBy('nome')
            ->get()
            ->map(function ($pessoa) use ($colunaPessoa, $dados, $tipo) {
                $itens = ItemVenda::whereHas('venda', function ($q) use ($colunaPessoa, $pessoa, $dados) {
                    $q->where($colunaPessoa, $pessoa->id)
                        ->when($dados['data_inicio'] ?? null, fn ($qq, $d) => $qq->where('data_venda', '>=', $d))
                        ->when($dados['data_fim'] ?? null, fn ($qq, $d) => $qq->where('data_venda', '<=', $d));
                })
                    ->when($tipo === 'produtos', fn ($q) => $q->whereNotNull('produto_id'))
                    ->when($tipo === 'visitacoes', fn ($q) => $q->whereNotNull('agenda_visitacao_id'));

                return [
                    'id' => $pessoa->id,
                    'nome' => $pessoa->nome,
                    'vendas_count' => (clone $itens)->distinct('venda_id')->count('venda_id'),
                    'itens_count' => (clone $itens)->sum('quantidade'),
                    'valor_total' => (clone $itens)->sum('valor_total'),
                ];
            });
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
            'historico' => ['nullable', 'string', 'max:255'],
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
            'historico' => ['nullable', 'string', 'max:255'],
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
            'pis_cofins_exclui_icms' => ['sometimes', 'boolean'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $empresaAtual->update(array_intersect_key($dados, array_flip([
            'uf', 'municipio', 'codigo_ibge_municipio', 'cep', 'logradouro', 'numero', 'bairro',
        ])));

        $config = ConfigFiscal::updateOrCreate(
            ['empresa_id' => $empresaAtual->id],
            array_intersect_key($dados, array_flip([
                'crt', 'inscricao_estadual', 'inscricao_municipal', 'ambiente_ativo', 'csc_nfce', 'id_token_csc', 'pis_cofins_exclui_icms',
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
                'message' => ErroCertificado::mensagem($e),
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

    // ---- Cupons de desconto (loja pública) ----

    public function cupons(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            // Lotes importados (ex.: planilha de códigos promocionais) ficam de fora
            // desta lista - são centenas/milhares de linhas, não cabem numa tela de
            // cadastro manual. Eles têm sua própria tela, em cuponsLote().
            Cupom::where('empresa_id', $empresaAtual->id)->where('importado', false)->orderBy('codigo')->get()
        );
    }

    /**
     * Relatório paginado dos cupons importados em lote - mostra, pra cada
     * código, se já foi usado, quando e por qual cliente.
     */
    public function cuponsLote(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $dados = $request->validate([
            'busca' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:todos,usados,disponiveis'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $consulta = Cupom::where('empresa_id', $empresaAtual->id)
            ->where('importado', true)
            ->with('usadoPor:id,nome')
            ->orderBy('codigo');

        if (! empty($dados['busca'])) {
            $consulta->whereRaw('codigo ILIKE ?', ['%'.$dados['busca'].'%']);
        }

        if (($dados['status'] ?? 'todos') === 'usados') {
            $consulta->whereNotNull('usado_em');
        } elseif (($dados['status'] ?? 'todos') === 'disponiveis') {
            $consulta->whereNull('usado_em');
        }

        $pagina = $consulta->paginate(50, page: $dados['pagina'] ?? 1);

        return response()->json([
            'dados' => $pagina->items(),
            'total' => $pagina->total(),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'resumo' => [
                'total' => (clone $consulta)->toBase()->getCountForPagination(),
                'usados' => Cupom::where('empresa_id', $empresaAtual->id)->where('importado', true)->whereNotNull('usado_em')->count(),
            ],
        ]);
    }

    public function criarCupom(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
            'tipo' => ['required', 'in:percentual,valor_fixo'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'valor_maximo_desconto' => ['nullable', 'numeric', 'min:0'],
            'valido_ate' => ['nullable', 'date'],
            'limite_uso' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($dados['tipo'] === 'percentual') {
            abort_if($dados['valor'] > 100, 422, 'Desconto percentual não pode passar de 100%.');
        }

        $empresaAtual = $request->attributes->get('empresaAtual');

        $dados['codigo'] = mb_strtoupper($dados['codigo']);

        $cupom = Cupom::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($cupom, 201);
    }

    public function atualizarCupom(Request $request, string $empresa, int $cupomId)
    {
        $this->exigirAdmin($request);

        $cupom = Cupom::findOrFail($cupomId);

        $dados = $request->validate([
            'codigo' => ['sometimes', 'string', 'max:40'],
            'tipo' => ['sometimes', 'in:percentual,valor_fixo'],
            'valor' => ['sometimes', 'numeric', 'min:0.01'],
            'valor_maximo_desconto' => ['nullable', 'numeric', 'min:0'],
            'valido_ate' => ['nullable', 'date'],
            'limite_uso' => ['nullable', 'integer', 'min:1'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        if (($dados['tipo'] ?? $cupom->tipo) === 'percentual' && ($dados['valor'] ?? $cupom->valor) > 100) {
            abort(422, 'Desconto percentual não pode passar de 100%.');
        }

        if (isset($dados['codigo'])) {
            $dados['codigo'] = mb_strtoupper($dados['codigo']);
        }

        $cupom->update($dados);

        return response()->json($cupom->fresh());
    }

    // ---- Descontos do PDV (só frente de caixa, nunca loja virtual) ----

    public function descontosPdv(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            DescontoPdv::where('empresa_id', $empresaAtual->id)->orderBy('descricao')->get()
        );
    }

    public function criarDescontoPdv(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'percentual' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'aplica_em' => ['required', 'in:produtos,visitas'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $desconto = DescontoPdv::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($desconto, 201);
    }

    public function atualizarDescontoPdv(Request $request, string $empresa, int $descontoId)
    {
        $this->exigirAdmin($request);

        $desconto = DescontoPdv::findOrFail($descontoId);

        $dados = $request->validate([
            'descricao' => ['sometimes', 'string', 'max:255'],
            'percentual' => ['sometimes', 'numeric', 'min:0.01', 'max:100'],
            'aplica_em' => ['sometimes', 'in:produtos,visitas'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $desconto->update($dados);

        return response()->json($desconto->fresh());
    }

    // ---- Identidade visual da loja pública ----

    public function configLoja(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json($empresaAtual->only(['segmento', 'logo_url', 'cor_primaria']));
    }

    public function atualizarConfigLoja(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'segmento' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cor_primaria' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        unset($dados['logo']);
        if ($request->hasFile('logo')) {
            $dados['logo_url'] = $this->armazenarImagemEmpresa($request->file('logo'));
        }

        $empresaAtual = $request->attributes->get('empresaAtual');
        $empresaAtual->update($dados);

        return response()->json($empresaAtual->fresh()->only(['segmento', 'logo_url', 'cor_primaria']));
    }

    // ---- Pedidos da loja virtual (acompanhamento de envio/retirada) ----

    public function pedidosLoja(Request $request, string $empresa)
    {
        $filtros = $request->validate([
            'status_envio' => ['nullable', 'string', 'in:a_separar,enviado,entregue'],
            'status_pagamento' => ['nullable', 'string', 'max:20'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        // Só pedidos do site com produto físico - visita agendada sozinha
        // não tem o que separar/enviar.
        $pedidos = Venda::where('empresa_id', $empresaAtual->id)
            ->where('canal', 'site')
            ->whereHas('itens', fn ($q) => $q->whereNotNull('produto_id'))
            ->when($filtros['status_envio'] ?? null, fn ($q, $v) => $q->where('status_envio', $v))
            ->when($filtros['status_pagamento'] ?? null, fn ($q, $v) => $q->where('status_pagamento', $v))
            ->with(['cliente', 'nfe', 'itens' => fn ($q) => $q->whereNotNull('produto_id'), 'itens.produto', 'itens.produtoVariacao'])
            ->orderByDesc('data_venda')
            ->limit(200)
            ->get();

        return response()->json($pedidos->map(function (Venda $v) {
            // Pedido antigo (antes do frete) não tem endereço guardado na
            // venda - cai no endereço atual do cadastro do cliente.
            $endereco = $v->endereco_entrega ?? ($v->cliente?->logradouro ? $v->cliente->only([
                'cep', 'logradouro', 'numero', 'bairro', 'municipio', 'uf',
            ]) : null);

            return [
                'id' => $v->id,
                'data_venda' => $v->data_venda,
                'status_pagamento' => $v->status_pagamento,
                'valor_total' => $v->valor_total,
                'valor_frete' => $v->valor_frete,
                'tipo_entrega' => $v->tipo_entrega,
                'status_envio' => $v->status_envio,
                'codigo_rastreio' => $v->codigo_rastreio,
                'nfe' => $v->nfe?->only(['id', 'numero', 'status', 'ambiente']),
                'endereco_entrega' => $endereco,
                'cliente' => $v->cliente?->only(['nome', 'cpf_cnpj', 'email', 'telefone']),
                'itens' => $v->itens->map(fn ($i) => [
                    'quantidade' => $i->quantidade,
                    'nome' => $i->produto?->nome,
                    'tamanho' => $i->produtoVariacao?->tamanho,
                    'composicao' => $i->composicao,
                    'valor_total' => $i->valor_total,
                ])->values(),
            ];
        }));
    }

    public function atualizarEnvioPedidoLoja(Request $request, string $empresa, int $vendaId)
    {
        $dados = $request->validate([
            'status_envio' => ['required', 'string', 'in:a_separar,enviado,entregue'],
            'codigo_rastreio' => ['nullable', 'string', 'max:60'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $venda = Venda::where('empresa_id', $empresaAtual->id)
            ->where('canal', 'site')
            ->whereNotNull('tipo_entrega')
            ->findOrFail($vendaId);

        $jaEstavaEnviado = $venda->status_envio === 'enviado';

        $venda->update([
            'status_envio' => $dados['status_envio'],
            'codigo_rastreio' => $venda->tipo_entrega === 'entrega' ? ($dados['codigo_rastreio'] ?? null) : null,
        ]);

        // Avisa o cliente só na virada para "enviado" (não a cada salvar) -
        // evita mensagem repetida se o lojista só ajustar o rastreio.
        if ($dados['status_envio'] === 'enviado' && ! $jaEstavaEnviado) {
            EnviarPedidoEnviadoJob::dispatch($venda->id);
        }

        return response()->json($venda->fresh()->only(['id', 'status_envio', 'codigo_rastreio']));
    }

    // ---- Frete da loja pública (tabela por UF, frete grátis e retirada) ----

    public function configFrete(Request $request, string $empresa)
    {
        return response()->json($this->payloadConfigFrete($request->attributes->get('empresaAtual')));
    }

    public function atualizarConfigFrete(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'frete_gratis_acima' => ['nullable', 'numeric', 'min:0'],
            'permite_retirada' => ['required', 'boolean'],
            'instrucoes_retirada' => ['nullable', 'string', 'max:500'],
            'regras' => ['present', 'array'],
            'regras.*.uf' => ['nullable', 'string', 'size:2'],
            'regras.*.valor' => ['required', 'numeric', 'min:0'],
            'regras.*.prazo_dias' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $ufs = collect($dados['regras'])->map(fn ($r) => isset($r['uf']) ? strtoupper($r['uf']) : null);
        abort_if($ufs->count() !== $ufs->unique()->count(), 422, 'Há UF repetida na tabela de frete.');

        $empresaAtual = $request->attributes->get('empresaAtual');

        DB::transaction(function () use ($dados, $empresaAtual) {
            $empresaAtual->update([
                'frete_gratis_acima' => $dados['frete_gratis_acima'] ?? null,
                'permite_retirada' => $dados['permite_retirada'],
                'instrucoes_retirada' => $dados['instrucoes_retirada'] ?? null,
            ]);

            FreteRegra::query()->delete();

            foreach ($dados['regras'] as $regra) {
                FreteRegra::create([
                    'empresa_id' => $empresaAtual->id,
                    'uf' => isset($regra['uf']) ? strtoupper($regra['uf']) : null,
                    'valor' => $regra['valor'],
                    'prazo_dias' => $regra['prazo_dias'] ?? null,
                ]);
            }
        });

        return response()->json($this->payloadConfigFrete($empresaAtual->fresh()));
    }

    private function payloadConfigFrete(Empresa $empresa): array
    {
        return [
            'frete_gratis_acima' => $empresa->frete_gratis_acima,
            'permite_retirada' => $empresa->permite_retirada,
            'instrucoes_retirada' => $empresa->instrucoes_retirada,
            'regras' => FreteRegra::orderByRaw('uf is null')->orderBy('uf')->get(['uf', 'valor', 'prazo_dias']),
        ];
    }

    // ---- Parâmetros operacionais (estoque, PDV, etc.) ----

    public function configOperacional(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json($empresaAtual->only(['estoque_permite_negativo', 'pdv_impressao_direta']));
    }

    public function atualizarConfigOperacional(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $dados = $request->validate([
            'estoque_permite_negativo' => ['required', 'boolean'],
            'pdv_impressao_direta' => ['required', 'boolean'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $empresaAtual->update($dados);

        return response()->json($empresaAtual->fresh()->only(['estoque_permite_negativo', 'pdv_impressao_direta']));
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
            'gateway' => ['required', 'in:mercadopago,pagseguro,cielo,stone'],
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
