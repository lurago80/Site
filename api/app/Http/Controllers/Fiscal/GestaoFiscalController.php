<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\DocumentoFiscal;
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
