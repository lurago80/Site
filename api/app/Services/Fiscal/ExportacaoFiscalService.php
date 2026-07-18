<?php

namespace App\Services\Fiscal;

use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Relatórios e exportações do módulo fiscal: listagem/totais para o
 * painel de gestão, pacote de XMLs e planilha resumo para o contador
 * (Escopo v2 - itens solicitados pelo cliente em 2026-07-18).
 */
class ExportacaoFiscalService
{
    /**
     * @return Collection<int, DocumentoFiscal>
     */
    public function relatorio(
        Empresa $empresa,
        ?string $dataInicio,
        ?string $dataFim,
        ?string $status,
        ?int $modelo = null,
    ): Collection {
        return DocumentoFiscal::query()
            ->where('empresa_id', $empresa->id)
            ->when($dataInicio, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dataFim, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($modelo, fn ($q, $m) => $q->where('modelo', $m))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Empacota os XMLs dos documentos fiscais do período num .zip e
     * devolve o caminho do arquivo gerado.
     */
    public function exportarXmlsZip(Empresa $empresa, ?string $dataInicio, ?string $dataFim, ?int $modelo = null): string
    {
        $documentos = $this->relatorio($empresa, $dataInicio, $dataFim, null, $modelo)
            ->filter(fn (DocumentoFiscal $d) => ! empty($d->xml_path) && file_exists($d->xml_path));

        $nomeZip = "fiscal/exports/{$empresa->id}/xmls-".now()->format('YmdHis').'.zip';
        $caminhoAbsoluto = Storage::path($nomeZip);
        Storage::makeDirectory(dirname($nomeZip));

        $zip = new \ZipArchive();
        $zip->open($caminhoAbsoluto, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($documentos as $documento) {
            $zip->addFile($documento->xml_path, basename($documento->xml_path));
        }

        $zip->close();

        return $caminhoAbsoluto;
    }

    /**
     * Gera um CSV resumo (uma linha por documento fiscal) para envio ao
     * contador: número, série, modelo, status, datas, valores e chave.
     */
    public function exportarRelatorioContadorCsv(Empresa $empresa, ?string $dataInicio, ?string $dataFim, ?int $modelo = null): string
    {
        $documentos = $this->relatorio($empresa, $dataInicio, $dataFim, null, $modelo);

        $nomeArquivo = "fiscal/exports/{$empresa->id}/relatorio-contador-".now()->format('YmdHis').'.csv';
        $caminhoAbsoluto = Storage::path($nomeArquivo);
        Storage::makeDirectory(dirname($nomeArquivo));

        $handle = fopen($caminhoAbsoluto, 'w');
        fputcsv($handle, [
            'Documento', 'Modelo', 'Série', 'Número', 'Status', 'Data Emissão',
            'Chave de Acesso', 'Valor Total', 'Valor ICMS', 'Valor PIS', 'Valor COFINS',
        ], ';');

        foreach ($documentos as $documento) {
            fputcsv($handle, [
                $documento->id,
                $documento->modelo === 55 ? 'NFe' : 'NFC-e',
                $documento->serie,
                $documento->numero,
                $documento->status,
                $documento->created_at->format('d/m/Y H:i'),
                $documento->chave_acesso,
                number_format((float) $documento->total, 2, ',', '.'),
                number_format((float) $documento->valor_icms, 2, ',', '.'),
                number_format((float) $documento->valor_pis, 2, ',', '.'),
                number_format((float) $documento->valor_cofins, 2, ',', '.'),
            ], ';');
        }

        fclose($handle);

        return $caminhoAbsoluto;
    }
}
