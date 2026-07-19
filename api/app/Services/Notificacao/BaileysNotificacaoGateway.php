<?php

namespace App\Services\Notificacao;

use App\Models\ConfigWhatsapp;
use App\Services\Notificacao\Dto\ResultadoEnvio;

/**
 * PENDENTE: Baileys é uma biblioteca Node.js (implementa o protocolo
 * do WhatsApp Web) - não existe versão PHP nativa. Usá-lo de verdade
 * exige um microserviço Node.js separado, rodando ao lado do Laravel,
 * mantendo uma sessão por empresa (QR code) e expondo uma API HTTP
 * interna para este gateway chamar. Decisão registrada em conversa
 * com o cliente em 2026-07-19: gratuito, mas fora dos Termos de Uso do
 * WhatsApp (risco real de banimento do número) - por isso o cliente
 * final decide, por empresa, entre este provedor e o Z-API (pago).
 *
 * Quando o microserviço existir: replicar o padrão do ZApiNotificacaoGateway,
 * trocando a URL pela do serviço Node local/interno.
 */
class BaileysNotificacaoGateway implements NotificacaoGatewayInterface
{
    public function enviarMensagem(ConfigWhatsapp $config, string $telefone, string $mensagem): ResultadoEnvio
    {
        throw new \RuntimeException('Provedor Baileys ainda não implementado - depende de um microserviço Node.js separado (ver comentário da classe).');
    }
}
