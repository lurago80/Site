<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;
use Illuminate\Support\Facades\Http;

/**
 * Implementação real via API do Pagar.me v5 (api.pagar.me/core/v5) -
 * a Stone é dona da Pagar.me e é essa a plataforma usada para Pix e
 * cartão online da bandeira "Stone" (o próprio QR code Pix retornado
 * traz o identificador BR.COM.STONE.QRCODE). Documentação:
 * https://docs.pagar.me/reference
 *
 * IMPORTANTE: escrita a partir da documentação oficial (não existe
 * integração anterior no projeto pra copiar, como havia com o Mercado
 * Pago) - antes de ativar para uma empresa cliente em produção, testar
 * de ponta a ponta com credenciais de sandbox da Stone/Pagar.me.
 *
 * Autenticação: HTTP Basic Auth, usuário = secret_key da conta,
 * senha em branco (padrão da Pagar.me, não é usuário+senha de verdade).
 * Guardamos a secret_key no campo `access_token` (criptografado) do
 * ConfigPagamento - mesmo campo que os outros gateways usam para a
 * credencial de servidor, só o nome do campo é genérico.
 */
class StonePagamentoGateway implements PagamentoGatewayInterface
{
    private const BASE_URL = 'https://api.pagar.me/core/v5';

    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca
    {
        $resposta = $this->cliente($config)->post(self::BASE_URL.'/orders', [
            'items' => [[
                'amount' => $this->emCentavos($venda->valor_total),
                'description' => "Venda #{$venda->id}",
                'quantity' => 1,
            ]],
            'customer' => $this->dadosCliente($venda),
            'payments' => [[
                'payment_method' => 'pix',
                'pix' => ['expires_in' => 3600],
            ]],
        ]);

        if ($resposta->failed()) {
            throw new \RuntimeException('Stone recusou a criação da cobrança Pix: '.$resposta->body());
        }

        $dados = $resposta->json();
        $cobranca = $dados['charges'][0] ?? [];
        $transacao = $cobranca['last_transaction'] ?? [];

        return new ResultadoCobranca(
            status: $this->mapearStatus($cobranca['status'] ?? 'pending'),
            referenciaExterna: $cobranca['id'] ?? null,
            qrCode: $transacao['qr_code'] ?? null,
            qrCodeBase64: $this->baixarQrCodeComoBase64($transacao['qr_code_url'] ?? null),
            expiraEm: isset($transacao['expires_at']) ? new \DateTimeImmutable($transacao['expires_at']) : null,
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
        $resposta = $this->cliente($config)->post(self::BASE_URL.'/orders', [
            'items' => [[
                'amount' => $this->emCentavos($venda->valor_total),
                'description' => "Venda #{$venda->id}",
                'quantity' => 1,
            ]],
            'customer' => $this->dadosCliente($venda),
            'payments' => [[
                'payment_method' => $metodoPagamento === 'cartao_debito' ? 'debit_card' : 'credit_card',
                'credit_card' => [
                    'card_token' => $tokenCartao,
                    'installments' => $parcelas,
                ],
            ]],
        ]);

        if ($resposta->failed()) {
            throw new \RuntimeException('Stone recusou a cobrança no cartão: '.$resposta->body());
        }

        $dados = $resposta->json();
        $cobranca = $dados['charges'][0] ?? [];
        $transacao = $cobranca['last_transaction'] ?? [];

        return new ResultadoCobranca(
            status: $this->mapearStatus($cobranca['status'] ?? 'pending'),
            referenciaExterna: $cobranca['id'] ?? null,
            payloadBruto: $dados,
            motivoRejeicao: $transacao['acquirer_message'] ?? null,
        );
    }

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string
    {
        $resposta = $this->cliente($config)->get(self::BASE_URL."/charges/{$referenciaExterna}");

        if ($resposta->failed()) {
            throw new \RuntimeException('Não foi possível consultar a cobrança na Stone: '.$resposta->body());
        }

        return $this->mapearStatus($resposta->json('status'));
    }

    private function cliente(ConfigPagamento $config)
    {
        return Http::withBasicAuth($config->access_token, '')->acceptJson();
    }

    private function dadosCliente(Venda $venda): array
    {
        return [
            'name' => $venda->cliente?->nome ?? 'Consumidor',
            'email' => $venda->cliente?->email ?: 'consumidor@'.$venda->empresa->slug.'.naoresponda.com',
            'type' => 'individual',
            'document' => preg_replace('/\D/', '', $venda->cliente?->cpf_cnpj ?? '') ?: '00000000000',
        ];
    }

    private function emCentavos(float $valor): int
    {
        return (int) round($valor * 100);
    }

    /**
     * A Pagar.me devolve o QR code Pix como URL de imagem (qr_code_url),
     * não em base64 como o Mercado Pago - baixamos aqui pra manter o
     * mesmo contrato de ResultadoCobranca em todos os gateways (o
     * front-end da loja pública já sabe renderizar qr_code_base64).
     */
    private function baixarQrCodeComoBase64(?string $qrCodeUrl): ?string
    {
        if ($qrCodeUrl === null) {
            return null;
        }

        try {
            $resposta = Http::timeout(10)->get($qrCodeUrl);

            return $resposta->successful() ? base64_encode($resposta->body()) : null;
        } catch (\Throwable) {
            // Não derruba a criação da cobrança por causa disso - o
            // cliente ainda consegue pagar copiando o código "qr_code"
            // (copia-e-cola) mesmo sem a imagem.
            return null;
        }
    }

    private function mapearStatus(?string $statusPagarme): string
    {
        return match ($statusPagarme) {
            'paid' => 'aprovado',
            'failed', 'canceled', 'chargedback' => 'recusado',
            default => 'pendente', // pending, processing, waiting_payment, authorized...
        };
    }
}
