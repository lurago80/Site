<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;

/**
 * PENDENTE: mesma interface do MercadoPagoPagamentoGateway, mas ainda
 * não implementada de verdade - aguardando credenciais de sandbox da
 * Cielo (client_id/client_secret = MerchantId/MerchantKey) para poder
 * testar, ver decisão registrada em conversa com o cliente em 2026-07-18.
 *
 * Quando houver credenciais: replicar o padrão do gateway do Mercado
 * Pago, usando a Cielo E-commerce API
 * (https://developercielo.github.io/manual/cielo-ecommerce).
 */
class CieloPagamentoGateway implements PagamentoGatewayInterface
{
    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca
    {
        throw new \RuntimeException('Gateway Cielo ainda não implementado - aguardando credenciais de teste.');
    }

    public function criarCobrancaCartao(
        Venda $venda,
        ConfigPagamento $config,
        string $tokenCartao,
        int $parcelas,
        string $metodoPagamento,
    ): ResultadoCobranca {
        throw new \RuntimeException('Gateway Cielo ainda não implementado - aguardando credenciais de teste.');
    }

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string
    {
        throw new \RuntimeException('Gateway Cielo ainda não implementado - aguardando credenciais de teste.');
    }
}
