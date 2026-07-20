<?php

namespace App\Console\Commands;

use App\Services\Ibpt\ImportadorIbptService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:importar-ibpt {caminho? : Caminho do .csv - padrão: IBPT/Tabela_IBPT.csv na raiz do repositório}')]
#[Description('Importa a tabela do IBPT (Lei da Transparência Fiscal) direto de um arquivo local, sem passar pelo upload HTTP - útil para tabelas grandes ou para importar via terminal no servidor.')]
class ImportarTabelaIbpt extends Command
{
    public function handle(ImportadorIbptService $importador): int
    {
        $caminho = $this->argument('caminho') ?? base_path('../IBPT/Tabela_IBPT.csv');

        if (! file_exists($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $this->info("Importando {$caminho}...");

        try {
            $resultado = $importador->importar($caminho);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Importação concluída: {$resultado['total_importado']} códigos.");

        return self::SUCCESS;
    }
}
