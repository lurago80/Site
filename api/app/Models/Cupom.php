<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'codigo', 'tipo', 'valor', 'valido_ate', 'limite_uso', 'usos_realizados', 'ativo'])]
class Cupom extends Model
{
    // Eloquent pluraliza "Cupom" em inglês (cupoms) por padrão - tabela é cupons.
    protected $table = 'cupons';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valido_ate' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
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
}
