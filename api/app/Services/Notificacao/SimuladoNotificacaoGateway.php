<?php

namespace App\Services\Notificacao;

use App\Models\ConfigWhatsapp;
use App\Services\Notificacao\Dto\ResultadoEnvio;
use Illuminate\Support\Str;

/**
 * Gateway "de mentira": só registra a mensagem como enviada, sem falar
 * com nenhum provedor real. Usado como padrão até a empresa configurar
 * um provedor de verdade (Z-API ou Baileys) - mesma lógica do
 * SimuladoPagamentoGateway.
 */
class SimuladoNotificacaoGateway implements NotificacaoGatewayInterface
{
    public function enviarMensagem(ConfigWhatsapp $config, string $telefone, string $mensagem): ResultadoEnvio
    {
        return new ResultadoEnvio(
            status: 'enviado',
            referenciaExterna: 'SIMULADO-'.Str::random(12),
        );
    }
}
