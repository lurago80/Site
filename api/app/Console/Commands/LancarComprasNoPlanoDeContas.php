<?php

namespace App\Console\Commands;

use App\Models\Compra;
use App\Services\Compras\LancamentoCompraService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:lancar-compras-plano-contas {--empresa= : Só esta empresa (id)} {--refazer : Refaz também as compras que já têm lançamento} {--simular : Só conta, sem gravar}')]
#[Description('Lança no plano de contas (001.018 - entrada de notas) as compras confirmadas que ainda não têm lançamento.')]
class LancarComprasNoPlanoDeContas extends Command
{
    public function handle(LancamentoCompraService $lancamentos): int
    {
        // Roda para todas as empresas - precisa do bypass de RLS.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $compras = Compra::query()
            ->where('status', 'confirmada')
            ->when($this->option('empresa'), fn ($q, $id) => $q->where('empresa_id', $id))
            ->when(! $this->option('refazer'), fn ($q) => $q->whereDoesntHave('lancamentosPlanoContas'))
            ->orderBy('id');

        $total = (clone $compras)->count();
        $this->info("Compras a lançar: {$total}".($this->option('simular') ? ' (simulação, nada gravado)' : ''));

        if ($this->option('simular') || $total === 0) {
            return self::SUCCESS;
        }

        $feitas = 0;
        $compras->chunkById(200, function ($lote) use ($lancamentos, &$feitas) {
            foreach ($lote as $compra) {
                DB::transaction(fn () => $lancamentos->registrar($compra));
                $feitas++;
            }
        });

        $this->info("Compras lançadas: {$feitas}");

        return self::SUCCESS;
    }
}
