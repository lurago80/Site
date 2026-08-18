<?php

namespace App\Services\Fiscal;

/**
 * Resolve o CFOP correto de um item de NFe conforme a UF do emitente x
 * UF do destinatário, e o tipo de operação do documento fiscal.
 *
 * CFOP 5929/6929 = "Lançamento efetuado em decorrência de emissão de
 * documento fiscal relativo a operação ou prestação também registrada
 * em ECF" - código fixo para esse cenário, não depende do produto
 * (Escopo v2, pedido do cliente em 2026-07-18: importar venda NFC-e
 * para NFe usando 5929 dentro do estado / 6929 fora do estado).
 *
 * CFOP 1202/2202 = "Devolução de venda de mercadoria adquirida ou
 * recebida de terceiros" - cliente devolvendo mercadoria à empresa
 * (operação de entrada).
 *
 * CFOP 5202/6202 = "Devolução de compra para comercialização" - empresa
 * devolvendo mercadoria ao fornecedor (operação de saída), ver
 * EmissaoFiscalService::emitirDevolucaoFornecedor().
 */
class CfopResolver
{
    public const TIPO_VENDA = 'venda';

    public const TIPO_REGULARIZACAO_NFCE = 'regularizacao_nfce';

    public const TIPO_DEVOLUCAO_CLIENTE = 'devolucao_cliente';

    public const TIPO_DEVOLUCAO_FORNECEDOR = 'devolucao_fornecedor';

    private const CFOP_REGULARIZACAO_INTERNO = '5929';

    private const CFOP_REGULARIZACAO_INTERESTADUAL = '6929';

    private const CFOP_DEVOLUCAO_CLIENTE_INTERNO = '1202';

    private const CFOP_DEVOLUCAO_CLIENTE_INTERESTADUAL = '2202';

    private const CFOP_DEVOLUCAO_FORNECEDOR_INTERNO = '5202';

    private const CFOP_DEVOLUCAO_FORNECEDOR_INTERESTADUAL = '6202';

    private const CFOP_VENDA_PADRAO = '5102';

    private const TIPOS_VALIDOS = [
        self::TIPO_VENDA,
        self::TIPO_REGULARIZACAO_NFCE,
        self::TIPO_DEVOLUCAO_CLIENTE,
        self::TIPO_DEVOLUCAO_FORNECEDOR,
    ];

    public function resolver(
        string $ufEmitente,
        string $ufDestinatario,
        ?string $cfopProdutoPadrao,
        string $tipoOperacao,
    ): string {
        if (! in_array($tipoOperacao, self::TIPOS_VALIDOS, true)) {
            throw new \InvalidArgumentException("Tipo de operação inválido para resolução de CFOP: {$tipoOperacao}.");
        }

        $interno = strtoupper($ufEmitente) === strtoupper($ufDestinatario);

        if ($tipoOperacao === self::TIPO_REGULARIZACAO_NFCE) {
            return $interno ? self::CFOP_REGULARIZACAO_INTERNO : self::CFOP_REGULARIZACAO_INTERESTADUAL;
        }

        if ($tipoOperacao === self::TIPO_DEVOLUCAO_CLIENTE) {
            return $interno ? self::CFOP_DEVOLUCAO_CLIENTE_INTERNO : self::CFOP_DEVOLUCAO_CLIENTE_INTERESTADUAL;
        }

        if ($tipoOperacao === self::TIPO_DEVOLUCAO_FORNECEDOR) {
            return $interno ? self::CFOP_DEVOLUCAO_FORNECEDOR_INTERNO : self::CFOP_DEVOLUCAO_FORNECEDOR_INTERESTADUAL;
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
