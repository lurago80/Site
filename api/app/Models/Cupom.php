<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'codigo', 'tipo', 'valor', 'valor_maximo_desconto', 'valido_ate', 'limite_uso', 'usos_realizados', 'ativo', 'importado'])]
class Cupom extends Model
{
    // Eloquent pluraliza "Cupom" em inglês (cupoms) por padrão - tabela é cupons.
    protected $table = 'cupons';

    /** Máximo de tickets de visitação que recebem desconto numa mesma compra. */
    public const MAX_TICKETS_COM_DESCONTO = 2;

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_maximo_desconto' => 'decimal:2',
            'valido_ate' => 'date',
            'ativo' => 'boolean',
            'importado' => 'boolean',
            'usado_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usadoPor(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'usado_por_cliente_id');
    }

    /**
     * Motivo de recusa em português (pronto pra mostrar ao cliente), ou
     * null se o cupom pode ser usado agora. Centraliza aqui porque tanto
     * a validação prévia (loja) quanto o checkout de fato precisam da
     * mesma regra, e não podem divergir.
     */
    public function motivoInvalido(): ?string
    {
        if (! $this->ativo) {
            return 'Cupom inválido ou desativado.';
        }

        if ($this->valido_ate && $this->valido_ate->isPast()) {
            return 'Este cupom expirou.';
        }

        if ($this->limite_uso !== null && $this->usos_realizados >= $this->limite_uso) {
            return 'Este cupom já atingiu o limite de usos.';
        }

        return null;
    }

    public function calcularDesconto(float $subtotal): float
    {
        $desconto = $this->tipo === 'percentual'
            ? $subtotal * ((float) $this->valor / 100)
            : (float) $this->valor;

        // Desconto nunca deixa o total negativo.
        return min($desconto, $subtotal);
    }

    /**
     * Cupom só desconta o valor das visitas agendadas, nunca produtos
     * (regra de negócio explícita do cliente). Além disso, tem um teto em
     * reais: com 2 ou mais tickets de visitação no carrinho, o desconto
     * não passa de `valor_maximo_desconto`; com exatamente 1 ticket, o
     * teto cai pela metade - ex.: cupom de 30% com teto de R$48 desconta
     * no máximo R$48 (2+ tickets) ou R$24 (1 ticket único), mesmo que o
     * percentual calculado sobre o subtotal desse mais que isso.
     */
    public function calcularDescontoVisita(float $subtotalVisitas, int $quantidadeTickets): float
    {
        // O desconto cobre no máximo 2 tickets por vez: num carrinho com 3+
        // tickets, o percentual incide só sobre o valor de 2 deles
        // (ex.: 15% de R$ 80 -> R$ 12 com 1 ticket, R$ 24 com 2, R$ 24 com 3).
        $ticketsComDesconto = min($quantidadeTickets, self::MAX_TICKETS_COM_DESCONTO);
        $baseDesconto = $quantidadeTickets > 0
            ? $subtotalVisitas / $quantidadeTickets * $ticketsComDesconto
            : $subtotalVisitas;

        $desconto = $this->calcularDesconto($baseDesconto);

        if ($this->valor_maximo_desconto !== null) {
            $teto = $quantidadeTickets >= 2
                ? (float) $this->valor_maximo_desconto
                : (float) $this->valor_maximo_desconto / 2;

            $desconto = min($desconto, $teto);
        }

        return round($desconto, 2);
    }
}
