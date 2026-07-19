<?php

namespace App\Services\Notificacao;

use App\Models\ConfigWhatsapp;
use App\Services\Notificacao\Dto\ResultadoEnvio;

interface NotificacaoGatewayInterface
{
    public function enviarMensagem(ConfigWhatsapp $config, string $telefone, string $mensagem): ResultadoEnvio;
}
