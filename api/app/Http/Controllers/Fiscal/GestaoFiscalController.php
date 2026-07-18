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
 * reimpressão, importação de venda não fiscal, relatórios e exportação
 * de XMLs/planilha para o contador.
 *
 * TODO: hoje protegido só pelo middleware 'tenant' (slug da empresa na
 * URL), igual à loja pública - ainda não existe tela de login do
 * sistema interno. Antes de produção isso precisa ficar atrás de
 * autenticação real (ver Escopo v2, seção 2.2).
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
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $documentos = $this->exportacaoFiscalService->relatorio(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
            $filtros['status'] ?? null,
        );

        return response()->json($documentos->map(fn (DocumentoFiscal $d) => [
            'id' => $d->id,
            'modelo' => $d->modelo,
            'serie' => $d->serie,
            'numero' => $d->numero,
            'status' => $d->status,
            'chave_acesso' => $d->chave_acesso,
            'total' => $d->total,
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

    public function reimprimir(Request $request, string $empresa, int $documentoId)
    {
        $documento = DocumentoFiscal::with(['itens.produto', 'venda.cliente', 'empresa'])->findOrFail($documentoId);

        return view('fiscal.cupom', ['documento' => $documento]);
    }

    public function exportarXmls(Request $request, string $empresa)
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $caminho = $this->exportacaoFiscalService->exportarXmlsZip(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
        );

        return Response::download($caminho)->deleteFileAfterSend();
    }

    public function exportarRelatorioContador(Request $request, string $empresa)
    {
        $filtros = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $caminho = $this->exportacaoFiscalService->exportarRelatorioContadorCsv(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
        );

        return Response::download($caminho)->deleteFileAfterSend();
    }
}
