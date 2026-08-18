<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\Empresa;
use App\Models\Venda;
use App\Services\Fiscal\EmissaoFiscalService;
use App\Services\Fiscal\ExportacaoFiscalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Painel de gestão fiscal: cancelamento, inutilização de numeração,
 * reimpressão, importação de venda não fiscal (ou de NFC-e para NFe),
 * relatórios e exportação de XMLs/planilha para o contador.
 *
 * Cobre NFC-e (modelo 65) e NFe (modelo 55) na mesma tela/mesmas rotas -
 * a maioria das ações é agnóstica a modelo (cancelar, inutilizar,
 * reimprimir, exportar); só a importação e o relatório aceitam um
 * filtro/parâmetro `modelo` explícito.
 *
 * Protegido por login (middleware 'auth', ver routes/web.php e
 * App\Http\Controllers\Auth\LoginController) - a empresa do contexto
 * vem sempre do usuário autenticado, nunca do slug da URL.
 */
class GestaoFiscalController extends Controller
{
    public function __construct(
        private readonly EmissaoFiscalService $emissaoFiscalService,
        private readonly ExportacaoFiscalService $exportacaoFiscalService,
    ) {}

    public function relatorio(Request $request, string $empresa)
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'modelo' => ['nullable', 'integer', 'in:55,65'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $documentos = $this->exportacaoFiscalService->relatorio(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
            $filtros['status'] ?? null,
            $filtros['modelo'] ?? null,
        );

        return response()->json($documentos->map(fn (DocumentoFiscal $d) => [
            'id' => $d->id,
            'modelo' => $d->modelo,
            'serie' => $d->serie,
            'numero' => $d->numero,
            'status' => $d->status,
            'chave_acesso' => $d->chave_acesso,
            'total' => $d->total,
            'documento_fiscal_origem_id' => $d->documento_fiscal_origem_id,
            'created_at' => $d->created_at,
        ]));
    }

    public function vendasNaoFiscais(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $vendas = Venda::where('empresa_id', $empresaAtual->id)
            ->where('tipo_doc', 'nao_fiscal')
            ->with('cliente')
            ->orderByDesc('data_venda')
            ->get();

        return response()->json($vendas);
    }

    /**
     * NFC-e autorizadas que ainda não têm uma NFe de regularização
     * vinculada - candidatas a "importar para NFe" (CFOP 5929/6929).
     */
    public function nfcesDisponiveisParaNfe(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $documentos = DocumentoFiscal::where('empresa_id', $empresaAtual->id)
            ->where('modelo', 65)
            ->where('status', 'autorizada')
            ->whereDoesntHave('regularizacoes')
            ->with('venda.cliente')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($documentos->map(fn (DocumentoFiscal $d) => [
            'id' => $d->id,
            'numero' => $d->numero,
            'total' => $d->total,
            'created_at' => $d->created_at,
            'cliente' => $d->venda?->cliente?->nome,
            'cliente_completo' => $d->venda?->cliente !== null
                && ! empty($d->venda->cliente->uf)
                && ! empty($d->venda->cliente->logradouro),
        ]));
    }

    /**
     * Documentos autorizados (venda ou regularização de NFC-e) elegíveis
     * para gerar uma NFe de devolução (cliente devolvendo à empresa).
     */
    public function documentosElegiveisDevolucao(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $documentos = DocumentoFiscal::where('empresa_id', $empresaAtual->id)
            ->where('status', 'autorizada')
            ->whereIn('tipo_operacao', ['venda', 'regularizacao_nfce'])
            ->with('venda.cliente')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($documentos->map(fn (DocumentoFiscal $d) => [
            'id' => $d->id,
            'modelo' => $d->modelo,
            'numero' => $d->numero,
            'total' => $d->total,
            'created_at' => $d->created_at,
            'cliente' => $d->venda?->cliente?->nome,
        ]));
    }

    /**
     * Itens da venda de origem com quantidade vendida, já devolvida e
     * disponível para uma nova devolução parcial.
     */
    public function itensDisponiveisDevolucao(Request $request, string $empresa, int $documentoId)
    {
        $documento = DocumentoFiscal::with('venda.itens.produto')
            ->where('status', 'autorizada')
            ->whereIn('tipo_operacao', ['venda', 'regularizacao_nfce'])
            ->findOrFail($documentoId);

        if ($documento->venda === null) {
            return response()->json(['message' => 'Documento sem venda associada.'], 422);
        }

        $itens = $documento->venda->itens->map(function ($itemVenda) {
            $quantidadeJaDevolvida = (float) DocumentoFiscalItem::query()
                ->where('item_venda_id', $itemVenda->id)
                ->whereHas('documentoFiscal', fn ($query) => $query
                    ->where('tipo_operacao', 'devolucao')
                    ->whereNotIn('status', ['cancelada', 'rejeitada']))
                ->sum('quantidade');

            return [
                'item_venda_id' => $itemVenda->id,
                'produto' => $itemVenda->produto?->nome,
                'quantidade_vendida' => (float) $itemVenda->quantidade,
                'quantidade_ja_devolvida' => $quantidadeJaDevolvida,
                'quantidade_disponivel' => (float) $itemVenda->quantidade - $quantidadeJaDevolvida,
            ];
        });

        return response()->json($itens);
    }

    public function emitirDevolucao(Request $request, string $empresa, int $documentoId)
    {
        $dados = $request->validate([
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.item_venda_id' => ['required', 'integer', 'exists:itens_venda,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ]);

        $documentoOrigem = DocumentoFiscal::findOrFail($documentoId);

        try {
            $documento = $this->emissaoFiscalService->emitirDevolucao($documentoOrigem, $dados['itens']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento, 201);
    }

    /**
     * Compras confirmadas elegíveis para gerar uma NFe de devolução ao
     * fornecedor.
     */
    public function comprasElegiveisDevolucao(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $compras = Compra::where('empresa_id', $empresaAtual->id)
            ->where('status', 'confirmada')
            ->with('fornecedor')
            ->orderByDesc('data_entrada')
            ->get();

        return response()->json($compras->map(fn (Compra $c) => [
            'id' => $c->id,
            'numero_nota' => $c->numero_nota,
            'total' => $c->valor_total,
            'data_entrada' => $c->data_entrada,
            'fornecedor' => $c->fornecedor?->razao_social,
        ]));
    }

    /**
     * Itens da compra com quantidade comprada, já devolvida e disponível
     * para uma nova devolução parcial ao fornecedor.
     */
    public function itensCompraDisponiveisDevolucao(Request $request, string $empresa, int $compraId)
    {
        $compra = Compra::with('itens.produto')
            ->where('status', 'confirmada')
            ->findOrFail($compraId);

        $itens = $compra->itens->map(function ($itemCompra) {
            $quantidadeJaDevolvida = (float) DocumentoFiscalItem::query()
                ->where('item_compra_id', $itemCompra->id)
                ->whereHas('documentoFiscal', fn ($query) => $query
                    ->where('tipo_operacao', 'devolucao')
                    ->where('direcao_devolucao', 'empresa_para_fornecedor')
                    ->whereNotIn('status', ['cancelada', 'rejeitada']))
                ->sum('quantidade');

            return [
                'item_compra_id' => $itemCompra->id,
                'produto' => $itemCompra->produto?->nome ?? $itemCompra->descricao_xml,
                'quantidade_comprada' => (float) $itemCompra->quantidade,
                'quantidade_ja_devolvida' => $quantidadeJaDevolvida,
                'quantidade_disponivel' => (float) $itemCompra->quantidade - $quantidadeJaDevolvida,
            ];
        });

        return response()->json($itens);
    }

    public function emitirDevolucaoFornecedor(Request $request, string $empresa, int $compraId)
    {
        $dados = $request->validate([
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.item_compra_id' => ['required', 'integer', 'exists:itens_compra,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ]);

        $compra = Compra::findOrFail($compraId);

        try {
            $documento = $this->emissaoFiscalService->emitirDevolucaoFornecedor($compra, $dados['itens']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento, 201);
    }

    public function cancelar(Request $request, string $empresa, int $documentoId)
    {
        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $documento = DocumentoFiscal::findOrFail($documentoId);

        try {
            $documento = $this->emissaoFiscalService->cancelar($documento, $dados['justificativa']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento);
    }

    public function inutilizar(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'modelo' => ['required', 'integer', 'in:55,65'],
            'serie' => ['required', 'string', 'max:3'],
            'numero_inicial' => ['required', 'integer', 'min:1'],
            'numero_final' => ['required', 'integer', 'min:1'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $registro = $this->emissaoFiscalService->inutilizar(
                $empresaAtual,
                $dados['modelo'],
                $dados['serie'],
                $dados['numero_inicial'],
                $dados['numero_final'],
                $dados['justificativa'],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($registro, 201);
    }

    public function importarVendaNaoFiscal(Request $request, string $empresa, int $vendaId)
    {
        $dados = $request->validate([
            'modelo' => ['required', 'integer', 'in:55,65'],
        ]);

        $venda = Venda::findOrFail($vendaId);

        try {
            $documento = $this->emissaoFiscalService->importarVendaNaoFiscal($venda, $dados['modelo']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento, 201);
    }

    /**
     * Gera uma NFe (modelo 55) a partir de uma NFC-e já autorizada,
     * usando CFOP 5929 (mesmo estado) ou 6929 (interestadual) - ver
     * Fiscal\CfopResolver e EmissaoFiscalService::importarVendaNfce().
     */
    public function importarVendaNfce(Request $request, string $empresa, int $documentoNfceId)
    {
        $documentoNfce = DocumentoFiscal::findOrFail($documentoNfceId);

        try {
            $documento = $this->emissaoFiscalService->importarVendaNfce($documentoNfce);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento, 201);
    }

    public function reimprimir(Request $request, string $empresa, int $documentoId)
    {
        $documento = DocumentoFiscal::with(['itens.produto', 'venda.cliente', 'empresa'])->findOrFail($documentoId);

        $view = $documento->modelo === 55 ? 'fiscal.nfe' : 'fiscal.cupom';

        return view($view, ['documento' => $documento]);
    }

    public function exportarXmls(Request $request, string $empresa)
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'modelo' => ['nullable', 'integer', 'in:55,65'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $caminho = $this->exportacaoFiscalService->exportarXmlsZip(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
            $filtros['modelo'] ?? null,
        );

        return Response::download($caminho)->deleteFileAfterSend();
    }

    public function exportarRelatorioContador(Request $request, string $empresa)
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'modelo' => ['nullable', 'integer', 'in:55,65'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $caminho = $this->exportacaoFiscalService->exportarRelatorioContadorCsv(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
            $filtros['modelo'] ?? null,
        );

        return Response::download($caminho)->deleteFileAfterSend();
    }
}
