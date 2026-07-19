<?php

namespace App\Services\Assinatura;

use App\Models\ConfigAssinatura;
use App\Models\Empresa;
use App\Models\Plano;
use Illuminate\Support\Facades\Http;

/**
 * Implementação real via API REST do Asaas (sem SDK, mesmo padrão dos
 * demais gateways do sistema). Escolhido para cobrança de assinatura
 * porque não cobra mensalidade da própria plataforma - só uma taxa por
 * cobrança de fato realizada (Pix é gratuito, boleto/cartão têm taxa).
 *
 * Referência: https://docs.asaas.com/reference/criar-novo-cliente e
 * https://docs.asaas.com/reference/criar-nova-assinatura
 */
class AsaasGateway
{
    public function criarCliente(Empresa $empresa, ConfigAssinatura $config): string
    {
        $resposta = $this->cliente($config)->post($this->baseUrl($config).'/customers', [
            'name' => $empresa->razao_social,
            'cpfCnpj' => preg_replace('/\D/', '', $empresa->cnpj),
            'externalReference' => (string) $empresa->id,
        ]);

        if ($resposta->failed()) {
            throw new \RuntimeException('Asaas recusou a criação do cliente: '.$resposta->body());
        }

        return $resposta->json('id');
    }

    public function criarAssinatura(string $customerId, Plano $plano, ConfigAssinatura $config): array
    {
        $resposta = $this->cliente($config)->post($this->baseUrl($config).'/subscriptions', [
            'customer' => $customerId,
            'billingType' => 'UNDEFINED', // cliente escolhe Pix/boleto/cartão na hora de pagar
            'value' => (float) $plano->valor_mensal,
            'cycle' => 'MONTHLY',
            'description' => "Assinatura plano {$plano->nome}",
            'nextDueDate' => now()->addDay()->toDateString(),
        ]);

        if ($resposta->failed()) {
            throw new \RuntimeException('Asaas recusou a criação da assinatura: '.$resposta->body());
        }

        $dados = $resposta->json();

        return [
            'id' => $dados['id'],
            'proximaCobranca' => $dados['nextDueDate'] ?? null,
        ];
    }

    private function cliente(ConfigAssinatura $config)
    {
        return Http::withHeaders(['access_token' => $config->api_key])->acceptJson();
    }

    private function baseUrl(ConfigAssinatura $config): string
    {
        return $config->ambiente === 'producao'
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';
    }
}
