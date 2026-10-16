<?php

namespace App\Services\Pagamento\Dto;

/**
 * Retorno padronizado de qualquer gateway de pagamento - mesmo espírito
 * do ResultadoEmissaoFiscal: o resto do sistema só conhece este
 * contrato, nunca a resposta bruta de um gateway específico.
 */
final class ResultadoCobranca
{
    public function __construct(
        public readonly string $status, // pendente | aprovado | recusado
        public readonly ?string $referenciaExterna,
        // Referência gerada por NÓS antes de chamar o gateway (ex.: orderReference
        // da DeltaPag) - só preenchido por gateways cujo webhook não identifica a
        // cobrança sozinho, para o controller de webhook conseguir correlacionar.
        public readonly ?string $referenciaPedido = null,
        public readonly ?string $qrCode = null,
        public readonly ?string $qrCodeBase64 = null,
        public readonly ?string $linkPagamento = null,
        public readonly ?\DateTimeInterface $expiraEm = null,
        public readonly ?array $payloadBruto = null,
        public readonly ?string $motivoRejeicao = null,
    ) {}
}
