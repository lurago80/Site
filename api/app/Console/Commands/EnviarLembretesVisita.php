<?php

namespace App\Console\Commands;

use App\Models\AgendaVisitacao;
use App\Models\ItemVenda;
use App\Services\Notificacao\NotificacaoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:enviar-lembretes-visita')]
#[Description('Envia um lembrete de WhatsApp para clientes com visita agendada para amanhã.')]
class EnviarLembretesVisita extends Command
{
    public function handle(NotificacaoService $notificacaoService): int
    {
        // Comando roda para todas as empresas — precisa do bypass de RLS.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $amanha = now()->addDay();
        $agendas = AgendaVisitacao::query()
            ->whereBetween('data_hora', [$amanha->copy()->startOfDay(), $amanha->copy()->endOfDay()])
            ->get();

        $enviados = 0;

        foreach ($agendas as $agenda) {
            $itens = ItemVenda::where('agenda_visitacao_id', $agenda->id)
                ->whereHas('venda', fn ($q) => $q->where('status_pagamento', 'pago'))
                ->with('venda.cliente')
                ->get();

            foreach ($itens as $item) {
                $cliente = $item->venda?->cliente;

                if ($cliente === null || empty($cliente->telefone)) {
                    continue;
                }

                $notificacaoService->enviarLembreteVisita($agenda, $cliente, $cliente->telefone);
                $enviados++;
            }
        }

        $this->info("Lembretes enviados: {$enviados}");

        return self::SUCCESS;
    }
}
