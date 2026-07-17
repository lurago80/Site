<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\Venda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra a emissão de um documento fiscal (NFe/NFC-e) a partir de
 * uma venda: reserva o próximo número da série (com lock, mesmo
 * princípio anti-concorrência do ReservaVagaService), delega a emissão
 * em si ao FiscalGatewayInterface configurado, e persiste o resultado.
 *
 * Modelo 55 = NFe, 65 = NFC-e (ver Escopo v2, seção 4.2).
 */
class EmissaoFiscalService
{
    public function __construct(private readonly FiscalGatewayInterface $gateway) {}

    public function emitir(Venda $venda, int $modelo): DocumentoFiscal
    {
        if (! in_array($modelo, [55, 65], true)) {
            throw new \InvalidArgumentException('Modelo fiscal inválido: use 55 (NFe) ou 65 (NFC-e).');
        }

        return DB::transaction(function () use ($venda, $modelo) {
            $configFiscal = ConfigFiscal::query()
                ->where('empresa_id', $venda->empresa_id)
                ->lockForUpdate()
                ->first();

            if ($configFiscal === null) {
                throw new \RuntimeException('Empresa não possui configuração fiscal cadastrada (config_fiscal).');
            }

            [$serie, $numero] = $this->proximoNumero($configFiscal, $modelo);

            $documento = DocumentoFiscal::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'modelo' => $modelo,
                'serie' => $serie,
                'numero' => $numero,
                'ambiente' => $configFiscal->ambiente_ativo,
                'status' => 'contingencia',
                'total' => $venda->valor_total,
                'valor_produtos' => $venda->valor_total,
            ]);

            $certificado = CertificadoDigital::query()
                ->where('empresa_id', $venda->empresa_id)
                ->first();

            $itensEmMemoria = $this->montarItensEmMemoria($venda, $documento);

            $resultado = $this->gateway->emitir(
                $documento,
                $itensEmMemoria,
                $venda->empresa,
                $configFiscal,
                $certificado,
            );

            $documento->update([
                'status' => $resultado->status,
                'chave_acesso' => $resultado->chaveAcesso,
                'protocolo_autorizacao' => $resultado->protocoloAutorizacao,
                'xml_path' => $resultado->xml,
                'motivo_cancelamento' => $resultado->motivoRejeicao,
            ]);

            foreach ($itensEmMemoria as $item) {
                $item->documento_fiscal_id = $documento->id;
                $item->save();
            }

            return $documento->fresh('itens');
        });
    }

    /**
     * @return array{0: string, 1: int} [serie, numero]
     */
    private function proximoNumero(ConfigFiscal $configFiscal, int $modelo): array
    {
        if ($modelo === 55) {
            $numero = $configFiscal->numero_nfe_atual + 1;
            $configFiscal->update(['numero_nfe_atual' => $numero]);

            return [$configFiscal->serie_nfe_atual, $numero];
        }

        $numero = $configFiscal->numero_nfce_atual + 1;
        $configFiscal->update(['numero_nfce_atual' => $numero]);

        return [$configFiscal->serie_nfce_atual, $numero];
    }

    /**
     * @return Collection<int, DocumentoFiscalItem>
     */
    private function montarItensEmMemoria(Venda $venda, DocumentoFiscal $documento): Collection
    {
        return $venda->itens->map(function ($itemVenda) use ($venda, $documento) {
            return new DocumentoFiscalItem([
                'empresa_id' => $venda->empresa_id,
                'documento_fiscal_id' => $documento->id,
                'item_venda_id' => $itemVenda->id,
                'produto_id' => $itemVenda->produto_id,
                'quantidade' => $itemVenda->quantidade,
                'valor_unitario' => $itemVenda->valor_unitario,
                'valor_total' => $itemVenda->valor_total,
            ]);
        });
    }
}
