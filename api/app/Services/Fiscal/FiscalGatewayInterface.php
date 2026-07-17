<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Services\Fiscal\Dto\ResultadoEmissaoFiscal;
use Illuminate\Support\Collection;

/**
 * Único ponto de contato do sistema com "o mundo fiscal" (Escopo v2,
 * seção 3.4). A API central nunca fala com a SEFAZ ou com uma lib
 * específica diretamente - sempre por trás desta interface, para que
 * a Fase 2 (troca por API paga como Focus NFe/PlugNotas/eNotas) seja
 * só uma nova implementação, sem mexer no resto do sistema.
 */
interface FiscalGatewayInterface
{
    /**
     * @param  Collection<int, \App\Models\DocumentoFiscalItem>  $itens  itens ainda não persistidos (em memória)
     */
    public function emitir(
        DocumentoFiscal $documento,
        Collection $itens,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
    ): ResultadoEmissaoFiscal;
}
