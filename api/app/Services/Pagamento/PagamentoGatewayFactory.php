<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;

/**
 * O gateway de pagamento é escolhido POR EMPRESA (Escopo v2, decisão
 * de 2026-07-18: cada empresa cliente pode ter taxas melhores em
 * gateways diferentes) - por isso não é um binding único no container
 * como o FiscalGatewayInterface, e sim resolvido em tempo de execução
 * a partir do ConfigPagamento da empresa.
 */
class PagamentoGatewayFactory
{
    public function paraEmpresa(?ConfigPagamento $config): PagamentoGatewayInterface
    {
        if ($config === null || ! $config->ativo) {
            return new SimuladoPagamentoGateway();
        }

        return match ($config->gateway) {
            'mercadopago' => new MercadoPagoPagamentoGateway(),
            'pagseguro' => new PagSeguroPagamentoGateway(),
            'cielo' => new CieloPagamentoGateway(),
            default => new SimuladoPagamentoGateway(),
        };
    }
}
