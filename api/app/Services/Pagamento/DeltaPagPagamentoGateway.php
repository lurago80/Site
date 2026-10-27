<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Integração com a DeltaPag, via API da plataforma Zoov/Bempaggo que a
 * sustenta (documentação oficial: https://developers.zoov.com.br/, os
 * domínios *.deltapag.io ainda funcionam mas estão sendo descontinuados).
 *
 * Autenticação: um único Bearer token estático, gerado no painel da
 * DeltaPag (Configurações → Integrações → API) - guardado no campo
 * `access_token` do ConfigPagamento. Diferente dos outros gateways, a
 * API também exige um "seller id" em cada endpoint - guardado no campo
 * `client_id` (os campos `public_key`/`client_secret` não são usados
 * por esta integração).
 *
 * IMPORTANTE - webhook sem payload: ao contrário do Mercado Pago/Stone, o
 * webhook de cobrança da DeltaPag não manda NENHUM dado identificador no
 * corpo (só um POST com texto fixo "Bempaggo"); a única forma de saber
 * qual cobrança mudou é a gente mesmo embutir uma referência na query
 * string da `notificationUrl` que informamos ao criar a cobrança (a API
 * acrescenta `?orderStatus=...&lastChargeStatus=...` por cima, mas não
 * apaga o que já estava lá). Por isso geramos `orderReference` ANTES de
 * chamar a API e devolvemos em `referenciaPedido`, persistido por
 * PagamentoService em `cobrancas.referencia_pedido` - ver
 * WebhookPagamentoController::deltaPag().
 *
 * Escrita a partir da documentação oficial, SEM integração anterior para
 * copiar e SEM ter testado contra o sandbox ainda (aguardando
 * credenciais) - testar de ponta a ponta antes de ativar para qualquer
 * empresa cliente em produção. Pontos que merecem atenção especial no
 * primeiro teste real:
 * - O corpo exato de POST /api/v2/charges/credit/card não está detalhado
 *   na documentação (por isso usamos o fluxo orders/credit-card/authorize
 *   com operationType=AUTHORIZE_AND_CAPTURE, que está bem documentado).
 * - O nome do campo de motivo de recusa em transactions[] não é
 *   documentado (motivoRejeicao pode vir vazio).
 * - Cartão de débito não é mencionado na documentação - bloqueado aqui.
 */
class DeltaPagPagamentoGateway implements PagamentoGatewayInterface
{
    private const BASE_URL_PRODUCAO = 'https://api.zoov.com.br';

    private const BASE_URL_SANDBOX = 'https://sandbox-api.zoov.com.br';

    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca
    {
        $orderReference = $this->orderReference($venda);

        $resposta = $this->cliente($config)->post(
            $this->baseUrl($config)."/api/v2/sellers/{$config->client_id}/orders/pix",
            [
                'orderReference' => $orderReference,
                'amount' => $this->emCentavos($venda->valor_total),
                'customer' => $this->dadosCliente($venda),
                'notificationUrl' => $this->notificationUrl($orderReference),
                'payments' => [[
                    'paymentMethod' => 'PIX',
                    'amount' => $this->emCentavos($venda->valor_total),
                    'desiredExpirationDate' => now()->addHour()->getTimestamp() * 1000,
                ]],
            ],
        );

        if ($resposta->failed()) {
            throw new \RuntimeException('DeltaPag recusou a criação da cobrança Pix: '.$resposta->body());
        }

        $id = $resposta->header('Content-ID');
        $cobranca = $this->buscarCobranca($config, $id);
        $transacao = $cobranca['transactions'][0] ?? [];

        return new ResultadoCobranca(
            status: $this->mapearStatus($cobranca['status'] ?? null),
            referenciaExterna: $id !== null ? (string) $id : null,
            referenciaPedido: $orderReference,
            qrCode: $transacao['emv'] ?? null,
            qrCodeBase64: $id !== null ? $this->baixarQrCodeComoBase64($config, $id) : null,
            expiraEm: isset($transacao['expirationDate'])
                ? (new \DateTimeImmutable())->setTimestamp(intdiv((int) $transacao['expirationDate'], 1000))
                : null,
            payloadBruto: $cobranca,
        );
    }

    public function criarCobrancaCartao(
        Venda $venda,
        ConfigPagamento $config,
        string $tokenCartao,
        int $parcelas,
        string $metodoPagamento,
    ): ResultadoCobranca {
        if ($metodoPagamento === 'cartao_debito') {
            throw new \RuntimeException('A DeltaPag ainda não tem suporte documentado a cartão de débito - só crédito.');
        }

        $orderReference = $this->orderReference($venda);

        $resposta = $this->cliente($config)->post(
            $this->baseUrl($config)."/api/v2/sellers/{$config->client_id}/orders/credit-card/authorize",
            [
                'operationType' => 'AUTHORIZE_AND_CAPTURE',
                'orderReference' => $orderReference,
                'amount' => $this->emCentavos($venda->valor_total),
                'customer' => $this->dadosCliente($venda),
                'notificationUrl' => $this->notificationUrl($orderReference),
                'payments' => [[
                    'paymentMethod' => 'CREDIT_CARD',
                    'amount' => $this->emCentavos($venda->valor_total),
                    'installments' => max(1, $parcelas),
                    'cardToken' => ['token' => $tokenCartao],
                ]],
            ],
        );

        if ($resposta->failed()) {
            // 422 é a resposta da DeltaPag para cartão recusado pelo emissor/
            // antifraude (ex.: "viola as regras de tentativas de cobrança") -
            // é um resultado de negócio normal, não deve derrubar o checkout
            // com um erro 500. Qualquer outro código (401, 5xx, etc) ainda é
            // um problema de integração de verdade e continua lançando.
            if ($resposta->status() === 422) {
                $erros = $resposta->json();
                $mensagem = is_array($erros) ? ($erros[0]['message'] ?? $erros['message'] ?? null) : null;

                return new ResultadoCobranca(
                    status: 'recusado',
                    referenciaExterna: null,
                    referenciaPedido: $orderReference,
                    payloadBruto: is_array($erros) ? $erros : null,
                    motivoRejeicao: $mensagem ?? 'Cartão recusado pela DeltaPag.',
                );
            }

            throw new \RuntimeException('DeltaPag recusou a cobrança no cartão: '.$resposta->body());
        }

        $id = $resposta->header('Content-ID');
        $cobranca = $this->buscarCobranca($config, $id);
        $transacao = $cobranca['transactions'][0] ?? [];

        return new ResultadoCobranca(
            status: $this->mapearStatus($cobranca['status'] ?? null),
            referenciaExterna: $id !== null ? (string) $id : null,
            referenciaPedido: $orderReference,
            payloadBruto: $cobranca,
            motivoRejeicao: $transacao['message'] ?? $transacao['reason'] ?? null,
        );
    }

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string
    {
        return $this->mapearStatus($this->buscarCobranca($config, $referenciaExterna)['status'] ?? null);
    }

    /**
     * Pix ainda não pago: cancela o QR code (o cliente não consegue mais
     * pagar aquela cobrança). Cartão: usa o mesmo endpoint de estorno tanto
     * para desfazer uma autorização quanto para estornar uma captura - a
     * DeltaPag decide o comportamento certo a partir do estado da cobrança.
     */
    public function cancelarCobranca(ConfigPagamento $config, string $referenciaExterna, string $metodo): string
    {
        $url = match ($metodo) {
            'pix' => $this->baseUrl($config)."/api/v2/charges/{$referenciaExterna}/pix/cancel",
            'cartao_credito' => $this->baseUrl($config)."/api/v2/charges/{$referenciaExterna}/credit-card/refund",
            default => throw new \RuntimeException("DeltaPag: cancelamento não suportado para o método \"{$metodo}\"."),
        };

        $resposta = $this->cliente($config)->post($url, $metodo === 'cartao_credito' ? ['reason' => 'OTHERS'] : []);

        if ($resposta->failed()) {
            throw new \RuntimeException("DeltaPag recusou o cancelamento da cobrança {$referenciaExterna}: ".$resposta->body());
        }

        return $this->consultarStatus($config, $referenciaExterna);
    }

    private function cliente(ConfigPagamento $config): PendingRequest
    {
        return Http::withToken($config->access_token)->acceptJson();
    }

    private function baseUrl(ConfigPagamento $config): string
    {
        return $config->ambiente === 'producao' ? self::BASE_URL_PRODUCAO : self::BASE_URL_SANDBOX;
    }

    private function buscarCobranca(ConfigPagamento $config, string|int|null $id): array
    {
        if ($id === null) {
            throw new \RuntimeException('DeltaPag não retornou o Content-ID da cobrança criada.');
        }

        $resposta = $this->cliente($config)->get($this->baseUrl($config)."/api/v2/charges/{$id}");

        if ($resposta->failed()) {
            throw new \RuntimeException("Não foi possível consultar a cobrança {$id} na DeltaPag: ".$resposta->body());
        }

        return $resposta->json();
    }

    private function baixarQrCodeComoBase64(ConfigPagamento $config, string|int $id): ?string
    {
        try {
            $resposta = $this->cliente($config)->timeout(10)->get($this->baseUrl($config)."/api/v2/charges/{$id}/qrcode");

            return $resposta->successful() ? base64_encode($resposta->body()) : null;
        } catch (\Throwable) {
            // Não derruba a criação da cobrança por causa disso - o cliente
            // ainda consegue pagar copiando o "emv" (copia-e-cola).
            return null;
        }
    }

    private function dadosCliente(Venda $venda): array
    {
        return [
            'name' => $venda->cliente?->nome ?? 'Consumidor',
            'document' => preg_replace('/\D/', '', $venda->cliente?->cpf_cnpj ?? '') ?: '00000000000',
        ];
    }

    private function emCentavos(float $valor): int
    {
        return (int) round($valor * 100);
    }

    /** 4 a 32 caracteres, único por pedido - exigido pela DeltaPag. */
    private function orderReference(Venda $venda): string
    {
        return 'V'.$venda->id.'-'.bin2hex(random_bytes(5));
    }

    /**
     * Embute nossa própria referência na URL de notificação, já que o
     * webhook da DeltaPag não manda nenhum dado identificador no corpo -
     * ver WebhookPagamentoController::deltaPag().
     */
    private function notificationUrl(string $orderReference): string
    {
        return url('/api/webhooks/pagamento/deltapag').'?ref='.urlencode($orderReference);
    }

    private function mapearStatus(?string $statusDeltaPag): string
    {
        return match ($statusDeltaPag) {
            'PAY' => 'aprovado',
            'CANCELED' => 'cancelado',
            'FAIL', 'CHARGEBACK', 'COUNTERCHARGE', 'REFUND' => 'recusado',
            default => 'pendente', // PENDING, SCHEDULE, ACCREDIT, IN_PROGRESS, AUTHORIZED, MANUAL_DISCHARGE
        };
    }
}
