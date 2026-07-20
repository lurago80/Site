<?php

namespace App\Services\Fiscal;

use App\Models\Cfop;
use App\Services\Fiscal\Dados\TabelaCfopPadrao;

/**
 * Importa/atualiza a tabela CFOP padrão (Ajuste SINIEF, ver
 * TabelaCfopPadrao) na tabela global cfops. Ao contrário do IBPT, o
 * CFOP não muda com frequência nem exige upload de arquivo - a
 * "importação" é upsert por código a partir dos dados embutidos, sem
 * apagar registros que a Super Admin tenha cadastrado manualmente.
 */
class ImportadorCfopService
{
    public function importarPadrao(): array
    {
        $dados = TabelaCfopPadrao::todos();
        $total = 0;

        foreach ($dados as $codigo => $descricao) {
            Cfop::updateOrCreate(
                ['codigo' => $codigo],
                ['descricao' => $descricao],
            );
            $total++;
        }

        return ['total_importado' => $total];
    }
}
