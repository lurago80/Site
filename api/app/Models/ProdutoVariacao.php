<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'produto_id', 'produto_vinculado_id', 'tamanho', 'estoque_atual', 'ativo'])]
class ProdutoVariacao extends Model
{
    protected $table = 'produto_variacoes';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function produtoVinculado(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_vinculado_id');
    }

    /** Estoque exibido quando o produto vinculado não controla estoque (ilimitado). */
    public const ESTOQUE_ILIMITADO_EXIBIDO = 9999;

    /**
     * Estoque disponível para venda: o da própria variação ou, se vinculada, o do
     * produto vinculado. null = ilimitado (produto vinculado sem controle de estoque).
     */
    public function estoqueDisponivel(): ?int
    {
        if ($this->produto_vinculado_id === null) {
            return (int) $this->estoque_atual;
        }

        $estoque = $this->produtoVinculado?->estoque_atual;

        return $estoque === null ? null : (int) $estoque;
    }

    /** Número para a loja: ilimitado vira um teto alto, para o seletor de quantidades funcionar. */
    public function estoqueParaExibir(): int
    {
        return $this->estoqueDisponivel() ?? self::ESTOQUE_ILIMITADO_EXIBIDO;
    }

    /** Ativa e, se vinculada, com o produto vinculado também ativo. */
    public function disponivelParaVenda(): bool
    {
        if (! $this->ativo) {
            return false;
        }

        return $this->produto_vinculado_id === null || (bool) $this->produtoVinculado?->ativo;
    }

    /**
     * Baixa o estoque (da variação ou do produto vinculado). Aborta com 409 se não
     * houver saldo, a menos que $permitirNegativo.
     */
    public function baixar(int $quantidade, string $rotulo, bool $permitirNegativo = false): void
    {
        if ($this->produto_vinculado_id !== null) {
            $produto = Produto::lockForUpdate()->findOrFail($this->produto_vinculado_id);

            if ($produto->estoque_atual !== null) {
                abort_if(! $permitirNegativo && $produto->estoque_atual < $quantidade, 409, "Estoque insuficiente para {$produto->nome}.");
                $produto->decrement('estoque_atual', $quantidade);
            }

            return;
        }

        abort_if(! $permitirNegativo && $this->estoque_atual < $quantidade, 409, "Estoque insuficiente para {$rotulo}.");
        $this->decrement('estoque_atual', $quantidade);
    }

    /** O produto que sai no pedido/nota: o vinculado, quando existe; senão o pai. */
    public function produtoParaFaturar(Produto $pai): Produto
    {
        return $this->produto_vinculado_id !== null ? ($this->produtoVinculado ?? $pai) : $pai;
    }
}
