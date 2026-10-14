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
use App\Services\Vendas\LancamentoVendaService;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'custo_visita' => ['nullable', 'numeric', 'min:0'],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('vendedores', 'id')->where('empresa_id', $empresaAtual->id)],
            'atendente_id' => ['nullable', 'integer', Rule::exists('atendentes', 'id')->where('empresa_id', $empresaAtual->id)],
        ]);

        // Sem custo informado, usa o custo padrão por visitante da empresa (parâmetros).
        $dados['custo_visita'] = $dados['custo_visita'] ?? (float) $empresaAtual->custo_visita_padrao;

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
            'custo_visita' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:aberta,lotada,cancelada'],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('vendedores', 'id')->where('empresa_id', $empresaAtual->id)],
            'atendente_id' => ['nullable', 'integer', Rule::exists('atendentes', 'id')->where('empresa_id', $empresaAtual->id)],
        ]);

        if (array_key_exists('custo_visita', $dados) && $dados['custo_visita'] === null) {
            unset($dados['custo_visita']);
        }

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
                ->with(['fornecedor', 'grupo', 'classTrib', 'creditoPresumido', 'variacoes.produtoVinculado:id,nome,estoque_atual'])
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

        return response()->json(
            $produto->variacoes()->with('produtoVinculado:id,nome,estoque_atual')->orderBy('tamanho')->get()
        );
    }

    public function criarVariacaoProduto(Request $request, string $empresa, int $produtoId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $produto = Produto::where('empresa_id', $empresaAtual->id)->findOrFail($produtoId);

        $dados = $request->validate([
            'tamanho' => ['required', 'string', 'max:40'],
            'estoque_atual' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
            'produto_vinculado_id' => ['nullable', 'integer'],
        ]);

        $this->validarProdutoVinculado($empresaAtual->id, $produto, $dados['produto_vinculado_id'] ?? null);

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
            'produto_vinculado_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        if (array_key_exists('produto_vinculado_id', $dados)) {
            $this->validarProdutoVinculado($empresaAtual->id, Produto::findOrFail($produtoId), $dados['produto_vinculado_id']);
        }

        $variacao->update($dados);

        return response()->json($variacao->fresh()->load('produtoVinculado:id,nome,estoque_atual'));
    }

    /**
     * Variação "vinculada": o estoque, a baixa e o cadastro fiscal passam a ser do
     * produto vinculado (ex.: a opção PILSEN da CERVEJA ARTESANAL usa a CERVEJA PILSEN).
     */
    private function validarProdutoVinculado(int $empresaId, Produto $pai, ?int $vinculadoId): void
    {
        if ($vinculadoId === null) {
            return;
        }

        $vinculado = Produto::where('empresa_id', $empresaId)->find($vinculadoId);

        abort_if($vinculado === null, 422, 'Produto vinculado não encontrado.');
        abort_if($vinculado->id === $pai->id, 422, 'O produto não pode ser vinculado a ele mesmo.');
        abort_if($vinculado->eh_kit, 422, 'Um kit não pode ser vinculado a uma variação.');
        abort_if(
            $vinculado->variacoes()->exists(),
            422,
            'O produto vinculado não pode ter variações próprias: é o estoque dele que será usado.'
        );
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
            'kit_total_escolhas' => ['nullable', 'integer', 'min:1', 'max:99'],
            'componentes' => ['present', 'array'],
            'componentes.*.tipo' => ['required', 'string', 'in:fixo,escolha'],
            'componentes.*.produto_id' => ['required', 'integer'],
            'componentes.*.quantidade' => ['required', 'integer', 'min:1', 'max:99'],
            'componentes.*.grupo' => ['nullable', 'string', 'max:40'],
        ]);

        // grupo só faz sentido em item à escolha; em branco = o produto é o seu próprio grupo
        $dados['componentes'] = array_map(function (array $c) {
            $grupo = trim((string) ($c['grupo'] ?? ''));
            $c['grupo'] = $c['tipo'] === 'escolha' && $grupo !== '' ? $grupo : null;

            return $c;
        }, $dados['componentes']);

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
                    $componente['tipo'] === 'fixo' && $item->variacoes_ativas_count > 0,
                    422,
                    "\"{$item->nome}\" tem variações - use o tipo \"escolha\" para ele."
                );
            }

            if (! empty($dados['kit_total_escolhas'])) {
                // teto de cada grupo (itens com o mesmo grupo dividem o limite) somado
                $tetoEscolhas = collect($dados['componentes'])->where('tipo', 'escolha')
                    ->groupBy(fn ($c) => $c['grupo'] !== null ? 'g:'.mb_strtolower($c['grupo']) : 'p:'.$c['produto_id'])
                    ->sum(fn ($itens) => $itens->max('quantidade'));

                abort_if(
                    $tetoEscolhas < $dados['kit_total_escolhas'],
                    422,
                    "O total de itens à escolha ({$dados['kit_total_escolhas']}) é maior que a soma dos limites dos itens à escolha ({$tetoEscolhas})."
                );
            }
        }

        DB::transaction(function () use ($dados, $produto, $empresaAtual) {
            $produto->update([
                'eh_kit' => $dados['eh_kit'],
                'kit_total_escolhas' => $dados['eh_kit'] ? ($dados['kit_total_escolhas'] ?? null) : null,
            ]);
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
                    'grupo' => $componente['grupo'],
                ]);
            }
        });

        return response()->json($this->payloadKit($produto->fresh()));
    }

    private function payloadKit(Produto $produto): array
    {
        return [
            'eh_kit' => $produto->eh_kit,
            'kit_total_escolhas' => $produto->kit_total_escolhas,
            'componentes' => $produto->componentes()->with('produto:id,nome')->get()->map(fn ($c) => [
                'tipo' => $c->tipo,
                'produto_id' => $c->produto_id,
                'nome' => $c->produto?->nome,
                'quantidade' => $c->quantidade,
                'grupo' => $c->grupo,
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
     * Exclui o produto quando não há movimento; com movimento (vendas, compras,
     * notas fiscais, agenda ou uso como componente de kit) só desativa - assim o
     * histórico fica intacto e o produto sai da loja e do PDV.
     */
    public function excluirProduto(Request $request, string $empresa, int $produtoId)
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem excluir produtos.');

        $empresaAtual = $request->attributes->get('empresaAtual');
        $produto = Produto::where('empresa_id', $empresaAtual->id)->findOrFail($produtoId);

        $motivo = $produto->motivoDeNaoPoderExcluir();

        if ($motivo === null) {
            try {
                // variações e a composição do próprio kit saem em cascata
                DB::transaction(fn () => $produto->delete());

                return response()->json(['acao' => 'excluido', 'message' => 'Produto excluído.']);
            } catch (\Illuminate\Database\QueryException $e) {
                // 23503 = violação de chave estrangeira: surgiu um vínculo entre a checagem e a exclusão
                if ($e->getCode() !== '23503') {
                    throw $e;
                }

                $motivo = 'vínculo com outros registros';
            }
        }

        $jaDesativado = ! $produto->ativo;
        $produto->update(['ativo' => false]);

        return response()->json([
            'acao' => 'desativado',
            'motivo' => $motivo,
            'message' => $jaDesativado
                ? "Produto com movimento ({$motivo}): não pode ser excluído e já estava desativado."
                : "Produto com movimento ({$motivo}): não pode ser excluído, então foi desativado.",
        ]);
    }

    /**
     * Salva o arquivo de imagem enviado no disco público (storage/app/public/produtos)
     * e devolve a URL acessível publicamente para gravar em imagem_url.
     */
    private function armazenarImagemProduto(\Illuminate\Http\UploadedFile $arquivo): string
    {
        $caminho = app(\App\Services\Imagens\RedimensionadorImagem::class)->armazenar($arquivo, 'produtos');

        return \Illuminate\Support\Facades\Storage::disk('public')->url($caminho);
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

        $vendedores = Vendedor::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get();

        // A chave PIX é dado financeiro do vendedor: só o administrador vê.
        return response()->json(
            $request->user()->perfil === 'admin' ? $vendedores : $vendedores->each->makeHidden('chave_pix')
        );
    }

    public function criarVendedor(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'chave_pix' => ['nullable', 'string', 'max:77'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $dados['percentual_comissao'] ??= 5;
        $dados['chave_pix'] = $this->normalizarChavePix($dados['chave_pix'] ?? null);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $vendedor = Vendedor::create($dados + ['empresa_id' => $empresaAtual->id, 'ativo' => true]);

        return response()->json($vendedor, 201);
    }

    public function atualizarVendedor(Request $request, string $empresa, int $vendedorId)
    {
        $this->exigirGerente($request);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $vendedor = Vendedor::where('empresa_id', $empresaAtual->id)->findOrFail($vendedorId);

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'chave_pix' => ['sometimes', 'nullable', 'string', 'max:77'],
            'percentual_comissao' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('chave_pix', $dados)) {
            $dados['chave_pix'] = $this->normalizarChavePix($dados['chave_pix']);
        }

        $vendedor->update($dados);

        return response()->json($vendedor->fresh());
    }

    /** Tira espaços das pontas; vazio vira nulo (sem chave cadastrada). */
    private function normalizarChavePix(?string $chave): ?string
    {
        $chave = trim((string) $chave);

        return $chave === '' ? null : $chave;
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
        $this->exigirGerente($request);

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
        $this->exigirGerente($request);

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
    /**
     * Vendas com desconto ou cortesia no período (data da venda, em Brasília):
     * valor bruto, desconto, valor líquido, atendente e o motivo informado no PDV.
     */
    public function relatorioDescontos(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $fuso = 'America/Sao_Paulo';

        $vendas = \App\Models\Venda::with(['atendente:id,nome', 'descontoPdv:id,descricao', 'cupom:id,codigo'])
            ->where('status_pagamento', '!=', 'cancelado')
            ->whereNull('cancelada_em')
            ->where(fn ($q) => $q->where('valor_desconto', '>', 0)->orWhere('cortesia', true))
            ->when($this->canalFiltro($dados), fn ($q, $c) => $q->where('canal', $c))
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('data_venda', '>=', \Illuminate\Support\Carbon::parse($d, $fuso)->startOfDay()->utc()))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data_venda', '<=', \Illuminate\Support\Carbon::parse($d, $fuso)->endOfDay()->utc()))
            ->orderBy('data_venda')
            ->orderBy('id')
            ->get();

        $linhas = $vendas->map(function (\App\Models\Venda $v) use ($fuso) {
            $desconto = (float) $v->valor_desconto;
            $liquido = (float) $v->valor_total;
            $data = $v->data_venda->copy()->setTimezone($fuso);

            return [
                'id' => $v->id,
                'data' => $data->format('d/m/Y'),
                'hora' => $data->format('H:i'),
                'canal' => $v->canal,
                'tipo' => $v->cortesia ? 'Cortesia' : ($v->descontoPdv?->descricao ?? ($v->cupom ? 'Cupom '.$v->cupom->codigo : 'Desconto')),
                'cortesia' => (bool) $v->cortesia,
                'valor_total' => round($liquido + $desconto, 2),
                'valor_desconto' => round($desconto, 2),
                'valor_liquido' => round($liquido, 2),
                'atendente' => $v->atendente?->nome,
                'motivo' => $v->motivo_desconto,
            ];
        })->values();

        return response()->json([
            'linhas' => $linhas,
            'totais' => [
                'vendas' => $linhas->count(),
                'valor_total' => round($linhas->sum('valor_total'), 2),
                'valor_desconto' => round($linhas->sum('valor_desconto'), 2),
                'valor_liquido' => round($linhas->sum('valor_liquido'), 2),
            ],
        ]);
    }

    /** Vendas canceladas no período (data do cancelamento, em Brasília): quem cancelou, quando e o motivo. */
    public function relatorioCancelamentos(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $fuso = 'America/Sao_Paulo';

        $vendas = Venda::with(['canceladaPor:id,name', 'formaPagamento:id,descricao', 'documentoFiscal'])
            ->whereNotNull('cancelada_em')
            ->when($this->canalFiltro($dados), fn ($q, $c) => $q->where('canal', $c))
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('cancelada_em', '>=', \Illuminate\Support\Carbon::parse($d, $fuso)->startOfDay()->utc()))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('cancelada_em', '<=', \Illuminate\Support\Carbon::parse($d, $fuso)->endOfDay()->utc()))
            ->orderBy('cancelada_em')
            ->get();

        $linhas = $vendas->map(function (Venda $v) use ($fuso) {
            $cancelamento = $v->cancelada_em->copy()->setTimezone($fuso);

            return [
                'id' => $v->id,
                'data_cancelamento' => $cancelamento->format('d/m/Y'),
                'hora_cancelamento' => $cancelamento->format('H:i'),
                'data_venda' => $v->data_venda->copy()->setTimezone($fuso)->format('d/m/Y H:i'),
                'canal' => $v->canal,
                'valor_total' => round((float) $v->valor_total + (float) $v->valor_desconto, 2),
                'forma_pagamento' => $v->formaPagamento?->descricao,
                'nota_fiscal' => $v->documentoFiscal ? ('NFC-e '.$v->documentoFiscal->numero) : null,
                'cancelado_por' => $v->canceladaPor?->name,
                'motivo' => $v->motivo_cancelamento,
            ];
        })->values();

        return response()->json([
            'linhas' => $linhas,
            'totais' => ['vendas' => $linhas->count(), 'valor_total' => round($linhas->sum('valor_total'), 2)],
        ]);
    }

    public function relatorioAtendentes(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
            'tipo' => ['nullable', 'in:todos,produtos,visitacoes'],
            'pessoa_id' => ['nullable', 'integer'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            $this->relatorioVendasPorPessoa('atendente_id', Atendente::class, $empresaAtual, $dados)->values()
        );
    }

    /** Todos os produtos e serviços (visitações) vendidos por cada atendente no período - uma linha por item. */
    public function relatorioItensPorAtendente(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
            'tipo' => ['nullable', 'in:todos,produtos,visitacoes'],
            'pessoa_id' => ['nullable', 'integer'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            $this->itensVendidosPorPessoa('atendente_id', Atendente::class, $empresaAtual, $dados)
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
            'canal' => ['nullable', 'in:todos,pdv,site'],
            'tipo' => ['nullable', 'in:todos,produtos,visitacoes'],
            'pessoa_id' => ['nullable', 'integer'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $relatorio = $this->relatorioVendasPorPessoa('vendedor_id', Vendedor::class, $empresaAtual, $dados);

        // A comissão é paga por PIX: o administrador vê a chave ao lado de cada vendedor.
        if ($request->user()->perfil === 'admin') {
            $chaves = Vendedor::where('empresa_id', $empresaAtual->id)->pluck('chave_pix', 'id');
            $relatorio = $relatorio->map(fn (array $linha) => $linha + ['chave_pix' => $chaves[$linha['id']] ?? null]);
        }

        return response()->json($relatorio->values());
    }

    /** Todos os produtos e serviços (visitações) vendidos por cada vendedor no período - uma linha por item. */
    public function relatorioItensPorVendedor(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
            'tipo' => ['nullable', 'in:todos,produtos,visitacoes'],
            'pessoa_id' => ['nullable', 'integer'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            $this->itensVendidosPorPessoa('vendedor_id', Vendedor::class, $empresaAtual, $dados)
        );
    }

    /** Quantos pedidos/visitantes/valor vieram de cada indicação (informada pelo atendente no check-in). */
    public function relatorioIndicacoes(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $linhas = \App\Models\Indicacao::where('empresa_id', $empresaAtual->id)->orderBy('nome')->get()
            ->map(function ($indicacao) use ($dados) {
                $vendas = Venda::where('indicacao_id', $indicacao->id)
                    ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('data_venda', '>=', $d))
                    ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data_venda', '<=', $d.' 23:59:59'));

                return [
                    'id' => $indicacao->id,
                    'nome' => $indicacao->nome,
                    'pedidos' => (clone $vendas)->count(),
                    'visitantes' => (int) ItemVenda::whereNotNull('agenda_visitacao_id')
                        ->whereIn('venda_id', (clone $vendas)->select('id'))->sum('quantidade'),
                    'valor_total' => round((float) (clone $vendas)->sum('valor_total'), 2),
                ];
            })
            ->sortByDesc('pedidos')
            ->values();

        return response()->json($linhas);
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
            ->when($dados['pessoa_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('nome')
            ->get()
            ->map(function ($pessoa) use ($colunaPessoa, $dados, $tipo) {
                $itens = ItemVenda::whereHas('venda', function ($q) use ($colunaPessoa, $pessoa, $dados) {
                    $q->where($colunaPessoa, $pessoa->id)
                        ->where('status_pagamento', '!=', 'cancelado')
                        ->when($this->canalFiltro($dados), fn ($qq, $c) => $qq->where('canal', $c))
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

    /**
     * Todos os produtos e serviços (visitações) vendidos por cada pessoa no período,
     * uma linha por item - para o relatório detalhado de vendedor/atendente.
     */
    private function itensVendidosPorPessoa(string $colunaPessoa, string $modeloPessoa, Empresa $empresaAtual, array $dados): Collection
    {
        $tipo = $dados['tipo'] ?? 'todos';
        $fuso = 'America/Sao_Paulo';

        $pessoas = $modeloPessoa::where('empresa_id', $empresaAtual->id)
            ->when($dados['pessoa_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('nome')
            ->get()
            ->keyBy('id');

        if ($pessoas->isEmpty()) {
            return collect();
        }

        $itens = ItemVenda::whereHas('venda', function ($q) use ($colunaPessoa, $pessoas, $dados) {
            $q->whereIn($colunaPessoa, $pessoas->keys())
                ->where('status_pagamento', '!=', 'cancelado')
                ->when($this->canalFiltro($dados), fn ($qq, $c) => $qq->where('canal', $c))
                ->when($dados['data_inicio'] ?? null, fn ($qq, $d) => $qq->where('data_venda', '>=', $d))
                ->when($dados['data_fim'] ?? null, fn ($qq, $d) => $qq->where('data_venda', '<=', $d));
        })
            ->when($tipo === 'produtos', fn ($q) => $q->whereNotNull('produto_id'))
            ->when($tipo === 'visitacoes', fn ($q) => $q->whereNotNull('agenda_visitacao_id'))
            ->with(['produto:id,nome', 'produtoVariacao:id,tamanho', 'venda:id,data_venda,'.$colunaPessoa])
            ->get();

        return $itens
            ->groupBy(fn (ItemVenda $i) => $i->venda->{$colunaPessoa})
            ->map(function (Collection $itensDaPessoa, $pessoaId) use ($pessoas, $fuso) {
                return [
                    'pessoa_id' => (int) $pessoaId,
                    'pessoa' => $pessoas[$pessoaId]->nome,
                    'itens' => $itensDaPessoa->map(fn (ItemVenda $i) => [
                        'venda_id' => $i->venda_id,
                        'data' => $i->venda->data_venda->copy()->setTimezone($fuso)->format('d/m/Y H:i'),
                        'produto' => $i->agenda_visitacao_id ? 'Visita/visitação' : ($i->produto?->nome ?? '(produto removido)'),
                        'variacao' => $i->produtoVariacao?->tamanho,
                        'quantidade' => (int) $i->quantidade,
                        'valor_unitario' => round((float) $i->valor_unitario, 2),
                        'valor_total' => round((float) $i->valor_total, 2),
                    ])->values(),
                    'totais' => [
                        'itens' => $itensDaPessoa->count(),
                        'quantidade' => (int) $itensDaPessoa->sum('quantidade'),
                        'valor_total' => round((float) $itensDaPessoa->sum('valor_total'), 2),
                    ],
                ];
            })
            // Pessoas sem venda no período também aparecem, com lista vazia.
            ->union($pessoas->map(fn ($p) => [
                'pessoa_id' => $p->id, 'pessoa' => $p->nome, 'itens' => collect(), 'totais' => ['itens' => 0, 'quantidade' => 0, 'valor_total' => 0],
            ])->keyBy('pessoa_id'))
            ->values()
            ->sortBy('pessoa');
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
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $periodo = fn ($query) => $query
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('vencimento', '>=', $d))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('vencimento', '<=', $d));

        $planos = PlanoContas::where('empresa_id', $empresaAtual->id)->orderBy('codigo')->get();

        $relatorio = $planos->map(function (PlanoContas $plano) use ($periodo, $dados) {
            $query = $plano->tipo === 'despesa'
                ? ContaPagar::where('plano_conta_id', $plano->id)
                : ContaReceber::where('plano_conta_id', $plano->id);

            $query = $periodo($query);

            // Lançamentos automáticos das vendas (002.001, 002.002 e 001.015): já realizados.
            $lancadoVendas = \App\Models\LancamentoPlanoConta::where('plano_conta_id', $plano->id)
                ->when($this->inicioEfetivo($dados['data_inicio'] ?? null), fn ($q, $d) => $q->where('data', '>=', $d))
                ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data', '<=', $d))
                ->sum('valor');

            return [
                'id' => $plano->id,
                'codigo' => $plano->codigo,
                'nome' => $plano->nome,
                'tipo' => $plano->tipo,
                'total' => (clone $query)->sum('valor') + $lancadoVendas,
                'total_pago' => (clone $query)->where('status', 'pago')->sum('valor') + $lancadoVendas,
                'total_em_aberto' => (clone $query)->where('status', 'em_aberto')->sum('valor'),
                // 001.015 já está deduzida da receita: serve só para consulta e não entra em resultado.
                'informativa' => $plano->codigo === LancamentoVendaService::CONTA_DESCONTOS['codigo'],
            ];
        });

        return response()->json($relatorio);
    }

    /**
     * Resultado do período (DRE simples). As receitas 002.001/002.002 já saem das vendas líquidas
     * de custo e de desconto; os descontos (001.015) e o faturamento bruto aparecem só como memo.
     */
    public function relatorioResultado(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $canal = $this->canalFiltro($dados);

        $lancamentos = \App\Models\LancamentoPlanoConta::query()
            ->when($canal, fn ($q, $c) => $q->whereHas('venda', fn ($v) => $v->where('canal', $c)))
            ->when($this->inicioEfetivo($dados['data_inicio'] ?? null), fn ($q, $d) => $q->where('data', '>=', $d))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data', '<=', $d));

        $porOrigem = (clone $lancamentos)->selectRaw('origem, sum(valor) as total')->groupBy('origem')->pluck('total', 'origem');
        $receitaProdutos = round((float) ($porOrigem['receita_produtos'] ?? 0), 2);
        $receitaServicos = round((float) ($porOrigem['receita_servicos'] ?? 0), 2);
        $descontos = round((float) ($porOrigem['desconto'] ?? 0), 2);
        $consumoInterno = round((float) ($porOrigem['consumo_interno'] ?? 0), 2);

        // Memo: faturamento bruto e custo só das vendas com receita (consumo interno/cortesia ficam de fora).
        $itens = \App\Models\ItemVenda::whereIn('venda_id', (clone $lancamentos)->whereIn('origem', ['receita_produtos', 'receita_servicos'])->select('venda_id'));
        $faturamentoBruto = round((float) (clone $itens)->sum('valor_total'), 2);
        $custoTotal = round((float) (clone $itens)->sum('custo_total'), 2);

        // Outras despesas: contas a pagar do período + lançamentos automáticos (ex.: consumo
        // interno), exceto 001.015 (já deduzida da receita).
        $lancadoPorConta = (clone $lancamentos)->where('origem', '!=', 'desconto')
            ->selectRaw('plano_conta_id, sum(valor) as total')->groupBy('plano_conta_id')->pluck('total', 'plano_conta_id');

        $despesas = PlanoContas::where('empresa_id', $empresaAtual->id)
            ->where('tipo', 'despesa')
            ->whereNotIn('codigo', [LancamentoVendaService::CONTA_DESCONTOS['codigo'], LancamentoVendaService::CONTA_CONSUMO_INTERNO['codigo']])
            ->orderBy('codigo')
            ->get()
            ->map(fn (PlanoContas $p) => [
                'codigo' => $p->codigo,
                'nome' => $p->nome,
                'total' => round((float) ContaPagar::where('plano_conta_id', $p->id)
                    ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('vencimento', '>=', $d))
                    ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('vencimento', '<=', $d))
                    ->sum('valor') + ($lancadoPorConta[$p->id] ?? 0), 2),
            ])
            ->filter(fn (array $linha) => $linha['total'] != 0)
            ->values();

        // 001.016 vem dos lançamentos de consumo interno e cortesia (custo dos itens), não de contas a pagar.
        if ($consumoInterno != 0) {
            $despesas = $despesas->push([
                'codigo' => LancamentoVendaService::CONTA_CONSUMO_INTERNO['codigo'],
                'nome' => 'CONSUMO INTERNO / CORTESIAS',
                'total' => $consumoInterno,
            ])->sortBy('codigo')->values();
        }

        $totalReceitas = round($receitaProdutos + $receitaServicos, 2);
        $totalDespesas = round((float) $despesas->sum('total'), 2);

        // Receitas separadas por canal (balcão x loja online), dentro do filtro escolhido.
        $porCanal = (clone $lancamentos)
            ->join('vendas', 'vendas.id', '=', 'lancamentos_plano_contas.venda_id')
            ->whereIn('lancamentos_plano_contas.origem', ['receita_produtos', 'receita_servicos'])
            ->selectRaw('vendas.canal as canal, sum(lancamentos_plano_contas.valor) as total')
            ->groupBy('vendas.canal')
            ->pluck('total', 'canal');

        return response()->json([
            'por_canal' => ['pdv' => round((float) ($porCanal['pdv'] ?? 0), 2), 'site' => round((float) ($porCanal['site'] ?? 0), 2)],
            'receita_produtos' => $receitaProdutos,
            'receita_servicos' => $receitaServicos,
            'total_receitas' => $totalReceitas,
            'despesas' => $despesas,
            'total_despesas' => $totalDespesas,
            'resultado' => round($totalReceitas - $totalDespesas, 2),
            'memo' => [
                'faturamento_bruto' => $faturamentoBruto,
                'custo_total' => $custoTotal,
                'descontos_concedidos' => $descontos,
            ],
        ]);
    }

    // ---- Relatórios de vendas ----

    /**
     * Base das vendas no período (fuso de Brasília), excluindo canceladas.
     * Usada pelos relatórios de vendas, itens vendidos, produtos mais
     * vendidos, curva ABC e lucratividade.
     */
    private function vendasNoPeriodo(array $dados, bool $incluirSemReceita = false)
    {
        $fuso = 'America/Sao_Paulo';
        $dados['data_inicio'] = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        return Venda::where('status_pagamento', '!=', 'cancelado')
            ->whereNull('cancelada_em')
            // Consumo interno e cortesia não são vendas: só o relatório de vendas as lista (fora dos totais).
            ->when(! $incluirSemReceita, fn ($q) => $q
                ->where('cortesia', false)
                ->whereDoesntHave('formaPagamento', fn ($f) => $f->whereRaw("upper(trim(descricao)) = 'CONSUMO INTERNO'")))
            ->when($dados['data_inicio'] ?? null, fn ($q, $d) => $q->where('data_venda', '>=', \Illuminate\Support\Carbon::parse($d, $fuso)->startOfDay()->utc()))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data_venda', '<=', \Illuminate\Support\Carbon::parse($d, $fuso)->endOfDay()->utc()))
            ->when($this->canalFiltro($dados), fn ($q, $c) => $q->where('canal', $c));
    }

    /** Vendas canceladas no período (pela data da venda, não do cancelamento) - informativo, fora dos totais dos relatórios. */
    private function vendasCanceladasNoPeriodo(array $dados)
    {
        $fuso = 'America/Sao_Paulo';
        $dataInicio = $this->inicioEfetivo($dados['data_inicio'] ?? null);

        return Venda::where('status_pagamento', 'cancelado')
            ->when($dataInicio, fn ($q, $d) => $q->where('data_venda', '>=', \Illuminate\Support\Carbon::parse($d, $fuso)->startOfDay()->utc()))
            ->when($dados['data_fim'] ?? null, fn ($q, $d) => $q->where('data_venda', '<=', \Illuminate\Support\Carbon::parse($d, $fuso)->endOfDay()->utc()))
            ->when($this->canalFiltro($dados), fn ($q, $c) => $q->where('canal', $c));
    }

    /** Vendas do período: uma linha por venda, com canal, atendente, vendedor e forma de pagamento. */
    public function relatorioVendas(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $fuso = 'America/Sao_Paulo';

        $vendas = $this->vendasNoPeriodo($dados, incluirSemReceita: true)
            ->with(['atendente:id,nome', 'vendedor:id,nome', 'formaPagamento:id,descricao'])
            ->withCount('itens')
            ->orderBy('data_venda')
            ->orderBy('id')
            ->get();

        $linhas = $vendas->map(function (Venda $v) use ($fuso) {
            $data = $v->data_venda->copy()->setTimezone($fuso);

            return [
                'id' => $v->id,
                'data' => $data->format('d/m/Y'),
                'hora' => $data->format('H:i'),
                'canal' => $v->canal,
                'tipo_doc' => $v->tipo_doc,
                'status_pagamento' => $v->status_pagamento,
                'atendente' => $v->atendente?->nome,
                'vendedor' => $v->vendedor?->nome,
                'forma_pagamento' => $v->formaPagamento?->descricao,
                'itens' => $v->itens_count,
                // Consumo interno / cortesia: aparece com o valor cheio, mas fica fora dos totais.
                'tipo_venda' => $v->tipoSemReceita(),
                'sem_receita' => $v->semReceita(),
                'valor_desconto' => $v->cortesia ? 0.0 : round((float) $v->valor_desconto, 2),
                'valor_total' => round((float) $v->valor_total + ($v->cortesia ? (float) $v->valor_desconto : 0), 2),
            ];
        })->values();

        $normais = $linhas->where('sem_receita', false);
        $semReceita = $linhas->where('sem_receita', true);

        return response()->json([
            'linhas' => $linhas,
            'totais' => [
                'vendas' => $normais->count(),
                'itens' => (int) $normais->sum('itens'),
                'valor_desconto' => round((float) $normais->sum('valor_desconto'), 2),
                'valor_total' => round((float) $normais->sum('valor_total'), 2),
            ],
            // Separação por canal (só vendas com receita): balcão x loja online.
            'por_canal' => collect(['pdv', 'site'])->mapWithKeys(fn ($c) => [$c => [
                'vendas' => $normais->where('canal', $c)->count(),
                'valor_total' => round((float) $normais->where('canal', $c)->sum('valor_total'), 2),
            ]]),
            // Informativo: não está somado em "totais".
            'fora_do_total' => [
                'vendas' => $semReceita->count(),
                'valor_total' => round((float) $semReceita->sum('valor_total'), 2),
            ],
            // Vendas canceladas no período: não entram em nenhum total acima, só informativo.
            'canceladas' => (function () use ($dados) {
                $canceladas = $this->vendasCanceladasNoPeriodo($dados)->get();

                return [
                    'vendas' => $canceladas->count(),
                    'valor_total' => round((float) $canceladas->sum('valor_total'), 2),
                ];
            })(),
        ]);
    }

    /** Itens vendidos no período: uma linha por item de venda (produto/variação, quantidade e valores). */
    public function relatorioItensVendidos(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $fuso = 'America/Sao_Paulo';

        $itens = ItemVenda::whereIn('venda_id', $this->vendasNoPeriodo($dados)->select('id'))
            ->whereNull('agenda_visitacao_id')
            ->with(['produto:id,nome', 'produtoVariacao:id,tamanho', 'venda:id,data_venda'])
            ->orderBy('venda_id')
            ->get();

        $linhas = $itens->map(fn (ItemVenda $i) => [
            'venda_id' => $i->venda_id,
            'data' => $i->venda->data_venda->copy()->setTimezone($fuso)->format('d/m/Y H:i'),
            'produto' => $i->produto?->nome ?? '(produto removido)',
            'variacao' => $i->produtoVariacao?->tamanho,
            'quantidade' => (int) $i->quantidade,
            'valor_unitario' => round((float) $i->valor_unitario, 2),
            'valor_total' => round((float) $i->valor_total, 2),
            'custo_total' => round((float) $i->custo_total, 2),
        ])->values();

        $itensCancelados = ItemVenda::whereIn('venda_id', $this->vendasCanceladasNoPeriodo($dados)->select('id'))
            ->whereNull('agenda_visitacao_id')
            ->get();

        return response()->json([
            'linhas' => $linhas,
            'totais' => [
                'itens' => $linhas->count(),
                'quantidade' => (int) $linhas->sum('quantidade'),
                'valor_total' => round((float) $linhas->sum('valor_total'), 2),
                'custo_total' => round((float) $linhas->sum('custo_total'), 2),
            ],
            // Itens de vendas canceladas no período: não entram nos totais acima, só informativo.
            'cancelados' => [
                'itens' => $itensCancelados->count(),
                'quantidade' => (int) $itensCancelados->sum('quantidade'),
                'valor_total' => round((float) $itensCancelados->sum('valor_total'), 2),
            ],
        ]);
    }

    /** Agrupa os itens do período por produto, somando quantidade e valor vendido. */
    private function itensAgrupadosPorProduto(array $dados): Collection
    {
        return ItemVenda::whereIn('venda_id', $this->vendasNoPeriodo($dados)->select('id'))
            ->whereNull('agenda_visitacao_id')
            ->whereNotNull('produto_id')
            ->with('produto:id,nome')
            ->get()
            ->groupBy('produto_id')
            ->map(fn (Collection $itens) => [
                'produto_id' => $itens->first()->produto_id,
                'produto' => $itens->first()->produto?->nome ?? '(produto removido)',
                'quantidade' => (int) $itens->sum('quantidade'),
                'valor_total' => round((float) $itens->sum('valor_total'), 2),
                'custo_total' => round((float) $itens->sum('custo_total'), 2),
            ])
            ->values();
    }

    /** Ranking de produtos por quantidade vendida no período. */
    public function relatorioProdutosMaisVendidos(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $linhas = $this->itensAgrupadosPorProduto($dados)->sortByDesc('quantidade')->values();

        return response()->json([
            'linhas' => $linhas,
            'totais' => [
                'produtos' => $linhas->count(),
                'quantidade' => (int) $linhas->sum('quantidade'),
                'valor_total' => round((float) $linhas->sum('valor_total'), 2),
            ],
        ]);
    }

    /**
     * Curva ABC por faturamento: produtos ordenados do maior para o menor valor
     * vendido, classificados conforme o percentual acumulado (A até 80%, B até
     * 95%, C no restante).
     */
    public function relatorioCurvaAbc(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $produtos = $this->itensAgrupadosPorProduto($dados)->sortByDesc('valor_total')->values();
        $valorTotalGeral = (float) $produtos->sum('valor_total');

        $acumulado = 0.0;
        $linhas = $produtos->map(function (array $p) use (&$acumulado, $valorTotalGeral) {
            $acumulado += $p['valor_total'];
            $percentualAcumulado = $valorTotalGeral > 0 ? round($acumulado / $valorTotalGeral * 100, 2) : 0;

            return $p + [
                'percentual' => $valorTotalGeral > 0 ? round($p['valor_total'] / $valorTotalGeral * 100, 2) : 0,
                'percentual_acumulado' => $percentualAcumulado,
                'classe' => $percentualAcumulado <= 80 ? 'A' : ($percentualAcumulado <= 95 ? 'B' : 'C'),
            ];
        })->values();

        $canceladas = $this->vendasCanceladasNoPeriodo($dados)->get();

        return response()->json([
            'linhas' => $linhas,
            'totais' => [
                'produtos' => $linhas->count(),
                'valor_total' => round($valorTotalGeral, 2),
                'classe_a' => $linhas->where('classe', 'A')->count(),
                'classe_b' => $linhas->where('classe', 'B')->count(),
                'classe_c' => $linhas->where('classe', 'C')->count(),
            ],
            // Vendas canceladas no período: não entram na curva acima, só informativo.
            'canceladas' => [
                'vendas' => $canceladas->count(),
                'valor_total' => round((float) $canceladas->sum('valor_total'), 2),
            ],
        ]);
    }

    /** Lucratividade por produto no período: receita, custo, margem em R$ e em %. */
    public function relatorioLucratividade(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'canal' => ['nullable', 'in:todos,pdv,site'],
        ]);

        $linhas = $this->itensAgrupadosPorProduto($dados)
            ->map(function (array $p) {
                $margem = round($p['valor_total'] - $p['custo_total'], 2);

                return $p + [
                    'margem' => $margem,
                    'margem_percentual' => $p['valor_total'] > 0 ? round($margem / $p['valor_total'] * 100, 2) : 0,
                ];
            })
            ->sortByDesc('margem')
            ->values();

        $valorTotal = (float) $linhas->sum('valor_total');
        $custoTotal = (float) $linhas->sum('custo_total');
        $margemTotal = round($valorTotal - $custoTotal, 2);

        return response()->json([
            'linhas' => $linhas,
            'totais' => [
                'produtos' => $linhas->count(),
                'valor_total' => round($valorTotal, 2),
                'custo_total' => round($custoTotal, 2),
                'margem' => $margemTotal,
                'margem_percentual' => $valorTotal > 0 ? round($margemTotal / $valorTotal * 100, 2) : 0,
            ],
        ]);
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
            'canal' => ['nullable', 'in:todos,pdv,site'],
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
            'perfil' => ['required', 'in:admin,gerente,caixa,atendente'],
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
            'perfil' => ['sometimes', 'in:admin,gerente,caixa,atendente'],
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
            // Série e último número já emitido de cada documento. O próximo número é "último + 1";
            // série 890-999 é reservada (avulsa/SCAN), então o limite é 889.
            'serie_nfe_atual' => ['sometimes', 'integer', 'between:0,889'],
            'numero_nfe_atual' => ['sometimes', 'integer', 'min:0', 'max:999999999'],
            'serie_nfce_atual' => ['sometimes', 'integer', 'between:0,889'],
            'numero_nfce_atual' => ['sometimes', 'integer', 'min:0', 'max:999999999'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        // a série é guardada como texto (coluna string)
        foreach (['serie_nfe_atual', 'serie_nfce_atual'] as $campoSerie) {
            if (isset($dados[$campoSerie])) {
                $dados[$campoSerie] = (string) $dados[$campoSerie];
            }
        }

        $empresaAtual->update(array_intersect_key($dados, array_flip([
            'uf', 'municipio', 'codigo_ibge_municipio', 'cep', 'logradouro', 'numero', 'bairro',
        ])));

        $config = ConfigFiscal::updateOrCreate(
            ['empresa_id' => $empresaAtual->id],
            array_intersect_key($dados, array_flip([
                'crt', 'inscricao_estadual', 'inscricao_municipal', 'ambiente_ativo', 'csc_nfce', 'id_token_csc', 'pis_cofins_exclui_icms',
                'serie_nfe_atual', 'numero_nfe_atual', 'serie_nfce_atual', 'numero_nfce_atual',
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
        $this->exigirGerente($request);

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
        $this->exigirGerente($request);

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
        $this->exigirGerente($request);

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
        $this->exigirGerente($request);

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
        $this->exigirGerente($request);

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
        $this->exigirGerente($request);

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

        return response()->json($empresaAtual->only(['estoque_permite_negativo', 'pdv_impressao_direta', 'pdv_caixa_pode_cancelar', 'custo_visita_padrao', 'produto_brinde_visita_id', 'operacao_inicio', 'alerta_desconto_percentual', 'alerta_diferenca_caixa', 'alerta_sangria', 'alerta_whatsapp']));
    }

    public function atualizarConfigOperacional(Request $request, string $empresa)
    {
        $this->exigirAdmin($request);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $dados = $request->validate([
            'estoque_permite_negativo' => ['required', 'boolean'],
            'pdv_impressao_direta' => ['required', 'boolean'],
            'pdv_caixa_pode_cancelar' => ['required', 'boolean'],
            'custo_visita_padrao' => ['sometimes', 'numeric', 'min:0'],
            'produto_brinde_visita_id' => ['sometimes', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('produtos', 'id')->where('empresa_id', $empresaAtual->id)],
            'operacao_inicio' => ['sometimes', 'nullable', 'date'],
            'alerta_desconto_percentual' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'alerta_diferenca_caixa' => ['sometimes', 'numeric', 'min:0'],
            'alerta_sangria' => ['sometimes', 'numeric', 'min:0'],
            'alerta_whatsapp' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        // WhatsApp dos alertas: guarda só os dígitos (vazio desliga o envio).
        if (array_key_exists('alerta_whatsapp', $dados)) {
            $dados['alerta_whatsapp'] = preg_replace('/\D/', '', (string) $dados['alerta_whatsapp']) ?: null;
        }

        $empresaAtual->update($dados);

        return response()->json($empresaAtual->fresh()->only(['estoque_permite_negativo', 'pdv_impressao_direta', 'pdv_caixa_pode_cancelar', 'custo_visita_padrao', 'produto_brinde_visita_id', 'operacao_inicio', 'alerta_desconto_percentual', 'alerta_diferenca_caixa', 'alerta_sangria', 'alerta_whatsapp']));
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

    /**
     * Relatórios de vendas ignoram o que veio antes do "início da operação" (parâmetro da empresa):
     * vendas de teste ficam no banco, mas fora dos números. Devolve a data inicial efetiva (Y-m-d).
     */
    private function inicioEfetivo(?string $dataInicio): ?string
    {
        $operacao = request()->attributes->get('empresaAtual')?->operacao_inicio?->toDateString();

        return $operacao !== null && ($dataInicio === null || $dataInicio < $operacao) ? $operacao : $dataInicio;
    }

    // ---- Alertas ao administrador/gerente ----

    /** Alertas recentes (mais novos primeiro) e quantos ainda não foram lidos. Só administrador e gerente. */
    public function alertas(Request $request, string $empresa)
    {
        $this->exigirGerente($request);

        $naoLidas = \App\Models\Alerta::whereNull('lida_em')->count();

        $lista = \App\Models\Alerta::with('usuario:id,name')
            ->when($request->boolean('so_nao_lidas'), fn ($q) => $q->whereNull('lida_em'))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'mensagem' => $a->mensagem,
                'valor' => $a->valor,
                'venda_id' => $a->venda_id,
                'usuario' => $a->usuario?->name,
                'quando' => $a->created_at->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'lida' => $a->lida_em !== null,
                'whatsapp_enviado' => $a->whatsapp_enviado_em !== null,
            ]);

        return response()->json(['nao_lidas' => $naoLidas, 'alertas' => $lista]);
    }

    public function marcarAlertasLidas(Request $request, string $empresa)
    {
        $this->exigirGerente($request);

        $marcadas = \App\Models\Alerta::whereNull('lida_em')->update(['lida_em' => now()]);

        return response()->json(['marcadas' => $marcadas]);
    }

    /** Filtro de canal dos relatórios: 'pdv' (balcão), 'site' (loja online) ou null (os dois). */
    private function canalFiltro(array $dados): ?string
    {
        return in_array($dados['canal'] ?? null, ['pdv', 'site'], true) ? $dados['canal'] : null;
    }

    /** Rotinas do dia a dia: administrador ou gerente. */
    private function exigirGerente(Request $request): void
    {
        abort_unless(in_array($request->user()->perfil, ['admin', 'gerente'], true), 403, 'Apenas administradores e gerentes podem fazer esta alteração.');
    }

    private function exigirAdmin(Request $request): void
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem gerenciar usuários.');
    }
}
