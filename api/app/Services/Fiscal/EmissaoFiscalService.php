<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\NumeracaoInutilizada;
use App\Models\Venda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestra a emissão, cancelamento e inutilização de documentos fiscais
 * (NFe/NFC-e): reserva o próximo número da série (com lock, mesmo
 * princípio anti-concorrência do ReservaVagaService), delega a operação
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
                'xml_path' => $this->salvarXml($venda->empresa_id, $resultado->chaveAcesso, $documento->id, $resultado->xml),
                'motivo_cancelamento' => $resultado->motivoRejeicao,
            ]);

            foreach ($itensEmMemoria as $item) {
                $item->documento_fiscal_id = $documento->id;
                $item->save();
            }

            if ($venda->tipo_doc !== 'fiscal') {
                $venda->update(['tipo_doc' => 'fiscal']);
            }

            return $documento->fresh('itens');
        });
    }

    /**
     * Converte uma venda não fiscal (ex.: registrada só no PDV sem nota)
     * em uma venda fiscal, emitindo o documento correspondente agora.
     */
    public function importarVendaNaoFiscal(Venda $venda, int $modelo): DocumentoFiscal
    {
        if ($venda->tipo_doc === 'fiscal') {
            throw new \RuntimeException('Esta venda já possui documento fiscal emitido.');
        }

        return $this->emitir($venda, $modelo);
    }

    public function cancelar(DocumentoFiscal $documento, string $justificativa): DocumentoFiscal
    {
        if (mb_strlen($justificativa) < 15) {
            throw new \InvalidArgumentException('Justificativa do cancelamento deve ter ao menos 15 caracteres.');
        }

        if ($documento->status !== 'autorizada') {
            throw new \RuntimeException('Só é possível cancelar um documento autorizado.');
        }

        return DB::transaction(function () use ($documento, $justificativa) {
            $configFiscal = ConfigFiscal::where('empresa_id', $documento->empresa_id)->firstOrFail();
            $certificado = CertificadoDigital::where('empresa_id', $documento->empresa_id)->first();

            $resultado = $this->gateway->cancelar(
                $documento,
                $justificativa,
                $documento->empresa,
                $configFiscal,
                $certificado,
            );

            if ($resultado->status !== 'homologada') {
                throw new \RuntimeException("Cancelamento rejeitado pela SEFAZ: {$resultado->motivo}");
            }

            $documento->update([
                'status' => 'cancelada',
                'motivo_cancelamento' => $justificativa,
                'data_cancelamento' => now(),
            ]);

            return $documento->fresh();
        });
    }

    public function inutilizar(
        \App\Models\Empresa $empresa,
        int $modelo,
        string $serie,
        int $numeroInicial,
        int $numeroFinal,
        string $justificativa,
    ): NumeracaoInutilizada {
        if (mb_strlen($justificativa) < 15) {
            throw new \InvalidArgumentException('Justificativa da inutilização deve ter ao menos 15 caracteres.');
        }

        if ($numeroInicial > $numeroFinal) {
            throw new \InvalidArgumentException('Número inicial não pode ser maior que o final.');
        }

        return DB::transaction(function () use ($empresa, $modelo, $serie, $numeroInicial, $numeroFinal, $justificativa) {
            $configFiscal = ConfigFiscal::where('empresa_id', $empresa->id)->firstOrFail();
            $certificado = CertificadoDigital::where('empresa_id', $empresa->id)->first();

            $resultado = $this->gateway->inutilizar(
                $empresa,
                $configFiscal,
                $certificado,
                $modelo,
                $serie,
                $numeroInicial,
                $numeroFinal,
                $justificativa,
            );

            return NumeracaoInutilizada::create([
                'empresa_id' => $empresa->id,
                'modelo' => $modelo,
                'serie' => $serie,
                'numero_inicial' => $numeroInicial,
                'numero_final' => $numeroFinal,
                'justificativa' => $justificativa,
                'status' => $resultado->status,
                'protocolo' => $resultado->protocolo,
                'motivo' => $resultado->motivo,
            ]);
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

    /**
     * Grava o XML retornado pelo gateway em disco e devolve o CAMINHO do
     * arquivo (não o conteúdo) - é isso que a coluna xml_path guarda.
     */
    private function salvarXml(int $empresaId, ?string $chaveAcesso, int $documentoId, ?string $xml): ?string
    {
        if ($xml === null) {
            return null;
        }

        $nomeArquivo = ($chaveAcesso ?: "documento-{$documentoId}").'.xml';
        $caminhoRelativo = "fiscal/xmls/{$empresaId}/{$nomeArquivo}";

        Storage::put($caminhoRelativo, $xml);

        return Storage::path($caminhoRelativo);
    }
}
