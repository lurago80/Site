<?php

namespace App\Services\Notificacao;

use App\Models\ConfigWhatsapp;
use App\Services\Notificacao\Dto\ResultadoEnvio;
use Illuminate\Support\Facades\Http;

/**
 * Implementação real via API REST do Z-API (serviço pago que expõe uma
 * instância do WhatsApp por HTTP - sem precisar rodar nenhuma
 * infraestrutura própria). Sem SDK, só o cliente HTTP do Laravel.
 *
 * Referência: https://developer.z-api.io/message/send-text
 */
class ZApiNotificacaoGateway implements NotificacaoGatewayInterface
{
    private const BASE_URL = 'https://api.z-api.io';

    public function enviarMensagem(ConfigWhatsapp $config, string $telefone, string $mensagem): ResultadoEnvio
    {
        $url = self::BASE_URL."/instances/{$config->instance_id}/token/{$config->token}/send-text";

        $resposta = Http::withHeaders(['Client-Token' => $config->client_token])
            ->acceptJson()
            ->post($url, [
                'phone' => $telefone,
                'message' => $mensagem,
            ]);

        if ($resposta->failed()) {
            return new ResultadoEnvio(
                status: 'falha',
                payloadBruto: $resposta->json(),
                motivoFalha: $resposta->body(),
            );
        }

        $dados = $resposta->json();

        return new ResultadoEnvio(
            status: 'enviado',
            referenciaExterna: $dados['zaapId'] ?? $dados['messageId'] ?? null,
            payloadBruto: $dados,
        );
    }
}
