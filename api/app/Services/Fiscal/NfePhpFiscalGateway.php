<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Services\Fiscal\Dto\ResultadoEmissaoFiscal;
use Illuminate\Support\Collection;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;

/**
 * Implementação real do módulo fiscal Fase 1 (Escopo v2, seção 3.4):
 * biblioteca gratuita/open-source (NFePHP), sem custo por nota emitida.
 *
 * STATUS: o carregamento de certificado e a montagem do objeto Tools
 * (que fala com a SEFAZ) já são funcionais. A geração e assinatura do
 * XML em si (NFePHP\NFe\Make) está pendente porque o cadastro atual de
 * Empresa/ConfigFiscal ainda não coleta todos os campos obrigatórios de
 * um NFe/NFC-e válido perante a SEFAZ - faltam pelo menos:
 *   - endereço completo do emitente (logradouro, número, bairro,
 *     município + código IBGE, UF, CEP);
 *   - endereço do destinatário (quando pessoa jurídica / NFe modelo 55);
 *   - tabela de tributação por produto (NCM, CEST, origem) além dos
 *     campos já previstos em documento_fiscal_item.
 * Sem um certificado A1 de testes e esses campos, não há como validar
 * a emissão de ponta a ponta - por isso o sistema roda hoje com
 * SimuladoFiscalGateway (ver config/fiscal.php) até essa etapa ser
 * retomada com um certificado de homologação em mãos.
 */
class NfePhpFiscalGateway implements FiscalGatewayInterface
{
    public function emitir(
        DocumentoFiscal $documento,
        Collection $itens,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
    ): ResultadoEmissaoFiscal {
        if ($certificado === null) {
            throw new \RuntimeException(
                "Empresa {$empresa->slug} não possui certificado digital cadastrado."
            );
        }

        $tools = $this->montarTools($empresa, $configFiscal, $certificado);

        throw new \RuntimeException(
            'Geração de XML da NFe/NFC-e ainda não implementada - faltam campos '.
            'obrigatórios no cadastro (endereço do emitente/destinatário, tabela '.
            'de tributação por produto). Ver NfePhpFiscalGateway::emitir(). '.
            'Use FISCAL_GATEWAY=simulado até um certificado de homologação estar disponível.'
        );
    }

    private function montarTools(Empresa $empresa, ConfigFiscal $configFiscal, CertificadoDigital $certificado): Tools
    {
        $certificadoPfx = Certificate::readPfx(
            file_get_contents($certificado->arquivo_referencia),
            $certificado->senha_criptografada,
        );

        $config = [
            'atualizacao' => now()->toDateTimeString(),
            'tpAmb' => $configFiscal->ambiente_ativo === 'producao' ? 1 : 2,
            'razaosocial' => $empresa->razao_social,
            'cnpj' => preg_replace('/\D/', '', $empresa->cnpj),
            // TODO: capturar a UF do emitente no cadastro de Empresa - ainda não existe no schema.
            'siglaUF' => 'SP',
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => $configFiscal->csc_nfce,
            'CSCid' => $configFiscal->id_token_csc,
        ];

        return new Tools(json_encode($config), $certificadoPfx);
    }
}
