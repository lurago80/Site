<?php

namespace App\Services\Notificacao;

use App\Models\ConfigWhatsapp;

/**
 * Resolve o gateway de notificação POR EMPRESA - mesma lógica do
 * PagamentoGatewayFactory: cada empresa escolhe seu provedor.
 */
class NotificacaoGatewayFactory
{
    public function paraEmpresa(?ConfigWhatsapp $config): NotificacaoGatewayInterface
    {
        if ($config === null || ! $config->ativo) {
            return new SimuladoNotificacaoGateway();
        }

        return match ($config->provider) {
            'zapi' => new ZApiNotificacaoGateway(),
            'baileys' => new BaileysNotificacaoGateway(),
            default => new SimuladoNotificacaoGateway(),
        };
    }
}
