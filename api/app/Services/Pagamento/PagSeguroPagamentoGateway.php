<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;

/**
 * PENDENTE: mesma interface do MercadoPagoPagamentoGateway, mas ainda
 * não implementada de verdade - aguardando credenciais de sandbox do
 * PagSeguro para poder testar (não faz sentido escrever a integração
 * às cegas sem conseguir validar contra a API real, ver decisão
 * registrada em conversa com o cliente em 2026-07-18).
 *
 * Quando houver credenciais: replicar o padrão do gateway do Mercado
 * Pago (API REST + Http facade), usando o endpoint de Pix/Cobranças
 * do PagBank (https://dev.pagbank.uol.com.br/reference).
 */
class PagSeguroPagamentoGateway implements PagamentoGatewayInterface
{
    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca
    {
        throw new \RuntimeException('Gateway PagSeguro ainda não implementado - aguardando credenciais de teste.');
    }

    public function criarCobrancaCartao(
        Venda $venda,
        ConfigPagamento $config,
        string $tokenCartao,
        int $parcelas,
        string $metodoPagamento,
    ): ResultadoCobranca {
        throw new \RuntimeException('Gateway PagSeguro ainda não implementado - aguardando credenciais de teste.');
    }

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string
    {
        throw new \RuntimeException('Gateway PagSeguro ainda não implementado - aguardando credenciais de teste.');
    }
}
