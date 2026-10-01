<?php

namespace App\Services\Fiscal;

use App\Models\Produto;

/**
 * Calcula ICMS, IPI, PIS e COFINS de um item de NFe para empresas do regime
 * normal (Lucro Presumido/Real, CRT 3), a partir do cadastro fiscal do produto.
 *
 * Só calcula o que dá para calcular com os dados do cadastro. Qualquer CST
 * fora da lista suportada (substituição tributária, diferimento, etc.) ou
 * situação sem regra implementada (venda interestadual para consumidor final,
 * que exige DIFAL) recusa a emissão com uma mensagem clara - nunca emite uma
 * nota com imposto calculado "no chute".
 *
 * Premissas (confirmar com o contador):
 *  - O frete cobrado do cliente e o IPI compõem a base do ICMS quando o
 *    destinatário não é contribuinte (consumidor final); entre contribuintes
 *    o IPI fica fora da base.
 *  - A base de PIS/COFINS exclui o ICMS destacado (tese do STF) quando
 *    config_fiscal.pis_cofins_exclui_icms está ligado (padrão).
 */
class ImpostosNfeService
{
    private const ICMS_SUPORTADOS = ['00', '20', '40', '41', '50'];

    /** UFs de origem (Sul e Sudeste, exceto ES) cuja alíquota interestadual para N/NE/CO/ES é 7%. */
    private const UF_ORIGEM_7 = ['SP', 'RJ', 'MG', 'PR', 'SC', 'RS'];

    private const UF_DESTINO_7 = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'PA', 'PB', 'PE', 'PI', 'RN', 'RO', 'RR', 'SE', 'TO'];

    /**
     * @param  array{interno: bool, uf_emitente: string, uf_destino: string, destinatario_contribuinte: bool, exclui_icms_pis_cofins: bool}  $contexto
     * @return array{
     *     orig: string,
     *     icms: array<string, mixed>,
     *     ipi: array<string, mixed>|null,
     *     pis: array<string, mixed>,
     *     cofins: array<string, mixed>
     * }
     */
    public function calcularItem(Produto $produto, float $valorProduto, float $valorFrete, array $contexto): array
    {
        $nome = $produto->nome;
        $ipi = $this->calcularIpi($produto, $valorProduto, $valorFrete);
        $vIpi = $ipi['vIPI'] ?? 0.0;

        $icms = $this->calcularIcms($produto, $valorProduto, $valorFrete, $vIpi, $contexto);

        $baseIcmsPisCofins = $valorProduto + $valorFrete - ($contexto['exclui_icms_pis_cofins'] ? $icms['vICMS'] : 0.0);

        return [
            'orig' => $produto->cst_origem !== null && $produto->cst_origem !== '' ? substr((string) $produto->cst_origem, 0, 1) : '0',
            'icms' => $icms,
            'ipi' => $ipi,
            'pis' => $this->calcularPisCofins($nome, 'PIS', $produto->cst_pis, $produto->aliquota_pis, $baseIcmsPisCofins),
            'cofins' => $this->calcularPisCofins($nome, 'COFINS', $produto->cst_cofins, $produto->aliquota_cofins, $baseIcmsPisCofins),
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private function calcularIcms(Produto $produto, float $valorProduto, float $valorFrete, float $vIpi, array $contexto): array
    {
        $cst = (string) $produto->cst_icms;

        if ($cst === '') {
            throw new \RuntimeException("Produto \"{$produto->nome}\" sem CST de ICMS cadastrado (obrigatório no regime normal).");
        }

        if (! in_array($cst, self::ICMS_SUPORTADOS, true)) {
            throw new \RuntimeException(
                "CST de ICMS {$cst} do produto \"{$produto->nome}\" ainda não é suportado na emissão (suportados: ".implode(', ', self::ICMS_SUPORTADOS).'). '
                .'Substituição tributária e diferimento exigem tratamento específico.'
            );
        }

        if (in_array($cst, ['40', '41', '50'], true)) {
            return ['cst' => $cst, 'vBC' => 0.0, 'pICMS' => 0.0, 'vICMS' => 0.0];
        }

        $aliquota = $this->aliquotaIcms($produto, $contexto);

        $integraIpi = ! $contexto['destinatario_contribuinte'];
        $base = $valorProduto + $valorFrete + ($integraIpi ? $vIpi : 0.0);

        $reducao = null;
        if ($cst === '20') {
            $reducao = (float) $produto->reducao_base_calculo_icms;

            if ($reducao <= 0 || $reducao >= 100) {
                throw new \RuntimeException("Produto \"{$produto->nome}\" com CST 20 precisa de percentual de redução de base entre 0 e 100.");
            }

            $base = $base * (1 - $reducao / 100);
        }

        $base = round($base, 2);

        $resultado = [
            'cst' => $cst,
            'modBC' => 3, // valor da operação
            'vBC' => $base,
            'pICMS' => $aliquota,
            'vICMS' => round($base * $aliquota / 100, 2),
            'pRedBC' => $reducao,
        ];

        $fcp = (float) $produto->fcp_percentual;
        if ($fcp > 0 && $contexto['interno']) {
            $resultado['vBCFCP'] = $base;
            $resultado['pFCP'] = $fcp;
            $resultado['vFCP'] = round($base * $fcp / 100, 2);
        }

        return $resultado;
    }

    /**
     * Interna: alíquota do cadastro. Interestadual: só para destinatário
     * contribuinte (7% ou 12%); para consumidor final seria DIFAL.
     *
     * @param  array<string, mixed>  $contexto
     */
    private function aliquotaIcms(Produto $produto, array $contexto): float
    {
        if ($contexto['interno']) {
            $aliquota = (float) $produto->aliquota_icms;

            if ($aliquota <= 0) {
                throw new \RuntimeException("Produto \"{$produto->nome}\" sem alíquota de ICMS cadastrada (CST {$produto->cst_icms}).");
            }

            return $aliquota;
        }

        if (! $contexto['destinatario_contribuinte']) {
            throw new \RuntimeException(
                'Venda interestadual para consumidor final (não contribuinte) exige o cálculo do DIFAL, que ainda não é suportado. '
                .'Emita para um destinatário contribuinte (com Inscrição Estadual) ou dentro do estado.'
            );
        }

        $origem7 = in_array(strtoupper($contexto['uf_emitente']), self::UF_ORIGEM_7, true);
        $destino7 = in_array(strtoupper($contexto['uf_destino']), self::UF_DESTINO_7, true);

        return ($origem7 && $destino7) ? 7.0 : 12.0;
    }

    /**
     * @return array<string, mixed>|null  null quando o produto não tem IPI cadastrado
     */
    private function calcularIpi(Produto $produto, float $valorProduto, float $valorFrete): ?array
    {
        $cst = (string) $produto->cst_ipi;

        if ($cst === '') {
            return null;
        }

        $enquadramento = (string) ($produto->codigo_enquadramento_ipi ?: '999');

        if (! in_array($cst, ['00', '49', '50', '99'], true)) {
            // CSTs de IPI não tributado/isento/imune/suspenso: só informa o CST e o enquadramento.
            return ['cst' => $cst, 'cEnq' => $enquadramento, 'vIPI' => 0.0];
        }

        $aliquota = (float) $produto->aliquota_ipi;
        $base = round($valorProduto + $valorFrete, 2);

        return [
            'cst' => $cst,
            'cEnq' => $enquadramento,
            'vBC' => $base,
            'pIPI' => $aliquota,
            'vIPI' => round($base * $aliquota / 100, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calcularPisCofins(string $nomeProduto, string $imposto, ?string $cst, mixed $aliquota, float $base): array
    {
        $cst = (string) $cst;

        if ($cst === '') {
            throw new \RuntimeException("Produto \"{$nomeProduto}\" sem CST de {$imposto} cadastrado (obrigatório no regime normal).");
        }

        if (in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            return ['cst' => $cst];
        }

        if (! in_array($cst, ['01', '02', '49', '99'], true)) {
            throw new \RuntimeException("CST de {$imposto} {$cst} do produto \"{$nomeProduto}\" ainda não é suportado na emissão.");
        }

        $percentual = (float) $aliquota;
        $base = round(max($base, 0), 2);

        return [
            'cst' => $cst,
            'vBC' => $base,
            'pAliq' => $percentual,
            'valor' => round($base * $percentual / 100, 2),
        ];
    }
}
