<?php

namespace App\Console\Commands;

use App\Models\ReservaTemporaria;
use App\Services\Agendamento\ReservaVagaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:liberar-reservas-expiradas')]
#[Description('Libera reservas temporárias de vagas expiradas, devolvendo o saldo à agenda.')]
class LiberarReservasExpiradas extends Command
{
    public function handle(ReservaVagaService $reservaVagaService): int
    {
        // Comando roda para todas as empresas — precisa do bypass de RLS.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $reservasExpiradas = ReservaTemporaria::query()
            ->where('status', 'ativa')
            ->where('expira_em', '<', now())
            ->pluck('id');

        foreach ($reservasExpiradas as $reservaId) {
            $reservaVagaService->liberar($reservaId, 'expirada');
        }

        $this->info("Reservas liberadas: {$reservasExpiradas->count()}");

        return self::SUCCESS;
    }
}
