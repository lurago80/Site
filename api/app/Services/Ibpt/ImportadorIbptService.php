<?php

namespace App\Services\Ibpt;

use App\Models\IbptProduto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importa o .csv oficial do IBPT (Lei da Transparência Fiscal,
 * 12.741/2012) para a tabela ibpt_produtos. O arquivo vem
 * ponto-e-vírgula, em Latin-1/Windows-1252 (não UTF-8) - direto do
 * IBPT, sem opção de exportar diferente.
 *
 * Layout esperado (cabeçalho na primeira linha):
 * codigo;ex;tipo;descricao;nacionalfederal;importadosfederal;estadual;
 * municipal;vigenciainicio;vigenciafim;chave;versao;fonte
 *
 * A cada importação, a tabela inteira é substituída (truncate +
 * insert) - a tabela do IBPT é uma foto de um período de vigência,
 * não faz sentido mesclar com a versão anterior.
 */
class ImportadorIbptService
{
    private const TAMANHO_LOTE = 500;

    public function importar(string $caminhoArquivo): array
    {
        $handle = fopen($caminhoArquivo, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo enviado.');
        }

        $cabecalho = fgetcsv($handle, 0, ';', '"');

        if ($cabecalho === false || ! in_array('codigo', array_map('strtolower', $cabecalho))) {
            fclose($handle);
            throw new \RuntimeException('Arquivo não parece ser uma tabela IBPT válida (cabeçalho com "codigo" não encontrado).');
        }

        $total = 0;
        $lote = [];

        DB::transaction(function () use ($handle, &$total, &$lote) {
            DB::table('ibpt_produtos')->truncate();

            while (($linha = fgetcsv($handle, 0, ';', '"')) !== false) {
                if (count($linha) < 9) {
                    continue; // linha em branco/mal formada - ignora
                }

                $lote[] = $this->mapearLinha($linha);
                $total++;

                if (count($lote) >= self::TAMANHO_LOTE) {
                    DB::table('ibpt_produtos')->insert($lote);
                    $lote = [];
                }
            }

            if (! empty($lote)) {
                DB::table('ibpt_produtos')->insert($lote);
            }
        });

        fclose($handle);

        return ['total_importado' => $total];
    }

    private function mapearLinha(array $linha): array
    {
        [$codigo, $ex, $tipo, $descricao, $nacionalFederal, $importadosFederal, $estadual, $municipal,
            $vigenciaInicio, $vigenciaFim, $chave, $versao, $fonte] = array_pad($linha, 13, null);

        return [
            'codigo' => $this->converter($codigo),
            'ex' => $this->converter($ex) ?: null,
            'tipo' => (int) $tipo,
            'descricao' => $this->converter($descricao),
            'aliquota_nacional_federal' => $this->paraDecimal($nacionalFederal),
            'aliquota_importados_federal' => $this->paraDecimal($importadosFederal),
            'aliquota_estadual' => $this->paraDecimal($estadual),
            'aliquota_municipal' => $this->paraDecimal($municipal),
            'vigencia_inicio' => $this->paraData($vigenciaInicio),
            'vigencia_fim' => $this->paraData($vigenciaFim),
            'chave' => $this->converter($chave),
            'versao' => $this->converter($versao),
            'fonte' => $this->converter($fonte),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function converter(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $utf8 = mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1');

        return trim($utf8) ?: null;
    }

    private function paraDecimal(?string $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) str_replace(',', '.', $valor);
    }

    private function paraData(?string $valor): ?string
    {
        if (empty($valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', trim($valor))->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
