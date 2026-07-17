<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Services\Fiscal\Dto\ResultadoEmissaoFiscal;
use Illuminate\Support\Collection;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;

/**
 * Implementação real do módulo fiscal Fase 1 (Escopo v2, seção 3.4):
 * biblioteca gratuita/open-source (NFePHP), sem custo por nota emitida.
 *
 * Cobre hoje a emissão de NFC-e (modelo 65) com um único CFOP/CSOSN fixo
 * (venda de mercadoria, Simples Nacional sem crédito) - suficiente para
 * validar o fluxo completo em homologação. NFe (modelo 55, com
 * destinatário e endereço completo) ainda não está implementada.
 *
 * TODO antes de produção:
 * - NCM/CFOP por produto (hoje usa um valor fixo genérico - Produto
 *   ainda não tem esses campos no cadastro);
 * - suporte a outros regimes tributários além de Simples Nacional (CRT=1);
 * - NFe modelo 55 (exige endereço completo do destinatário).
 */
class NfePhpFiscalGateway implements FiscalGatewayInterface
{
    private const NCM_GENERICO_TODO = '22030000';

    private const CFOP_VENDA_DENTRO_ESTADO = '5102';

    public function emitir(
        DocumentoFiscal $documento,
        Collection $itens,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
    ): ResultadoEmissaoFiscal {
        if ($certificado === null) {
            throw new \RuntimeException("Empresa {$empresa->slug} não possui certificado digital cadastrado.");
        }

        if ($documento->modelo !== 65) {
            throw new \RuntimeException('NfePhpFiscalGateway hoje só emite NFC-e (modelo 65). NFe (55) pendente.');
        }

        $tools = $this->montarTools($empresa, $configFiscal, $certificado);
        $tools->model(65);

        $xml = $this->montarXmlNfce($documento, $itens, $empresa, $configFiscal);

        $xmlAssinado = $tools->signNFe($xml);

        $idLote = (string) random_int(1, 999999999);
        $respostaEnvio = $tools->sefazEnviaLote([$xmlAssinado], $idLote, indSinc: 1);

        return $this->interpretarResposta($respostaEnvio, $xmlAssinado);
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
            'siglaUF' => $empresa->uf,
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => $configFiscal->csc_nfce ?? '',
            'CSCid' => $configFiscal->id_token_csc ?? '',
        ];

        return new Tools(json_encode($config), $certificadoPfx);
    }

    private function montarXmlNfce(
        DocumentoFiscal $documento,
        Collection $itens,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
    ): string {
        $nfe = new Make();

        $cUF = $this->codigoUf($empresa->uf);
        $tpAmb = $configFiscal->ambiente_ativo === 'producao' ? 1 : 2;

        $std = new \stdClass();
        $std->versao = '4.00';
        $nfe->taginfNFe($std);

        $std = new \stdClass();
        $std->cUF = $cUF;
        $std->natOp = 'Venda de mercadoria';
        $std->mod = 65;
        $std->serie = (int) $documento->serie;
        $std->nNF = $documento->numero;
        $std->tpNF = 1; // saída
        $std->idDest = 1; // operação interna
        $std->cMunFG = $empresa->codigo_ibge_municipio;
        $std->tpImp = 4; // DANFE NFC-e
        $std->tpEmis = 1; // emissão normal
        $std->tpAmb = $tpAmb;
        $std->finNFe = 1; // NFe normal
        $std->indFinal = 1; // consumidor final
        $std->indPres = 1; // operação presencial
        $std->procEmi = 0;
        $std->verProc = '1.0.0';
        $nfe->tagide($std);

        $std = new \stdClass();
        $std->CNPJ = preg_replace('/\D/', '', $empresa->cnpj);
        $std->xNome = $empresa->razao_social;
        $std->IE = $configFiscal->inscricao_estadual;
        $std->CRT = (int) $configFiscal->crt;
        $nfe->tagEmit($std);

        $std = new \stdClass();
        $std->xLgr = $empresa->logradouro;
        $std->nro = $empresa->numero;
        $std->xBairro = $empresa->bairro;
        $std->cMun = $empresa->codigo_ibge_municipio;
        $std->xMun = $empresa->municipio;
        $std->UF = $empresa->uf;
        $std->CEP = preg_replace('/\D/', '', $empresa->cep);
        $std->cPais = 1058;
        $std->xPais = 'Brasil';
        $nfe->tagenderEmit($std);

        $valorTotal = 0;

        foreach ($itens->values() as $index => $item) {
            $numeroItem = $index + 1;
            $valorItem = (float) $item->valor_total;
            $valorTotal += $valorItem;

            $std = new \stdClass();
            $std->item = $numeroItem;
            $std->cProd = (string) ($item->produto_id ?? $numeroItem);
            $std->cEAN = 'SEM GTIN';
            $std->xProd = $item->produto?->nome ?? 'Item de venda';
            $std->NCM = self::NCM_GENERICO_TODO;
            $std->CFOP = self::CFOP_VENDA_DENTRO_ESTADO;
            $std->uCom = 'UN';
            $std->qCom = (float) $item->quantidade;
            $std->vUnCom = (float) $item->valor_unitario;
            $std->vProd = $valorItem;
            $std->cEANTrib = 'SEM GTIN';
            $std->uTrib = 'UN';
            $std->qTrib = (float) $item->quantidade;
            $std->vUnTrib = (float) $item->valor_unitario;
            $std->indTot = 1;
            $nfe->tagprod($std);

            $std = new \stdClass();
            $std->item = $numeroItem;
            $std->orig = 0;
            $std->CSOSN = '102'; // Simples Nacional - sem permissão de crédito
            $nfe->tagICMSSN($std);

            $std = new \stdClass();
            $std->item = $numeroItem;
            $std->vTotTrib = 0;
            $nfe->tagimposto($std);
        }

        $std = new \stdClass();
        $std->vBC = 0;
        $std->vICMS = 0;
        $std->vICMSDeson = 0;
        $std->vProd = $valorTotal;
        $std->vFrete = 0;
        $std->vSeg = 0;
        $std->vDesc = 0;
        $std->vII = 0;
        $std->vIPI = 0;
        $std->vPIS = 0;
        $std->vCOFINS = 0;
        $std->vOutro = 0;
        $std->vNF = $valorTotal;
        $std->vTotTrib = 0;
        $nfe->tagICMSTot($std);

        $std = new \stdClass();
        $std->modFrete = 9; // sem transporte
        $nfe->tagtransp($std);

        $std = new \stdClass();
        $std->vTroco = 0;
        $nfe->tagpag($std);

        $std = new \stdClass();
        $std->tPag = '01'; // dinheiro - TODO: mapear forma_pagamento real da venda
        $std->vPag = $valorTotal;
        $nfe->tagdetPag($std);

        $xml = $nfe->montaNFe();

        if (! empty($nfe->getErrors())) {
            throw new \RuntimeException('Erros ao montar XML da NFC-e: '.implode(' | ', $nfe->getErrors()));
        }

        return $xml;
    }

    private function interpretarResposta(string $respostaSoap, string $xmlAssinado): ResultadoEmissaoFiscal
    {
        $limpo = preg_replace('/(<\/?)([a-zA-Z0-9]+:)/', '$1', $respostaSoap);
        // remove também os xmlns default (sem prefixo) - sem isso o SimpleXML
        // trata os elementos como pertencentes a um namespace e //tag não casa.
        $limpo = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $limpo);
        $doc = simplexml_load_string($limpo);

        if ($doc === false) {
            throw new \RuntimeException('Resposta da SEFAZ não pôde ser interpretada: '.$respostaSoap);
        }

        $protNFe = $doc->xpath('//protNFe')[0] ?? null;

        if ($protNFe === null) {
            $cStatLote = (string) ($doc->xpath('//cStat')[0] ?? '');
            $xMotivoLote = (string) ($doc->xpath('//xMotivo')[0] ?? '');

            return new ResultadoEmissaoFiscal(
                status: 'rejeitada',
                chaveAcesso: null,
                protocoloAutorizacao: null,
                xml: $xmlAssinado,
                motivoRejeicao: "[{$cStatLote}] {$xMotivoLote}",
            );
        }

        $infProt = $protNFe->infProt;
        $cStat = (string) $infProt->cStat;
        $xMotivo = (string) $infProt->xMotivo;
        $chave = (string) $infProt->chNFe;
        $protocolo = (string) $infProt->nProt;

        // 100 = autorizado o uso da NF-e
        $status = $cStat === '100' ? 'autorizada' : 'rejeitada';

        return new ResultadoEmissaoFiscal(
            status: $status,
            chaveAcesso: $chave !== '' ? $chave : null,
            protocoloAutorizacao: $protocolo !== '' ? $protocolo : null,
            xml: $xmlAssinado,
            motivoRejeicao: $status === 'rejeitada' ? "[{$cStat}] {$xMotivo}" : null,
        );
    }

    private function codigoUf(string $uf): int
    {
        $codigos = [
            'AC' => 12, 'AL' => 27, 'AP' => 16, 'AM' => 13, 'BA' => 29, 'CE' => 23,
            'DF' => 53, 'ES' => 32, 'GO' => 52, 'MA' => 21, 'MT' => 51, 'MS' => 50,
            'MG' => 31, 'PA' => 15, 'PB' => 25, 'PR' => 41, 'PE' => 26, 'PI' => 22,
            'RJ' => 33, 'RN' => 24, 'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42,
            'SP' => 35, 'SE' => 28, 'TO' => 17,
        ];

        return $codigos[strtoupper($uf)] ?? throw new \RuntimeException("UF inválida: {$uf}");
    }
}
