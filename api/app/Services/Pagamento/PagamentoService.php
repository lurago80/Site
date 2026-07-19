<?php

namespace App\Services\Pagamento;

use App\Models\Cobranca;
use App\Models\ConfigPagamento;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra a cobrança de uma venda: resolve o gateway configurado
 * pela empresa (PagamentoGatewayFactory), delega a criação/consulta em
 * si, e persiste o resultado em `cobrancas`. Quando a empresa não tem
 * gateway configurado (ou desativado), cai automaticamente no
 * SimuladoPagamentoGateway - aprova na hora, mesmo comportamento que o
 * checkout já tinha antes deste módulo existir.
 */
class PagamentoService
{
    public function __construct(private readonly PagamentoGatewayFactory $factory) {}

    public function criarCobrancaPix(Venda $venda): Cobranca
    {
        return DB::transaction(function () use ($venda) {
            $config = ConfigPagamento::where('empresa_id', $venda->empresa_id)->first();
            $gateway = $this->factory->paraEmpresa($config);
            $gatewayNome = ($config && $config->ativo) ? $config->gateway : 'simulado';

            $resultado = $gateway->criarCobrancaPix($venda, $config ?? new ConfigPagamento());

            $cobranca = Cobranca::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'gateway' => $gatewayNome,
                'metodo' => 'pix',
                'referencia_externa' => $resultado->referenciaExterna,
                'status' => $resultado->status,
                'valor' => $venda->valor_total,
                'qr_code' => $resultado->qrCode,
                'qr_code_base64' => $resultado->qrCodeBase64,
                'payload_retorno' => $resultado->payloadBruto,
                'expira_em' => $resultado->expiraEm,
            ]);

            if ($resultado->status === 'aprovado') {
                $venda->update(['status_pagamento' => 'pago']);
            }

            return $cobranca;
        });
    }

    public function criarCobrancaCartao(Venda $venda, string $tokenCartao, int $parcelas, string $metodoPagamento): Cobranca
    {
        return DB::transaction(function () use ($venda, $tokenCartao, $parcelas, $metodoPagamento) {
            $config = ConfigPagamento::where('empresa_id', $venda->empresa_id)->first();
            $gateway = $this->factory->paraEmpresa($config);
            $gatewayNome = ($config && $config->ativo) ? $config->gateway : 'simulado';

            $resultado = $gateway->criarCobrancaCartao($venda, $config ?? new ConfigPagamento(), $tokenCartao, $parcelas, $metodoPagamento);

            $cobranca = Cobranca::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'gateway' => $gatewayNome,
                'metodo' => $metodoPagamento,
                'referencia_externa' => $resultado->referenciaExterna,
                'status' => $resultado->status,
                'valor' => $venda->valor_total,
                'payload_retorno' => $resultado->payloadBruto,
            ]);

            if ($resultado->status === 'aprovado') {
                $venda->update(['status_pagamento' => 'pago']);
            }

            return $cobranca;
        });
    }

    /**
     * Consulta o status atual no gateway e sincroniza cobrança + venda -
     * usado tanto pelo webhook do gateway quanto por um botão manual de
     * "verificar pagamento" no painel, para quando o webhook falhar.
     */
    public function consultarEAtualizarStatus(Cobranca $cobranca): Cobranca
    {
        return DB::transaction(function () use ($cobranca) {
            $config = ConfigPagamento::where('empresa_id', $cobranca->empresa_id)->first();
            $gateway = $this->factory->paraEmpresa($config);

            $status = $gateway->consultarStatus($config ?? new ConfigPagamento(), $cobranca->referencia_externa);

            $cobranca->update(['status' => $status]);

            if ($status === 'aprovado') {
                $cobranca->venda->update(['status_pagamento' => 'pago']);
            }

            return $cobranca->fresh();
        });
    }

    /**
     * Aplica diretamente um status recebido via webhook (o gateway já
     * manda o status pronto, não precisamos consultar de novo).
     */
    public function aplicarStatusDoWebhook(Cobranca $cobranca, string $status): Cobranca
    {
        return DB::transaction(function () use ($cobranca, $status) {
            $cobranca->update(['status' => $status]);

            if ($status === 'aprovado') {
                $cobranca->venda->update(['status_pagamento' => 'pago']);
            }

            return $cobranca->fresh();
        });
    }
}
