<?php

namespace App\Services\Fiscal;

/**
 * Resolve o CFOP correto de um item de NFe conforme a UF do emitente x
 * UF do destinatário, e se a nota é uma venda normal ou uma
 * "regularização" de uma venda já documentada por NFC-e.
 *
 * CFOP 5929/6929 = "Lançamento efetuado em decorrência de emissão de
 * documento fiscal relativo a operação ou prestação também registrada
 * em ECF" - código fixo para esse cenário, não depende do produto
 * (Escopo v2, pedido do cliente em 2026-07-18: importar venda NFC-e
 * para NFe usando 5929 dentro do estado / 6929 fora do estado).
 */
class CfopResolver
{
    private const CFOP_REGULARIZACAO_INTERNO = '5929';

    private const CFOP_REGULARIZACAO_INTERESTADUAL = '6929';

    private const CFOP_VENDA_PADRAO = '5102';

    public function resolver(
        string $ufEmitente,
        string $ufDestinatario,
        ?string $cfopProdutoPadrao,
        bool $regularizacaoDeNfce,
    ): string {
        $interno = strtoupper($ufEmitente) === strtoupper($ufDestinatario);

        if ($regularizacaoDeNfce) {
            return $interno ? self::CFOP_REGULARIZACAO_INTERNO : self::CFOP_REGULARIZACAO_INTERESTADUAL;
        }

        $base = $cfopProdutoPadrao ?: self::CFOP_VENDA_PADRAO;

        if ($interno) {
            return $base;
        }

        // Convenção da tabela de CFOP: operações interestaduais trocam o
        // primeiro dígito de 5 (dentro do estado) para 6 (fora do estado).
        return '6'.substr($base, 1);
    }
}
