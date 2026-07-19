<?php

namespace App\Jobs;

use App\Models\Venda;
use App\Services\Notificacao\NotificacaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Envia a confirmação de agendamento por WhatsApp em segundo plano -
 * antes rodava síncrono dentro do CheckoutController, então um
 * Z-API/Baileys lento deixava o checkout do cliente final lento junto.
 * Notificação nunca deve atrasar (nem quebrar) a resposta da compra.
 */
class EnviarConfirmacaoAgendamentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $vendaId) {}

    public function handle(NotificacaoService $notificacaoService): void
    {
        // Job roda fora do ciclo de request HTTP - não passou pelo
        // SetTenantContext, então precisa abrir o próprio contexto de
        // RLS (mesmo padrão dos comandos agendados, ex.
        // LiberarReservasExpiradas). Só lê/atualiza a venda pelo id
        // (já validado no momento da criação), sem risco de vazar
        // dados de outro tenant.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $venda = Venda::find($this->vendaId);

        if ($venda === null) {
            return;
        }

        $notificacaoService->enviarConfirmacaoAgendamento($venda);
    }
}
