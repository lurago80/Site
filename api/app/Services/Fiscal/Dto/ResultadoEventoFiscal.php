<?php

namespace App\Services\Fiscal\Dto;

/**
 * Retorno padronizado de um evento fiscal (cancelamento ou inutilização
 * de numeração) - mesmo espírito do ResultadoEmissaoFiscal, mas sem os
 * campos que só fazem sentido para uma emissão (chave de acesso, xml).
 */
final class ResultadoEventoFiscal
{
    public function __construct(
        public readonly string $status, // homologada | rejeitada
        public readonly ?string $protocolo,
        public readonly ?string $motivo = null,
    ) {}
}
