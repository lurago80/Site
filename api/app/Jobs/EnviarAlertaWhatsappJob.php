<?php

namespace App\Jobs;

use App\Models\Alerta;
use App\Models\Empresa;
use App\Services\Notificacao\NotificacaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Manda o alerta ao WhatsApp do administrador (parâmetro alerta_whatsapp) em segundo plano:
 * o provedor pode demorar ou falhar sem atrapalhar a venda, o cancelamento ou o fechamento.
 */
class EnviarAlertaWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $alertaId) {}

    public function handle(NotificacaoService $notificacaoService): void
    {
        // Fora do ciclo HTTP não há SetTenantContext (mesmo motivo dos demais jobs).
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $alerta = Alerta::find($this->alertaId);
        $telefone = $alerta ? Empresa::whereKey($alerta->empresa_id)->value('alerta_whatsapp') : null;

        if ($alerta === null || empty($telefone)) {
            return;
        }

        $notificacao = $notificacaoService->enviarParaAdministrador($alerta->empresa_id, $telefone, "⚠️ Alerta: {$alerta->mensagem}");

        if ($notificacao->status === 'enviado') {
            $alerta->update(['whatsapp_enviado_em' => now()]);
        }
    }
}
