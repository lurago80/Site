<?php

namespace App\Services\Pagamento;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\Dto\ResultadoCobranca;
use Illuminate\Support\Str;

/**
 * Gateway "de mentira": aprova instantaneamente, sem falar com nenhum
 * provedor real. Usado como padrão até a empresa configurar um gateway
 * de verdade (Config. Pagamento no dashboard) - mesma lógica do
 * SimuladoFiscalGateway para o módulo fiscal.
 */
class SimuladoPagamentoGateway implements PagamentoGatewayInterface
{
    public function criarCobrancaPix(Venda $venda, ConfigPagamento $config): ResultadoCobranca
    {
        return new ResultadoCobranca(
            status: 'aprovado',
            referenciaExterna: 'SIMULADO-'.Str::random(12),
            qrCode: '00020126SIMULADONAOUSAREMPRODUCAO5204000053039865802BR',
            qrCodeBase64: null,
        );
    }

    public function criarCobrancaCartao(
        Venda $venda,
        ConfigPagamento $config,
        string $tokenCartao,
        int $parcelas,
        string $metodoPagamento,
    ): ResultadoCobranca {
        return new ResultadoCobranca(
            status: 'aprovado',
            referenciaExterna: 'SIMULADO-'.Str::random(12),
        );
    }

    public function consultarStatus(ConfigPagamento $config, string $referenciaExterna): string
    {
        return 'aprovado';
    }
}
