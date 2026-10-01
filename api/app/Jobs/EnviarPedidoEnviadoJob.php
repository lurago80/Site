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
 * Avisa o cliente por WhatsApp que o pedido foi enviado (ou está pronto
 * para retirada), em segundo plano - mesmo motivo de
 * EnviarConfirmacaoAgendamentoJob: provedor lento não pode travar a tela
 * do lojista, e falha de notificação nunca desfaz a mudança de status.
 */
class EnviarPedidoEnviadoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $vendaId) {}

    public function handle(NotificacaoService $notificacaoService): void
    {
        // Fora do ciclo HTTP não há SetTenantContext - abre o contexto de
        // RLS como nos demais jobs (só lê a venda pelo id já validado).
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $venda = Venda::find($this->vendaId);

        if ($venda === null) {
            return;
        }

        $notificacaoService->enviarPedidoEnviado($venda);
    }
}
