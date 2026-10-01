<?php

namespace App\Services\Fiscal;

/**
 * Catálogo das operações de NFe (modelo 55) emitidas direto na retaguarda,
 * com os padrões de cada uma (natureza, CFOP-base, forma de pagamento, etc.).
 * O CFOP-base é de saída dentro do estado (5xxx); o serviço troca para 6xxx
 * quando o destinatário é de outra UF.
 */
final class OperacoesNfe
{
    public const TIPOS = [
        'venda' => [
            'rotulo' => 'Venda de mercadoria',
            'natureza' => 'Venda de mercadoria',
            'cfop' => '5102',
            'tpag' => null, // vem da forma de pagamento escolhida
            'ind_pres' => 1,
            'baixa_estoque' => true,
        ],
        'remessa' => [
            'rotulo' => 'Remessa de mercadoria',
            'natureza' => 'Remessa de mercadoria',
            'cfop' => '5949',
            'tpag' => '90', // sem pagamento
            'ind_pres' => 9,
            'baixa_estoque' => false,
        ],
        'transferencia' => [
            'rotulo' => 'Transferência de mercadoria',
            'natureza' => 'Transferência de mercadoria',
            'cfop' => '5152',
            'tpag' => '90',
            'ind_pres' => 9,
            'baixa_estoque' => false,
        ],
        'bonificacao' => [
            'rotulo' => 'Bonificação, doação ou brinde',
            'natureza' => 'Remessa em bonificação, doação ou brinde',
            'cfop' => '5910',
            'tpag' => '90',
            'ind_pres' => 9,
            'baixa_estoque' => true,
        ],
    ];

    public const MODALIDADES_FRETE = [
        0 => 'Por conta do remetente (CIF)',
        1 => 'Por conta do destinatário (FOB)',
        2 => 'Por conta de terceiros',
        3 => 'Transporte próprio do remetente',
        4 => 'Transporte próprio do destinatário',
        9 => 'Sem frete',
    ];

    /** CRT 1 e 2 = Simples Nacional (CSOSN); CRT 3 = regime normal, Lucro Presumido/Real (CST, ver ImpostosNfeService). */
    public const CRT_SUPORTADOS = ['1', '2', '3'];
}
