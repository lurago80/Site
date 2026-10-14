<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\Empresa;
use App\Models\Venda;
use App\Services\Fiscal\EmissaoFiscalService;
use App\Services\Fiscal\ExportacaoFiscalService;
use App\Services\Fiscal\OperacoesNfe;
use Illuminate\Http\Request;
use App\Models\ConfigWhatsapp;
use App\Models\Notificacao;
use App\Services\Notificacao\NotificacaoGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;

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

        // "Inutilizada" não existe como status gravado (é uma faixa homologada em numeracao_inutilizada):
        // busca sem filtro de status no banco e filtra pelo status exibido, que já considera a inutilização.
        $statusFiltro = $filtros['status'] ?? null;

        $documentos = $this->exportacaoFiscalService->relatorio(
            $empresaAtual,
            $filtros['data_inicio'] ?? null,
            $filtros['data_fim'] ?? null,
            $statusFiltro === 'inutilizada' ? null : $statusFiltro,
            $filtros['modelo'] ?? null,
        );

        // Número que foi inutilizado na SEFAZ não é mais "rejeitado": exibe como inutilizada.
        $inutilizacoes = \App\Models\NumeracaoInutilizada::where('empresa_id', $empresaAtual->id)
            ->where('status', 'homologada')->get();

        $inutilizacaoDe = fn (DocumentoFiscal $d) => in_array($d->status, ['rejeitada', 'contingencia'], true)
            ? $inutilizacoes->first(fn ($i) => (int) $i->modelo === (int) $d->modelo
                && (string) $i->serie === (string) $d->serie
                && $d->numero >= $i->numero_inicial && $d->numero <= $i->numero_final)
            : null;

        $linhas = $documentos->map(fn (DocumentoFiscal $d) => [
            'id' => $d->id,
            'modelo' => $d->modelo,
            'serie' => $d->serie,
            'numero' => $d->numero,
            'status' => $inutilizacaoDe($d) ? 'inutilizada' : $d->status,
            'chave_acesso' => $d->chave_acesso,
            'motivo' => ($i = $inutilizacaoDe($d)) ? "Numeração inutilizada na SEFAZ (protocolo {$i->protocolo})" : $d->motivo_cancelamento,
            'total' => $d->total,
            'documento_fiscal_origem_id' => $d->documento_fiscal_origem_id,
            'created_at' => $d->created_at,
        ]);

        if ($statusFiltro) {
            $linhas = $linhas->filter(fn (array $l) => $l['status'] === $statusFiltro);
        }

        return response()->json($linhas->values());
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

    /**
     * Opções da tela "Emitir NFe": ambiente em uso (homologação/produção),
     * se o regime da empresa é suportado e os tipos de operação disponíveis.
     */
    public function opcoesNfe(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $config = ConfigFiscal::where('empresa_id', $empresaAtual->id)->first();

        return response()->json([
            'configurado' => $config !== null,
            'ambiente' => $config?->ambiente_ativo,
            'regime_suportado' => $config !== null && in_array((string) $config->crt, OperacoesNfe::CRT_SUPORTADOS, true),
            'tipos' => collect(OperacoesNfe::TIPOS)->map(fn (array $t, string $valor) => [
                'valor' => $valor,
                'rotulo' => $t['rotulo'],
                'natureza' => $t['natureza'],
                'cfop' => $t['cfop'],
                'baixa_estoque' => $t['baixa_estoque'],
                'sem_pagamento' => $t['tpag'] === '90',
            ])->values(),
            'modalidades_frete' => collect(OperacoesNfe::MODALIDADES_FRETE)
                ->map(fn (string $rotulo, int $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
        ]);
    }

    /**
     * NFe direta da retaguarda: venda avulsa, remessa, transferência ou
     * bonificação (ver EmissaoFiscalService::emitirNfeAvulsa).
     */
    public function emitirNfeAvulsa(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', array_keys(OperacoesNfe::TIPOS))],
            'cliente_id' => ['required', 'integer'],
            'natureza_operacao' => ['nullable', 'string', 'max:60'],
            'cfop' => ['nullable', 'regex:/^[56]\d{3}$/'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer'],
            'itens.*.variacao_id' => ['nullable', 'integer'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'itens.*.valor_unitario' => ['nullable', 'numeric', 'min:0'],
            'frete' => ['nullable', 'numeric', 'min:0'],
            'modalidade_frete' => ['nullable', 'integer', 'in:'.implode(',', array_keys(OperacoesNfe::MODALIDADES_FRETE))],
            'transportadora' => ['nullable', 'array'],
            'transportadora.nome' => ['nullable', 'string', 'max:60'],
            'transportadora.documento' => ['nullable', 'string', 'max:18'],
            'transportadora.ie' => ['nullable', 'string', 'max:14'],
            'transportadora.endereco' => ['nullable', 'string', 'max:60'],
            'transportadora.municipio' => ['nullable', 'string', 'max:60'],
            'transportadora.uf' => ['nullable', 'string', 'size:2'],
            'informacoes_adicionais' => ['nullable', 'string', 'max:2000'],
            'baixar_estoque' => ['nullable', 'boolean'],
            'forma_pagamento_id' => ['nullable', 'integer'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        try {
            $documento = $this->emissaoFiscalService->emitirNfeAvulsa($empresaAtual, $dados);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento, 201);
    }

    /**
     * NFe de um pedido pago da loja virtual (produtos + frete do pedido).
     */
    public function emitirNfePedidoLoja(Request $request, string $empresa, int $vendaId)
    {
        $venda = Venda::with('cliente')->findOrFail($vendaId);

        try {
            $documento = $this->emissaoFiscalService->emitirNfePedidoLoja($venda);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($documento, 201);
    }

    public function reimprimir(Request $request, string $empresa, int $documentoId)
    {
        $documento = DocumentoFiscal::with(['itens.produto', 'venda.cliente', 'venda.itens.agendaVisitacao', 'cliente', 'empresa', 'compra.fornecedor'])->findOrFail($documentoId);

        $view = $documento->modelo === 55 ? 'fiscal.nfe' : 'fiscal.cupom';

        return view($view, ['documento' => $documento]);
    }

    /**
     * Envia o documento (link do cupom/DANFE + chave de acesso) ao cliente
     * por WhatsApp ou e-mail. O link é assinado e expira em 90 dias, para
     * abrir sem login no celular do destinatário.
     */
    public function enviar(Request $request, string $empresa, int $documentoId, NotificacaoGatewayFactory $factory)
    {
        $dados = $request->validate([
            'canal' => ['required', 'in:whatsapp,email'],
            'destino' => ['required', 'string', 'max:150'],
        ]);

        $documento = DocumentoFiscal::with(['empresa', 'venda.cliente'])->findOrFail($documentoId);

        if (! in_array($documento->status, ['autorizada', 'cancelada'], true)) {
            return response()->json(['message' => 'Só é possível enviar documentos autorizados.'], 422);
        }

        $tipo = $documento->modelo === 55 ? 'NF-e' : 'NFC-e';
        // Assinatura sobre o caminho relativo + origem do APP_URL: o APP_URL já
        // inclui o ROUTE_PREFIX, então route() absoluto duplicaria o "/app".
        $origem = parse_url((string) config('app.url'), PHP_URL_SCHEME).'://'.parse_url((string) config('app.url'), PHP_URL_HOST)
            .(parse_url((string) config('app.url'), PHP_URL_PORT) ? ':'.parse_url((string) config('app.url'), PHP_URL_PORT) : '');
        $link = $origem.URL::temporarySignedRoute('documento-fiscal.publico', now()->addDays(90), ['documentoId' => $documento->id], absolute: false);
        $razao = $documento->empresa->razao_social;
        $mensagem = "Olá! Segue a {$tipo} nº {$documento->numero} (série {$documento->serie}) de {$razao}, no valor de R$ "
            .number_format($documento->total, 2, ',', '.').".\n\nVisualizar/imprimir: {$link}\n\nChave de acesso: {$documento->chave_acesso}";

        if ($dados['canal'] === 'whatsapp') {
            $telefone = preg_replace('/\D/', '', $dados['destino']);
            if (strlen($telefone) < 10) {
                return response()->json(['message' => 'Informe o celular com DDD.'], 422);
            }
            if (strlen($telefone) <= 11) {
                $telefone = '55'.$telefone;
            }

            $config = ConfigWhatsapp::where('empresa_id', $documento->empresa_id)->first();
            $resultado = $factory->paraEmpresa($config)->enviarMensagem($config ?? new ConfigWhatsapp(), $telefone, $mensagem);

            Notificacao::create([
                'empresa_id' => $documento->empresa_id,
                'cliente_id' => $documento->venda?->cliente?->id,
                'venda_id' => $documento->venda_id,
                'canal' => 'whatsapp',
                'tipo' => 'outro',
                'telefone' => $telefone,
                'mensagem' => $mensagem,
                'provider' => ($config && $config->ativo) ? $config->provider : 'simulado',
                'status' => $resultado->status,
                'referencia_externa' => $resultado->referenciaExterna,
                'payload_retorno' => $resultado->payloadBruto,
            ]);

            if ($resultado->status !== 'enviado') {
                return response()->json(['message' => 'Não foi possível enviar pelo WhatsApp. Verifique a conexão em Configurações → WhatsApp.'], 422);
            }

            return response()->json(['message' => 'Enviado por WhatsApp.']);
        }

        $email = trim($dados['destino']);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'E-mail inválido.'], 422);
        }

        try {
            Mail::html(nl2br(e($mensagem)).'<br><br>O XML da nota segue em anexo.', function ($m) use ($email, $documento, $tipo, $razao) {
                $m->to($email)->subject("{$tipo} nº {$documento->numero} - {$razao}");
                if (! empty($documento->xml_path) && is_file($documento->xml_path)) {
                    $m->attach($documento->xml_path, ['as' => basename($documento->xml_path), 'mime' => 'application/xml']);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Falha ao enviar o e-mail. Verifique a configuração de e-mail do servidor.'], 422);
        }

        return response()->json(['message' => 'Enviado por e-mail.']);
    }

    /**
     * Visualização pública (link assinado) do cupom/DANFE, sem login.
     * O contexto de RLS é aberto aqui porque não há usuário autenticado -
     * a assinatura da URL é que autoriza o acesso a este documento.
     */
    public function visualizarPublico(Request $request, int $documentoId)
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $documento = DocumentoFiscal::with(['itens.produto', 'venda.cliente', 'venda.itens.agendaVisitacao', 'cliente', 'empresa', 'compra.fornecedor'])->findOrFail($documentoId);

        return view($documento->modelo === 55 ? 'fiscal.nfe' : 'fiscal.cupom', ['documento' => $documento]);
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
