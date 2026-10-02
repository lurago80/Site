<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Fiscal\PlanilhaFiscalProdutos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:exportar-fiscal-produtos {empresa : Slug ou ID da empresa} {caminho? : Caminho do .xlsx de saída - padrão: storage/app/exportacoes/produtos_fiscal_<slug>.xlsx}')]
#[Description('Gera a planilha Excel com todos os produtos e campos fiscais para o contador preencher.')]
class ExportarPlanilhaFiscalProdutos extends Command
{
    public function handle(PlanilhaFiscalProdutos $planilha): int
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $identificador = $this->argument('empresa');
        $empresa = Empresa::where('slug', $identificador)
            ->when(ctype_digit($identificador), fn ($q) => $q->orWhere('id', $identificador))
            ->first();

        if (! $empresa) {
            $this->error("Empresa não encontrada: {$identificador}");

            return self::FAILURE;
        }

        $caminho = $this->argument('caminho') ?? storage_path("app/exportacoes/produtos_fiscal_{$empresa->slug}.xlsx");
        if (! is_dir(dirname($caminho))) {
            mkdir(dirname($caminho), 0775, true);
        }

        $total = $planilha->exportar($empresa->id, $caminho);
        $this->info("{$total} produto(s) exportado(s) para {$caminho}");

        return self::SUCCESS;
    }
}
