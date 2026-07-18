<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Services\Fiscal\Dto\ResultadoEmissaoFiscal;
use App\Services\Fiscal\Dto\ResultadoEventoFiscal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gateway fiscal "de mentira": não fala com a SEFAZ, apenas simula uma
 * autorização instantânea. Usado em desenvolvimento/homologação antes
 * de haver um certificado digital A1 de testes disponível (ver decisão
 * registrada em conversa com o cliente, Fase do módulo fiscal).
 *
 * A chave de acesso e o protocolo gerados aqui NÃO são válidos perante
 * a SEFAZ - servem só para exercitar o resto do fluxo (numeração,
 * persistência, itens fiscais) sem depender de infraestrutura real.
 */
class SimuladoFiscalGateway implements FiscalGatewayInterface
{
    public function emitir(
        DocumentoFiscal $documento,
        Collection $itens,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
    ): ResultadoEmissaoFiscal {
        $prefixo = str_pad((string) $documento->modelo, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $documento->numero, 9, '0', STR_PAD_LEFT);

        $preenchimento = '';
        while (strlen($preenchimento) < 44 - strlen($prefixo)) {
            $preenchimento .= (string) random_int(0, 9);
        }

        return new ResultadoEmissaoFiscal(
            status: 'autorizada',
            chaveAcesso: $prefixo.$preenchimento,
            protocoloAutorizacao: 'SIMULADO-'.Str::random(15),
            xml: null,
            motivoRejeicao: null,
        );
    }

    public function cancelar(
        DocumentoFiscal $documento,
        string $justificativa,
        Empresa $empresa,
        ConfigFiscal $configFiscal,
        ?CertificadoDigital $certificado,
    ): ResultadoEventoFiscal {
        return new ResultadoEventoFiscal(
            status: 'homologada',
            protocolo: 'SIMULADO-CANC-'.Str::random(15),
        );
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
        return new ResultadoEventoFiscal(
            status: 'homologada',
            protocolo: 'SIMULADO-INUT-'.Str::random(15),
        );
    }
}
