<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Services\Pagamento\PagamentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recebe notificações assíncronas dos gateways de pagamento quando uma
 * cobrança muda de status (ex.: Pix pago). Endpoint público (o gateway
 * não tem como se autenticar como um usuário do sistema) - por isso não
 * tem 'auth'/'tenant' e precisa abrir o próprio contexto de RLS.
 *
 * Por segurança, NUNCA confiamos cegamente no status que vem no corpo
 * da notificação - sempre reconsultamos a API do gateway com nossas
 * credenciais antes de marcar algo como pago (prática recomendada pelo
 * próprio Mercado Pago, evita notificação falsificada por terceiros).
 */
class WebhookPagamentoController extends Controller
{
    public function __construct(private readonly PagamentoService $pagamentoService) {}

    public function mercadoPago(Request $request)
    {
        if ($request->input('type') !== 'payment') {
            return response()->json(['ignorado' => true]);
        }

        $referenciaExterna = (string) $request->input('data.id');

        if ($referenciaExterna === '') {
            return response()->json(['ignorado' => true]);
        }

        // Webhook não tem usuário logado nem slug de empresa na URL - a
        // única forma de achar a empresa dona da cobrança é buscando
        // pela referência externa em todos os tenants (bypass de RLS
        // controlado, só para esta consulta específica).
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $cobranca = Cobranca::where('gateway', 'mercadopago')
            ->where('referencia_externa', $referenciaExterna)
            ->first();

        if ($cobranca === null) {
            return response()->json(['encontrado' => false]);
        }

        DB::statement("SELECT set_config('app.current_empresa_id', ?, false)", [(string) $cobranca->empresa_id]);
        DB::statement("SELECT set_config('app.is_super_admin', 'false', false)");

        $this->pagamentoService->consultarEAtualizarStatus($cobranca);

        return response()->json(['ok' => true]);
    }
}
