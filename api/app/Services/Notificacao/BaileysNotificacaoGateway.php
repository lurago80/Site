<?php

namespace App\Services\Notificacao;

use App\Models\ConfigWhatsapp;
use App\Services\Notificacao\Dto\ResultadoEnvio;
use Illuminate\Support\Facades\Http;

/**
 * Delega o envio ao microserviço Node.js em whatsapp-service/ (Baileys
 * implementa o protocolo do WhatsApp Web - não existe versão PHP
 * nativa, por isso é um serviço à parte, chamado por HTTP interna).
 *
 * Gratuito, mas fora dos Termos de Uso do WhatsApp (risco real de
 * banimento do número) - o cliente final decide, por empresa, entre
 * este provedor e o Z-API (pago). Decisão registrada em conversa com o
 * cliente em 2026-07-19.
 */
class BaileysNotificacaoGateway implements NotificacaoGatewayInterface
{
    public function enviarMensagem(ConfigWhatsapp $config, string $telefone, string $mensagem): ResultadoEnvio
    {
        $resposta = $this->cliente()->post(
            $this->baseUrl()."/empresas/{$config->empresa_id}/enviar",
            ['telefone' => $telefone, 'mensagem' => $mensagem]
        );

        if ($resposta->failed()) {
            return new ResultadoEnvio(
                status: 'falha',
                payloadBruto: $resposta->json(),
                motivoFalha: $resposta->json('erro') ?? $resposta->body(),
            );
        }

        $dados = $resposta->json();

        return new ResultadoEnvio(
            status: 'enviado',
            referenciaExterna: $dados['id'] ?? null,
            payloadBruto: $dados,
        );
    }

    private function cliente()
    {
        return Http::withHeaders(['x-internal-token' => config('services.baileys.token')])->acceptJson();
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.baileys.url'), '/');
    }
}
