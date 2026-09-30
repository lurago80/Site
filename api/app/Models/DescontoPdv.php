<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Desconto aplicado manualmente pelo operador no PDV (nunca na loja virtual).
 * Cada desconto incide só em produtos ou só em visitas - regra de negócio
 * explícita do cliente (ex.: "10% Produtos", "50% Visitas").
 */
#[Fillable(['empresa_id', 'descricao', 'percentual', 'aplica_em', 'ativo'])]
class DescontoPdv extends Model
{
    protected $table = 'descontos_pdv';

    public const APLICA_PRODUTOS = 'produtos';

    public const APLICA_VISITAS = 'visitas';

    protected function casts(): array
    {
        return [
            'percentual' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function aplicaEmVisitas(): bool
    {
        return $this->aplica_em === self::APLICA_VISITAS;
    }

    /**
     * Desconto em R$ sobre o que o tipo do desconto cobre. Em visitas, vale
     * no máximo para 2 tickets por vez (mesma regra do cupom), usando o
     * valor médio do ticket.
     */
    public function calcular(float $valorProdutos, float $valorVisitas, int $quantidadeTickets): float
    {
        if ($this->aplicaEmVisitas()) {
            $base = $quantidadeTickets > 0
                ? $valorVisitas / $quantidadeTickets * min($quantidadeTickets, Cupom::MAX_TICKETS_COM_DESCONTO)
                : 0.0;
        } else {
            $base = $valorProdutos;
        }

        return round($base * ((float) $this->percentual / 100), 2);
    }
}
