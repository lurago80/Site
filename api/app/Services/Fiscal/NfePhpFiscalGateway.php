<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Services\Fiscal\Dto\ResultadoEmissaoFiscal;
use App\Services\Fiscal\Dto\ResultadoEventoFiscal;
use Illuminate\Support\Collection;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;

/**
 * Implementação real do módulo fiscal Fase 1 (Escopo v2, seção 3.4):
 * biblioteca gratuita/open-source (NFePHP), sem custo por nota emitida.
 *
 * Cobre NFC-e (modelo 65, CSOSN 102 - Simples Nacional sem crédito) e
 * NFe (modelo 55, com destinatário e endereço completo), incluindo o
 * caso de "regularização" de uma venda NFC-e via NFe com CFOP 5929/6929
 * (ver Fiscal\CfopResolver).
 *
 * TODO antes de produção:
 * - suporte a outros regimes tributários além de Simples Nacional (CRT=1);
 * - NFe hoje assume sempre operação de venda (CFOP 5102/6102 como base) -
 *   não cobre devolução, remessa, bonificação etc.
 */
class NfePhpFiscalGateway implements FiscalGatewayInterface
{
    private const NCM_GENERICO_TODO = '22030000';

    public function __construct(private readonly CfopResolver $cfopResolver = new CfopResolver()) {}

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

        if (! in_array($documento->modelo, [55, 65], true)) {
            throw new \RuntimeException("Modelo fiscal não suportado: {$documento->modelo}.");
        }

        $tools = $this->montarTools($empresa, $configFiscal, $certificado);
        $tools->model($documento->modelo);

        $xml = $documento->modelo === 65
            ? $this->montarXmlNfce($documento, $itens, $empresa, $configFiscal)
            : $this->montarXmlNfe($documento, $itens, $empresa, $configFiscal);

        $xmlAssinado = $tools->signNFe($xml);

        $idLote = (string) random_int(1, 999999999);
        $respostaEnvio = $tools->sefazEnviaLote([$xmlAssinado], $idLote, indSinc: 1);

        return $this->interpretarResposta($respostaEnvio, $xmlAssinado);
    }

    public function cancelar(
        DocumentoFiscal $documento,
        string $justificativa,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
    ): ResultadoEventoFiscal {
        if ($certificado === null) {
            throw new \RuntimeException("Empresa {$empresa->slug} não possui certificado digital cadastrado.");
        }

        if (empty($documento->chave_acesso) || empty($documento->protocolo_autorizacao)) {
            throw new \RuntimeException('Documento sem chave de acesso/protocolo - não é possível cancelar.');
        }

        $tools = $this->montarTools($empresa, $configFiscal, $certificado);
        $tools->model($documento->modelo);

        $resposta = $tools->sefazCancela(
            $documento->chave_acesso,
            $justificativa,
            $documento->protocolo_autorizacao,
        );

        // cStat 135 = homologado dentro do prazo, 155 = homologado fora do prazo
        return $this->interpretarEvento($resposta, ['135', '155']);
    }

    public function inutilizar(
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
        int $modelo,
        string $serie,
        int $numeroInicial,
        int $numeroFinal,
        string $justificativa,
    ): ResultadoEventoFiscal {
        if ($certificado === null) {
            throw new \RuntimeException("Empresa {$empresa->slug} não possui certificado digital cadastrado.");
        }

        $tools = $this->montarTools($empresa, $configFiscal, $certificado);
        $tools->model($modelo);

        $resposta = $tools->sefazInutiliza((int) $serie, $numeroInicial, $numeroFinal, $justificativa);

        // cStat 102 = inutilização homologada
        return $this->interpretarEvento($resposta, ['102']);
    }

    /**
     * @param  string[]  $codigosSucesso
     */
    private function interpretarEvento(string $respostaSoap, array $codigosSucesso): ResultadoEventoFiscal
    {
        $limpo = preg_replace('/(<\/?)([a-zA-Z0-9]+:)/', '$1', $respostaSoap);
        $limpo = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $limpo);
        $doc = simplexml_load_string($limpo);

        if ($doc === false) {
            throw new \RuntimeException('Resposta da SEFAZ não pôde ser interpretada: '.$respostaSoap);
        }

        // infEvento (cancelamento) ou infInut (inutilização) - ambos têm cStat/xMotivo/nProt
        $noh = $doc->xpath('//infEvento')[0] ?? $doc->xpath('//infInut')[0] ?? null;

        if ($noh === null) {
            $cStat = (string) ($doc->xpath('//cStat')[0] ?? '');
            $xMotivo = (string) ($doc->xpath('//xMotivo')[0] ?? '');

            return new ResultadoEventoFiscal(status: 'rejeitada', protocolo: null, motivo: "[{$cStat}] {$xMotivo}");
        }

        $cStat = (string) $noh->cStat;
        $xMotivo = (string) $noh->xMotivo;
        $protocolo = (string) $noh->nProt;

        $sucesso = in_array($cStat, $codigosSucesso, true);

        return new ResultadoEventoFiscal(
            status: $sucesso ? 'homologada' : 'rejeitada',
            protocolo: $protocolo !== '' ? $protocolo : null,
            motivo: $sucesso ? null : "[{$cStat}] {$xMotivo}",
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
            'siglaUF' => $empresa->uf,
            // PL_010_V1.30 inclui os grupos IBS/CBS da Reforma Tributária,
            // exigidos pela SEFAZ a partir de 2026 - ver tagIBSCBS.
            'schemes' => 'PL_010_V1.30',
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
        // schema > 9 habilita os campos da Reforma Tributária (IBS/CBS)
        // exigidos pela SEFAZ a partir de 2026 - ver tagIBSCBS abaixo.
        $nfe = new Make('PL_010_V130');

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
        $std->indIntermed = 0; // 0 = operação sem intermediador/marketplace
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
            $std->NCM = $item->produto?->ncm ?: self::NCM_GENERICO_TODO;
            $std->CFOP = $item->produto?->cfop_padrao ?: '5102';
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

            $this->tagPisCofinsIsento($nfe, $numeroItem);
            $this->tagIBSCBSTeste2026($nfe, $numeroItem, $valorItem);
        }

        $this->finalizarTotaisETransporte($nfe, $valorTotal);

        $xml = $nfe->montaNFe();

        if (! empty($nfe->getErrors())) {
            throw new \RuntimeException('Erros ao montar XML da NFC-e: '.implode(' | ', $nfe->getErrors()));
        }

        return $xml;
    }

    private function montarXmlNfe(
        DocumentoFiscal $documento,
        Collection $itens,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
    ): string {
        $venda = $documento->venda;
        $cliente = $venda?->cliente;

        if ($cliente === null) {
            throw new \RuntimeException('NFe exige um cliente/destinatário identificado na venda.');
        }
        $this->validarDestinatario($cliente);

        $regularizacaoDeNfce = $documento->documento_fiscal_origem_id !== null;

        // schema > 9 habilita os campos da Reforma Tributária (IBS/CBS)
        $nfe = new Make('PL_010_V130');

        $cUF = $this->codigoUf($empresa->uf);
        $tpAmb = $configFiscal->ambiente_ativo === 'producao' ? 1 : 2;
        $interno = strtoupper($cliente->uf) === strtoupper($empresa->uf);

        $std = new \stdClass();
        $std->versao = '4.00';
        $nfe->taginfNFe($std);

        $std = new \stdClass();
        $std->cUF = $cUF;
        $std->natOp = $regularizacaoDeNfce
            ? 'Regularização de venda documentada por NFC-e'
            : 'Venda de mercadoria';
        $std->mod = 55;
        $std->serie = (int) $documento->serie;
        $std->nNF = $documento->numero;
        $std->tpNF = 1; // saída
        $std->idDest = $interno ? 1 : 2; // 1 = interna, 2 = interestadual
        $std->cMunFG = $empresa->codigo_ibge_municipio;
        $std->tpImp = 1; // DANFE retrato
        $std->tpEmis = 1; // emissão normal
        $std->tpAmb = $tpAmb;
        $std->finNFe = 1; // NFe normal
        $std->indFinal = empty($cliente->inscricao_estadual) ? 1 : 0;
        $std->indPres = $regularizacaoDeNfce ? 9 : 1; // 9 = não se aplica (venda já ocorreu no PDV)
        $std->indIntermed = 0; // 0 = operação sem intermediador/marketplace
        $std->procEmi = 0;
        $std->verProc = '1.0.0';
        $nfe->tagide($std);

        // NFref/refNFe (NFePHP tagrefNFe) só vale para referenciar outra NFe
        // (modelo 55) - a SEFAZ rejeita ("Modelo inválido") quando a chave
        // referenciada é de uma NFC-e (modelo 65). Para o cenário de
        // regularização a ligação com a NFC-e de origem já fica registrada
        // internamente (documento_fiscal_origem_id) e visível no painel -
        // o CFOP 5929/6929 já identifica a operação perante a SEFAZ.

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

        $std = new \stdClass();
        $documentoCliente = preg_replace('/\D/', '', (string) $cliente->cpf_cnpj);
        $ehPessoaJuridica = strlen($documentoCliente) === 14;
        if ($ehPessoaJuridica) {
            $std->CNPJ = $documentoCliente;
        } else {
            $std->CPF = $documentoCliente;
        }
        $std->xNome = $cliente->nome;
        if (! empty($cliente->inscricao_estadual)) {
            // 1 = contribuinte ICMS, com IE real informada
            $std->indIEDest = 1;
            $std->IE = $cliente->inscricao_estadual;
        } else {
            // 9 = não contribuinte - válido para CPF (consumidor final)
            $std->indIEDest = 9;
        }
        $std->email = $cliente->email;
        $nfe->tagdest($std);

        $std = new \stdClass();
        $std->xLgr = $cliente->logradouro;
        $std->nro = $cliente->numero;
        $std->xBairro = $cliente->bairro;
        $std->cMun = $cliente->codigo_ibge_municipio;
        $std->xMun = $cliente->municipio;
        $std->UF = $cliente->uf;
        $std->CEP = preg_replace('/\D/', '', (string) $cliente->cep);
        $std->cPais = 1058;
        $std->xPais = 'Brasil';
        $nfe->tagenderDest($std);

        $valorTotal = 0;

        foreach ($itens->values() as $index => $item) {
            $numeroItem = $index + 1;
            $valorItem = (float) $item->valor_total;
            $valorTotal += $valorItem;

            $cfop = $this->cfopResolver->resolver(
                $empresa->uf,
                $cliente->uf,
                $item->produto?->cfop_padrao,
                $regularizacaoDeNfce,
            );

            $std = new \stdClass();
            $std->item = $numeroItem;
            $std->cProd = (string) ($item->produto_id ?? $numeroItem);
            $std->cEAN = 'SEM GTIN';
            $std->xProd = $item->produto?->nome ?? 'Item de venda';
            $std->NCM = $item->produto?->ncm ?: self::NCM_GENERICO_TODO;
            $std->CFOP = $cfop;
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
            $std->CSOSN = '102';
            $nfe->tagICMSSN($std);

            $std = new \stdClass();
            $std->item = $numeroItem;
            $std->vTotTrib = 0;
            $nfe->tagimposto($std);

            $this->tagPisCofinsIsento($nfe, $numeroItem);
            $this->tagIBSCBSTeste2026($nfe, $numeroItem, $valorItem);
        }

        $this->finalizarTotaisETransporte($nfe, $valorTotal);

        $xml = $nfe->montaNFe();

        if (! empty($nfe->getErrors())) {
            throw new \RuntimeException('Erros ao montar XML da NFe: '.implode(' | ', $nfe->getErrors()));
        }

        return $xml;
    }

    private function validarDestinatario(Cliente $cliente): void
    {
        $faltando = array_filter([
            'cpf_cnpj' => $cliente->cpf_cnpj,
            'uf' => $cliente->uf,
            'municipio' => $cliente->municipio,
            'codigo_ibge_municipio' => $cliente->codigo_ibge_municipio,
            'logradouro' => $cliente->logradouro,
            'numero' => $cliente->numero,
            'bairro' => $cliente->bairro,
        ], fn ($valor) => empty($valor));

        if (! empty($faltando)) {
            $campos = implode(', ', array_keys($faltando));
            throw new \RuntimeException("Cliente sem dados obrigatórios para NFe: {$campos}.");
        }
    }

    /**
     * PIS/COFINS não tributados (CST 07) - regime Simples Nacional não
     * destaca PIS/COFINS por fora (já estão dentro do DAS unificado),
     * mas a SEFAZ exige os grupos mesmo assim, com CST 07 (PISNT/COFINSNT).
     */
    private function tagPisCofinsIsento(Make $nfe, int $numeroItem): void
    {
        $std = new \stdClass();
        $std->item = $numeroItem;
        $std->CST = '07';
        $nfe->tagPIS($std);

        $std = new \stdClass();
        $std->item = $numeroItem;
        $std->CST = '07';
        $nfe->tagCOFINS($std);
    }

    /**
     * Grupo IBS/CBS da Reforma Tributária - alíquotas de teste da fase
     * de transição 2026 (LC 214/2025): CBS 0,9% / IBS 0,1% combinados.
     * CST 000 = tributação integral, cClassTrib 000001 = padrão sem
     * benefício. TODO: revisar quando a SEFAZ consolidar a tabela
     * definitiva de cClassTrib por segmento.
     */
    private function tagIBSCBSTeste2026(Make $nfe, int $numeroItem, float $valorItem): void
    {
        $vIBS = round($valorItem * 0.001, 2);
        $vCBS = round($valorItem * 0.009, 2);

        $std = new \stdClass();
        $std->item = $numeroItem;
        $std->CST = '000';
        $std->cClassTrib = '000001';
        $std->vBC = $valorItem;
        $std->gIBSUF_pIBSUF = 0.10;
        $std->gIBSUF_vIBSUF = $vIBS;
        $std->gIBSMun_pIBSMun = 0;
        $std->gIBSMun_vIBSMun = 0;
        $std->gCBS_pCBS = 0.90;
        $std->gCBS_vCBS = $vCBS;
        $nfe->tagIBSCBS($std);
    }

    private function finalizarTotaisETransporte(Make $nfe, float $valorTotal): void
    {
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
