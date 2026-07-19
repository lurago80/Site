<?php

namespace App\Services\Assinatura;

use App\Models\Assinatura;
use App\Models\ConfigAssinatura;
use App\Models\Empresa;
use App\Models\Plano;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra a criação de uma assinatura: se o super admin configurou o
 * Asaas (ConfigAssinatura ativo), cria o cliente e a assinatura de
 * verdade lá, cobrando a empresa automaticamente todo mês. Sem Asaas
 * configurado, mantém o comportamento manual original (super admin
 * digita o status_pagamento à mão) - mesma lógica de fallback do
 * SimuladoPagamentoGateway/SimuladoNotificacaoGateway.
 */
class AssinaturaService
{
    public function __construct(private readonly AsaasGateway $gateway) {}

    public function criarAssinatura(Empresa $empresa, Plano $plano, string $statusPagamentoManual, string $inicio): Assinatura
    {
        $config = ConfigAssinatura::first();

        if ($config === null || ! $config->ativo) {
            return Assinatura::create([
                'empresa_id' => $empresa->id,
                'plano_id' => $plano->id,
                'status_pagamento' => $statusPagamentoManual,
                'inicio' => $inicio,
            ]);
        }

        return DB::transaction(function () use ($empresa, $plano, $inicio, $config) {
            if (empty($empresa->asaas_customer_id)) {
                $empresa->update(['asaas_customer_id' => $this->gateway->criarCliente($empresa, $config)]);
            }

            $resultado = $this->gateway->criarAssinatura($empresa->asaas_customer_id, $plano, $config);

            return Assinatura::create([
                'empresa_id' => $empresa->id,
                'plano_id' => $plano->id,
                'status_pagamento' => 'em_dia',
                'inicio' => $inicio,
                'proxima_cobranca' => $resultado['proximaCobranca'],
                'asaas_subscription_id' => $resultado['id'],
            ]);
        });
    }
}
