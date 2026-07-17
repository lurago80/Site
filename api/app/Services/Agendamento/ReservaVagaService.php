<?php

namespace App\Services\Agendamento;

use App\Exceptions\VagasIndisponiveisException;
use App\Models\AgendaVisitacao;
use App\Models\ReservaTemporaria;
use Illuminate\Support\Facades\DB;

/**
 * Único ponto de entrada para reservar/confirmar/liberar vagas de uma
 * agenda_visitacao — usado tanto pelo checkout da loja pública quanto
 * pela venda no PDV (ver Escopo v2, seção 3.5).
 *
 * O controle de overbooking depende inteiramente de dois fatores:
 * 1. lockForUpdate() trava a linha da agenda até o fim da transação,
 *    serializando qualquer tentativa concorrente no mesmo horário;
 * 2. nenhum outro lugar do sistema pode alterar vagas_reservadas
 *    fora desta classe.
 */
class ReservaVagaService
{
    private const MINUTOS_EXPIRACAO = 15;

    public function reservar(int $agendaVisitacaoId, int $quantidade): ReservaTemporaria
    {
        if ($quantidade < 1) {
            throw new \InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        return DB::transaction(function () use ($agendaVisitacaoId, $quantidade) {
            $agenda = AgendaVisitacao::query()
                ->whereKey($agendaVisitacaoId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($agenda->status !== 'aberta') {
                throw new VagasIndisponiveisException('Horário não está disponível para reserva.');
            }

            if ($agenda->vagasDisponiveis() < $quantidade) {
                throw new VagasIndisponiveisException('Vagas insuficientes para este horário.');
            }

            $reserva = ReservaTemporaria::create([
                'empresa_id' => $agenda->empresa_id,
                'agenda_visitacao_id' => $agenda->id,
                'quantidade' => $quantidade,
                'expira_em' => now()->addMinutes(self::MINUTOS_EXPIRACAO),
                'status' => 'ativa',
            ]);

            $agenda->increment('vagas_reservadas', $quantidade);

            if ($agenda->fresh()->vagasDisponiveis() <= 0) {
                $agenda->update(['status' => 'lotada']);
            }

            return $reserva;
        });
    }

    /**
     * Converte uma reserva temporária em vaga definitiva (pagamento confirmado).
     * Não mexe em vagas_reservadas — a vaga já foi contabilizada no reservar().
     */
    public function confirmar(int $reservaId): ReservaTemporaria
    {
        return DB::transaction(function () use ($reservaId) {
            $reserva = ReservaTemporaria::query()->whereKey($reservaId)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== 'ativa') {
                throw new VagasIndisponiveisException('Reserva não está mais ativa.');
            }

            $reserva->update(['status' => 'confirmada']);

            return $reserva;
        });
    }

    /**
     * Libera uma reserva (expiração ou cancelamento) e devolve a vaga ao saldo.
     * Idempotente: chamar duas vezes na mesma reserva não devolve a vaga em dobro.
     */
    public function liberar(int $reservaId, string $novoStatus = 'expirada'): void
    {
        DB::transaction(function () use ($reservaId, $novoStatus) {
            $reserva = ReservaTemporaria::query()->whereKey($reservaId)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== 'ativa') {
                return;
            }

            $agenda = AgendaVisitacao::query()
                ->whereKey($reserva->agenda_visitacao_id)
                ->lockForUpdate()
                ->firstOrFail();

            $agenda->decrement('vagas_reservadas', $reserva->quantidade);

            if ($agenda->fresh()->status === 'lotada' && $agenda->fresh()->vagasDisponiveis() > 0) {
                $agenda->update(['status' => 'aberta']);
            }

            $reserva->update(['status' => $novoStatus]);
        });
    }
}
