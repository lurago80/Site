<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;

/**
 * Único ponto de contato do sistema com "o mundo de pagamentos" - mesmo
 * princípio do FiscalGatewayInterface (Escopo v2, seção 3.4): a API
 * central nunca fala com Mercado Pago/PagSeguro/Cielo diretamente,
 * sempre por trás desta interface.
 *
 * Diferença importante em relação ao módulo fiscal: o gateway de
 * pagamento é escolhido POR EMPRESA (cada cliente da plataforma pode
 * ter taxas melhores em gateways diferentes), não por configuração
 * global do sistema - ver PagamentoGatewayFactory.
 */
interface PagamentoGatewayInterface
{
    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca;

    /**
     * @param  string  $tokenCartao  token gerado no front-end (nunca recebemos o número do cartão no servidor)
     */
    public function criarCobrancaCartao(
        Venda $venda,
        ConfigPagamento $config,
        string $tokenCartao,
        int $parcelas,
        string $metodoPagamento,
    ): ResultadoCobranca;

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string;
}
