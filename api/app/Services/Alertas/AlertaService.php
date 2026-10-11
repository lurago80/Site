<?php

namespace App\Services\Alertas;

use App\Models\Alerta;
use App\Models\Empresa;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

/**
 * Avisos ao administrador/gerente sobre operações fora do normal. Cortesia e cancelamento
 * alertam sempre; desconto, diferença de caixa e sangria só acima dos limites da empresa
 * (parâmetros alerta_*). Alerta nunca pode derrubar a operação que o gerou.
 */
class AlertaService
{
    /** Cortesia (sempre) ou desconto acima do percentual configurado. */
    public function vendaComDesconto(Venda $venda, Empresa $empresa, ?string $operador): void
    {
        $desconto = (float) $venda->valor_desconto;

        if ($desconto <= 0) {
            return;
        }

        $bruto = (float) $venda->valor_total + $desconto;
        $motivo = trim((string) $venda->motivo_desconto);
        $quem = $operador ? " por {$operador}" : '';

        if ($venda->cortesia) {
            $this->criar($venda->empresa_id, 'cortesia', "Cortesia na venda #{$venda->id}{$quem} (R$ ".$this->brl($bruto).')'.($motivo !== '' ? " - {$motivo}" : ''), $bruto, $venda->id);

            return;
        }

        $percentual = $bruto > 0 ? $desconto / $bruto * 100 : 0;

        if ($percentual >= (float) $empresa->alerta_desconto_percentual) {
            $this->criar($venda->empresa_id, 'desconto', "Desconto de ".number_format($percentual, 0)."% (R$ ".$this->brl($desconto).") na venda #{$venda->id}{$quem}".($motivo !== '' ? " - {$motivo}" : ''), $desconto, $venda->id);
        }
    }

    public function vendaCancelada(Venda $venda, ?string $quem, string $motivo): void
    {
        $this->criar(
            $venda->empresa_id, 'cancelamento',
            "Venda #{$venda->id} cancelada".($quem ? " por {$quem}" : '').' (R$ '.$this->brl((float) $venda->valor_total + (float) $venda->valor_desconto).") - {$motivo}",
            (float) $venda->valor_total, $venda->id,
        );
    }

    public function diferencaDeCaixa(Empresa $empresa, float $diferenca, ?string $quem): void
    {
        if (abs($diferenca) < (float) $empresa->alerta_diferenca_caixa) {
            return;
        }

        $this->criar(
            $empresa->id, 'diferenca_caixa',
            'Fechamento de caixa com '.($diferenca > 0 ? 'sobra' : 'falta').' de R$ '.$this->brl(abs($diferenca)).($quem ? " ({$quem})" : ''),
            abs($diferenca), null,
        );
    }

    public function sangria(Empresa $empresa, float $valor, ?string $quem, ?string $observacao): void
    {
        if ($valor < (float) $empresa->alerta_sangria) {
            return;
        }

        $this->criar(
            $empresa->id, 'sangria',
            'Sangria de R$ '.$this->brl($valor).($quem ? " por {$quem}" : '').($observacao ? " - {$observacao}" : ''),
            $valor, null,
        );
    }

    private function criar(int $empresaId, string $tipo, string $mensagem, ?float $valor, ?int $vendaId): void
    {
        try {
            // Savepoint: se o insert falhar, a transação da venda/caixa que chamou continua válida.
            DB::transaction(fn () => Alerta::create([
                'empresa_id' => $empresaId,
                'tipo' => $tipo,
                'mensagem' => mb_substr($mensagem, 0, 255),
                'valor' => $valor,
                'venda_id' => $vendaId,
                'usuario_id' => auth()->id(),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function brl(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
