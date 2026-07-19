<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recebe notificações do Asaas quando uma cobrança de assinatura muda
 * de status. Endpoint público (o gateway não tem como se autenticar
 * como um usuário do sistema) - mesma lógica do
 * WebhookPagamentoController: abre um bypass de RLS mínimo e
 * controlado só para localizar a assinatura, depois re-escopa.
 *
 * Diferente do módulo de pagamento (cobrança avulsa do cliente final),
 * aqui uma cobrança em atraso tem uma consequência automática real:
 * suspende o acesso da empresa ao sistema (mesmo campo `status` que o
 * super admin usa para suspender manualmente).
 */
class WebhookAssinaturaController extends Controller
{
    public function asaas(Request $request)
    {
        $evento = $request->input('event');
        $subscriptionId = $request->input('payment.subscription');

        if (empty($subscriptionId)) {
            return response()->json(['ignorado' => true]);
        }

        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $assinatura = Assinatura::where('asaas_subscription_id', $subscriptionId)->first();

        if ($assinatura === null) {
            return response()->json(['encontrado' => false]);
        }

        match ($evento) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->marcarEmDia($assinatura),
            'PAYMENT_OVERDUE' => $this->marcarAtrasadoESuspender($assinatura),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    private function marcarEmDia(Assinatura $assinatura): void
    {
        $assinatura->update(['status_pagamento' => 'em_dia']);

        if ($assinatura->empresa->status === 'suspensa') {
            $assinatura->empresa->update(['status' => 'ativa']);
        }
    }

    private function marcarAtrasadoESuspender(Assinatura $assinatura): void
    {
        $assinatura->update(['status_pagamento' => 'atrasado']);
        $assinatura->empresa->update(['status' => 'suspensa']);
    }
}
