<?php

namespace App\Console\Commands;

use App\Models\Venda;
use App\Services\Vendas\LancamentoVendaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:lancar-vendas-plano-contas {--empresa= : Só esta empresa (id)} {--refazer : Refaz também as vendas que já têm lançamento} {--simular : Só conta, sem gravar}')]
#[Description('Lança no plano de contas (002.001, 002.002, 001.015, 001.016, 001.017) as vendas pagas que ainda não têm lançamento.')]
class LancarVendasNoPlanoDeContas extends Command
{
    public function handle(LancamentoVendaService $lancamentos): int
    {
        // Roda para todas as empresas - precisa do bypass de RLS.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $vendas = Venda::query()
            ->where('status_pagamento', 'pago')
            ->whereNull('cancelada_em')
            ->when($this->option('empresa'), fn ($q, $id) => $q->where('empresa_id', $id))
            ->when(! $this->option('refazer'), fn ($q) => $q->whereDoesntHave('lancamentosPlanoContas'))
            ->orderBy('id');

        $total = (clone $vendas)->count();
        $this->info("Vendas a lançar: {$total}".($this->option('simular') ? ' (simulação, nada gravado)' : ''));

        if ($this->option('simular') || $total === 0) {
            return self::SUCCESS;
        }

        $feitas = 0;
        $vendas->chunkById(200, function ($lote) use ($lancamentos, &$feitas) {
            foreach ($lote as $venda) {
                DB::transaction(fn () => $lancamentos->registrar($venda));
                $feitas++;
            }
        });

        $this->info("Vendas lançadas: {$feitas}");
        $this->line('Obs.: o custo usa o preço de custo atual do produto e o custo atual da visita (0 se não informado).');

        return self::SUCCESS;
    }
}
