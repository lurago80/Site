<?php

namespace App\Services\Notificacao;

use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\ConfigWhatsapp;
use App\Models\Notificacao;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra o envio de notificações de WhatsApp: resolve o provedor
 * configurado pela empresa (NotificacaoGatewayFactory), delega o envio
 * em si e persiste o resultado em `notificacoes` (também é a base para
 * cobrar a empresa por mensagem enviada, ver Escopo v2). Sem cliente
 * com telefone cadastrado, ou sem provedor configurado, simplesmente
 * não bloqueia o fluxo principal (confirmação de venda/agendamento) -
 * notificação é um "extra", nunca pode derrubar a venda.
 */
class NotificacaoService
{
    public function __construct(private readonly NotificacaoGatewayFactory $factory) {}

    public function enviarConfirmacaoAgendamento(Venda $venda): ?Notificacao
    {
        $venda->loadMissing(['cliente', 'itens.agendaVisitacao']);

        $itemAgenda = $venda->itens->first(fn ($item) => $item->agenda_visitacao_id !== null);

        if ($itemAgenda === null || empty($venda->cliente?->telefone)) {
            return null;
        }

        $agenda = $itemAgenda->agendaVisitacao;
        $dataFormatada = $agenda->data_hora->format('d/m/Y \à\s H:i');

        $mensagem = "Olá, {$venda->cliente->nome}! Sua visita está confirmada para {$dataFormatada}. Até lá!";

        return $this->enviar($venda->empresa_id, $venda->cliente, $venda, 'confirmacao_agendamento', $mensagem);
    }

    public function enviarLembreteVisita(AgendaVisitacao $agenda, Cliente $cliente, string $telefone): ?Notificacao
    {
        $dataFormatada = $agenda->data_hora->format('d/m/Y \à\s H:i');
        $mensagem = "Olá, {$cliente->nome}! Passando para lembrar da sua visita amanhã, {$dataFormatada}. Te esperamos!";

        return $this->enviar($agenda->empresa_id, $cliente, null, 'lembrete_visita', $mensagem, $telefone);
    }

    private function enviar(
        int $empresaId,
        Cliente $cliente,
        ?Venda $venda,
        string $tipo,
        string $mensagem,
        ?string $telefone = null,
    ): Notificacao {
        return DB::transaction(function () use ($empresaId, $cliente, $venda, $tipo, $mensagem, $telefone) {
            $config = ConfigWhatsapp::where('empresa_id', $empresaId)->first();
            $gateway = $this->factory->paraEmpresa($config);
            $providerNome = ($config && $config->ativo) ? $config->provider : 'simulado';

            $telefoneDestino = $telefone ?? $cliente->telefone;

            $resultado = $gateway->enviarMensagem($config ?? new ConfigWhatsapp(), $telefoneDestino, $mensagem);

            return Notificacao::create([
                'empresa_id' => $empresaId,
                'cliente_id' => $cliente->id,
                'venda_id' => $venda?->id,
                'canal' => 'whatsapp',
                'tipo' => $tipo,
                'telefone' => $telefoneDestino,
                'mensagem' => $mensagem,
                'provider' => $providerNome,
                'status' => $resultado->status,
                'referencia_externa' => $resultado->referenciaExterna,
                'payload_retorno' => $resultado->payloadBruto,
            ]);
        });
    }
}
