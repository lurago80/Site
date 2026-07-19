<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Implementação real via API REST do Mercado Pago (sem SDK - só o
 * cliente HTTP do Laravel, a API é simples o suficiente). Cobre Pix
 * (testável de ponta a ponta) e cartão (mesma chamada, mas depende de
 * um token gerado no front-end via Mercado Pago.js - ainda não
 * implementado no checkout da loja pública).
 *
 * Referência: https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
 */
class MercadoPagoPagamentoGateway implements PagamentoGatewayInterface
{
    private const BASE_URL = 'https://api.mercadopago.com';

    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca
    {
        $resposta = $this->cliente($config)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->post(self::BASE_URL.'/v1/payments', [
                'transaction_amount' => (float) $venda->valor_total,
                'description' => "Venda #{$venda->id}",
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $venda->cliente?->email ?: 'consumidor@'.$venda->empresa->slug.'.naoresponda.com',
                    'first_name' => $venda->cliente?->nome ?? 'Consumidor',
                ],
            ]);

        if ($resposta->failed()) {
            throw new \RuntimeException('Mercado Pago recusou a criação da cobrança Pix: '.$resposta->body());
        }

        $dados = $resposta->json();
        $transacao = $dados['point_of_interaction']['transaction_data'] ?? [];

        return new ResultadoCobranca(
            status: $this->mapearStatus($dados['status'] ?? 'pending'),
            referenciaExterna: (string) $dados['id'],
            qrCode: $transacao['qr_code'] ?? null,
            qrCodeBase64: $transacao['qr_code_base64'] ?? null,
            payloadBruto: $dados,
        );
    }

    public function criarCobrancaCartao(
        Venda $venda,
        ConfigPagamento $config,
        string $tokenCartao,
        int $parcelas,
        string $metodoPagamento,
    ): ResultadoCobranca {
        $resposta = $this->cliente($config)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->post(self::BASE_URL.'/v1/payments', [
                'transaction_amount' => (float) $venda->valor_total,
                'token' => $tokenCartao,
                'description' => "Venda #{$venda->id}",
                'installments' => $parcelas,
                'payment_method_id' => $metodoPagamento,
                'payer' => [
                    'email' => $venda->cliente?->email ?: 'consumidor@'.$venda->empresa->slug.'.naoresponda.com',
                ],
            ]);

        if ($resposta->failed()) {
            throw new \RuntimeException('Mercado Pago recusou a cobrança no cartão: '.$resposta->body());
        }

        $dados = $resposta->json();

        return new ResultadoCobranca(
            status: $this->mapearStatus($dados['status'] ?? 'pending'),
            referenciaExterna: (string) $dados['id'],
            payloadBruto: $dados,
            motivoRejeicao: $dados['status_detail'] ?? null,
        );
    }

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string
    {
        $resposta = $this->cliente($config)->get(self::BASE_URL."/v1/payments/{$referenciaExterna}");

        if ($resposta->failed()) {
            throw new \RuntimeException('Não foi possível consultar a cobrança no Mercado Pago: '.$resposta->body());
        }

        return $this->mapearStatus($resposta->json('status'));
    }

    private function cliente(ConfigPagamento $config)
    {
        return Http::withToken($config->access_token)->acceptJson();
    }

    private function mapearStatus(string $statusMercadoPago): string
    {
        return match ($statusMercadoPago) {
            'approved' => 'aprovado',
            'rejected', 'cancelled' => 'recusado',
            default => 'pendente', // pending, in_process, authorized, in_mediation...
        };
    }
}
