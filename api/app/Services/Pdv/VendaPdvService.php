<?php

namespace App\Services\Pdv;

use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Services\Agendamento\ReservaVagaService;
use App\Services\Fiscal\EmissaoFiscalService;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra a finalização de uma venda no PDV (frente de caixa):
 * produtos físicos (com baixa de estoque), visita agendada (via
 * ReservaVagaService - mesma trava anti-overbooking da loja pública) e
 * comissão por vendedor (Escopo v2, seção 2.2). Emite NFC-e na hora
 * quando a venda é fiscal, reaproveitando o EmissaoFiscalService.
 */
class VendaPdvService
{
    public function __construct(
        private readonly ReservaVagaService $reservaVagaService,
        private readonly EmissaoFiscalService $emissaoFiscalService,
    ) {}

    public function finalizar(Empresa $empresa, array $dados): Venda
    {
        return DB::transaction(function () use ($empresa, $dados) {
            $vendedor = ! empty($dados['vendedor_id'])
                ? Vendedor::findOrFail($dados['vendedor_id'])
                : null;

            $cliente = ! empty($dados['cliente']['nome'] ?? null)
                ? $this->localizarOuCriarCliente($empresa->id, $dados['cliente'])
                : null;

            $venda = Venda::create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente?->id,
                'vendedor_id' => $vendedor?->id,
                'canal' => 'pdv',
                'tipo_doc' => $dados['tipo_doc'],
                'status_pagamento' => 'pago',
                'valor_total' => 0,
                'comissao' => 0,
                'data_venda' => now(),
            ]);

            $valorTotal = 0;
            $comissaoTotal = 0;

            foreach ($dados['itens'] ?? [] as $item) {
                [$valorItem, $comissaoItem] = $this->criarItemProduto($venda, $item, $vendedor);
                $valorTotal += $valorItem;
                $comissaoTotal += $comissaoItem;
            }

            if (! empty($dados['agenda_visitacao_id'])) {
                [$valorItem, $comissaoItem] = $this->criarItemAgenda(
                    $venda,
                    (int) $dados['agenda_visitacao_id'],
                    (int) $dados['agenda_quantidade'],
                    $vendedor,
                );
                $valorTotal += $valorItem;
                $comissaoTotal += $comissaoItem;
            }

            $venda->update([
                'valor_total' => $valorTotal,
                'comissao' => $comissaoTotal > 0 ? $comissaoTotal : null,
            ]);

            if ($dados['tipo_doc'] === 'fiscal') {
                $this->emissaoFiscalService->emitir($venda->fresh('itens'), 65);
            }

            return $venda->fresh(['itens', 'cliente', 'vendedor']);
        });
    }

    /**
     * @return array{0: float, 1: float} [valorItem, comissaoItem]
     */
    private function criarItemProduto(Venda $venda, array $item, ?Vendedor $vendedor): array
    {
        $produto = Produto::findOrFail($item['produto_id']);
        $quantidade = (int) $item['quantidade'];

        if ($produto->estoque_atual !== null) {
            abort_if($produto->estoque_atual < $quantidade, 409, "Estoque insuficiente para {$produto->nome}.");
            $produto->decrement('estoque_atual', $quantidade);
        }

        $valorItem = (float) $produto->preco_venda * $quantidade;
        $comissaoItem = $vendedor ? round($valorItem * (float) $vendedor->percentual_comissao / 100, 2) : 0;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
            'valor_unitario' => $produto->preco_venda,
            'valor_total' => $valorItem,
            'comissao_percentual' => $vendedor?->percentual_comissao,
            'comissao_valor' => $comissaoItem ?: null,
        ]);

        return [$valorItem, $comissaoItem];
    }

    /**
     * @return array{0: float, 1: float} [valorItem, comissaoItem]
     */
    private function criarItemAgenda(Venda $venda, int $agendaVisitacaoId, int $quantidade, ?Vendedor $vendedor): array
    {
        $reserva = $this->reservaVagaService->reservar($agendaVisitacaoId, $quantidade);
        $this->reservaVagaService->confirmar($reserva->id);

        $agenda = AgendaVisitacao::findOrFail($agendaVisitacaoId);
        $valorItem = (float) $agenda->valor_visita * $quantidade;
        $comissaoItem = $vendedor ? round($valorItem * (float) $vendedor->percentual_comissao / 100, 2) : 0;

        $venda->itens()->create([
            'empresa_id' => $venda->empresa_id,
            'agenda_visitacao_id' => $agenda->id,
            'quantidade' => $quantidade,
            'valor_unitario' => $agenda->valor_visita,
            'valor_total' => $valorItem,
            'comissao_percentual' => $vendedor?->percentual_comissao,
            'comissao_valor' => $comissaoItem ?: null,
        ]);

        return [$valorItem, $comissaoItem];
    }

    private function localizarOuCriarCliente(int $empresaId, array $dadosCliente): Cliente
    {
        $cliente = null;

        if (! empty($dadosCliente['cpf_cnpj'])) {
            $cliente = Cliente::where('cpf_cnpj', $dadosCliente['cpf_cnpj'])->first();
        }

        $atributos = [
            'empresa_id' => $empresaId,
            'nome' => $dadosCliente['nome'],
            'cpf_cnpj' => $dadosCliente['cpf_cnpj'] ?? null,
            'telefone' => $dadosCliente['telefone'] ?? null,
        ];

        if ($cliente) {
            $cliente->update($atributos);

            return $cliente;
        }

        return Cliente::create($atributos + ['consentimento_lgpd' => false]);
    }
}
