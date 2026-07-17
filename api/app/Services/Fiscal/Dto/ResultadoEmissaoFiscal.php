<?php

namespace App\Services\Fiscal\Dto;

/**
 * Retorno padronizado de qualquer gateway fiscal (ver FiscalGatewayInterface).
 * Independe de qual biblioteca/fornecedor gerou o documento - é o contrato
 * que o resto do sistema conhece (Escopo v2, seção 3.4 - módulo fiscal plugável).
 */
final class ResultadoEmissaoFiscal
{
    public function __construct(
        public readonly string $status, // autorizada | rejeitada | denegada | contingencia
        public readonly ?string $chaveAcesso,
        public readonly ?string $protocoloAutorizacao,
        public readonly ?string $xml,
        public readonly ?string $motivoRejeicao = null,
    ) {}
}
