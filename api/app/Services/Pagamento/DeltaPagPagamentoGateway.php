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
 * Testada de ponta a ponta no sandbox em 2026-10-27/28 (Pix, cartão de
 * crédito, recusa, cancelamento/estorno) e homologada pelo suporte da
 * DeltaPag - ver doc "Homologação DeltaPag - Sandbox". Pontos confirmados
 * com o suporte:
 * - Motivo de recusa: transactions[].returnCode/returnMessage (tabela
 *   ABECS), lista não fechada - código desconhecido = tratar como
 *   irreversível. Ver motivoRejeicao().
 * - Cartão de débito tokenizado (mesmo fluxo do crédito) não é
 *   documentado - continua bloqueado aqui. O que existe documentado para
 *   débito é o fluxo de 3D Secure (ver autorizarComThreeDSecure), que usa
 *   o número do cartão direto, não token.
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
            motivoRejeicao: $this->motivoRejeicao($transacao),
        );
    }

    /**
     * Fluxo de 3D Secure 2.0 - DIFERENTE do criarCobrancaCartao: usa o
     * número do cartão direto (não um token) e exige dados completos do
     * comprador (endereço/telefone/e-mail) e do navegador (fingerprint).
     * Confirmado com o suporte da DeltaPag em 2026-10-28: no sandbox usar
     * mode=DEBIT; cartões de teste 4918019199883839 (com desafio) e
     * 4918019160034602 (frictionless).
     *
     * Quando há desafio, o retorno traz a URL pra redirecionar o
     * comprador (ver `link`) - a confirmação final chega pelo webhook,
     * igual aos outros fluxos. Ainda não ligado ao checkout da loja
     * pública (exige telas novas pra coletar endereço/telefone/e-mail e
     * capturar o fingerprint do navegador via JS) - por enquanto só serve
     * pra validar a integração com o suporte da DeltaPag.
     *
     * @param  array{numero: string, cvv?: string, mes: int, ano: int}  $cartao
     * @param  array{ip: string, userAgent: string, successUrl: string, failureUrl: string, deviceFingerprint?: string}  $navegador
     */
    public function autorizarComThreeDSecure(
        Venda $venda,
        ConfigPagamento $config,
        array $cartao,
        array $navegador,
        string $modo = 'DEBIT',
    ): array {
        $cliente = $venda->cliente;

        if ($cliente === null) {
            throw new \RuntimeException('3DS exige dados completos do cliente (endereço, telefone, e-mail) - esta venda não tem cliente associado.');
        }

        $orderReference = $this->orderReference($venda);

        $resposta = $this->cliente($config)->post(
            $this->baseUrl($config)."/api/v2/sellers/{$config->client_id}/orders/credit-card/three-d-secure/authorize",
            [
                'mode' => $modo,
                'value' => $this->emCentavos($venda->valor_total),
                'installments' => 1,
                'orderReference' => $orderReference,
                'notificationUrl' => $this->notificationUrl($orderReference),
                'customer' => [
                    'name' => $cliente->nome,
                    'document' => preg_replace('/\D/', '', $cliente->cpf_cnpj ?? '') ?: '00000000000',
                    'email' => $cliente->email ?: 'consumidor@'.$venda->empresa->slug.'.naoresponda.com',
                    'phone' => [
                        'countryCode' => 55,
                        'areaCode' => (int) mb_substr(preg_replace('/\D/', '', $cliente->telefone ?? ''), 0, 2),
                        'number' => (int) mb_substr(preg_replace('/\D/', '', $cliente->telefone ?? ''), 2),
                    ],
                    'address' => [
                        'street' => $cliente->logradouro ?? '',
                        'streetNumber' => $cliente->numero ?? '',
                        'neighborhood' => $cliente->bairro ?? '',
                        'city' => $cliente->municipio ?? '',
                        'state' => $cliente->uf ?? '',
                        'zipCode' => preg_replace('/\D/', '', $cliente->cep ?? ''),
                    ],
                ],
                'card' => [
                    'cardNumber' => $cartao['numero'],
                    'cvv' => $cartao['cvv'] ?? null,
                    'expiration' => ['month' => $cartao['mes'], 'year' => $cartao['ano']],
                    'holder' => ['name' => $cliente->nome],
                ],
                'threeDSecure' => [
                    'device' => [
                        'deviceType3ds' => 'BROWSER',
                        'screenWidth' => 1920,
                        'screenHeight' => 1080,
                        'colorDepth' => 24,
                        'javaEnabled' => false,
                        'javascriptEnabled' => true,
                        'language' => 'pt-BR',
                        'timeZoneOffset' => -3,
                        'deviceFingerprint' => $navegador['deviceFingerprint'] ?? null,
                    ],
                    'onFailure' => 'DECLINE',
                    'ipAddress' => $navegador['ip'],
                    'userAgent' => $navegador['userAgent'],
                    'embedded' => false,
                    'successUrl' => $navegador['successUrl'],
                    'failureUrl' => $navegador['failureUrl'],
                    'billing' => [
                        'address' => [
                            'street' => $cliente->logradouro ?? '',
                            'streetNumber' => $cliente->numero ?? '',
                            'zipCode' => preg_replace('/\D/', '', $cliente->cep ?? ''),
                            'neighborhood' => $cliente->bairro ?? '',
                            'city' => $cliente->municipio ?? '',
                            'state' => $cliente->uf ?? '',
                        ],
                        'phone' => [
                            'country' => 'BRAZIL',
                            'areaCode' => mb_substr(preg_replace('/\D/', '', $cliente->telefone ?? ''), 0, 2),
                            'number' => mb_substr(preg_replace('/\D/', '', $cliente->telefone ?? ''), 2),
                        ],
                        'email' => $cliente->email ?: 'consumidor@'.$venda->empresa->slug.'.naoresponda.com',
                    ],
                ],
            ],
        );

        if ($resposta->failed()) {
            throw new \RuntimeException('DeltaPag recusou a autorização 3DS: '.$resposta->body());
        }

        // O header Link vem no formato padrão RFC 8288: <url>; rel="challenge".
        $linkChallenge = null;
        $linkBruto = $resposta->header('Link');
        if ($linkBruto !== null && preg_match('/<([^>]+)>/', $linkBruto, $m)) {
            $linkChallenge = $m[1];
        }

        $corpo = $resposta->json() ?? [];

        return [
            'referenciaExterna' => $resposta->header('Content-ID'),
            'referenciaPedido' => $orderReference,
            'temDesafio' => $linkChallenge !== null,
            'linkDesafio' => $linkChallenge,
            'acsUrl' => $corpo['acsUrl'] ?? null,
            'creq' => $corpo['creq'] ?? null,
            'authenticationTransactionId' => $corpo['authenticationTransactionId'] ?? null,
            'payloadBruto' => $corpo,
        ];
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

    /**
     * O código de recusa vem em transactions[].returnCode/returnMessage,
     * seguindo a tabela ABECS (confirmado com o suporte da DeltaPag em
     * 2026-10-28). A lista de códigos não é fechada - sempre tratar um
     * código desconhecido como irreversível (nunca sugerir "tente de
     * novo"). returnCode pode vir como "5" ou "05", então é tratado só
     * como string, nunca convertido pra número.
     */
    private function motivoRejeicao(array $transacao): ?string
    {
        $codigo = $transacao['returnCode'] ?? null;
        $mensagem = $transacao['returnMessage'] ?? $transacao['message'] ?? $transacao['reason'] ?? null;

        if ($codigo === null && $mensagem === null) {
            return null;
        }

        return trim(($codigo !== null ? "[{$codigo}] " : '').($mensagem ?? ''));
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
