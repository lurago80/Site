<?php

namespace App\Services\Pdv;

use App\Models\Caixa;
use App\Models\Empresa;

/**
 * Controle de caixa físico do PDV (Escopo v2, decisão de 2026-07-21):
 * abertura, fechamento, sangria e suprimento são todos lançados como
 * linhas em `caixas`. O caixa está "aberto" quando existe uma linha
 * tipo=abertura sem uma linha tipo=fechamento posterior - um único
 * caixa por empresa por vez (não por operador/terminal, mesmo escopo
 * simples já usado pelo resto do PDV).
 */
class CaixaService
{
    public function statusAtual(int $empresaId): array
    {
        $aberturaAtual = $this->aberturaEmAberto($empresaId);

        if ($aberturaAtual === null) {
            return ['status' => 'fechado', 'saldo' => null, 'abertura' => null];
        }

        $movimentosDesdeAbertura = Caixa::where('empresa_id', $empresaId)
            ->where('id', '>=', $aberturaAtual->id)
            ->whereIn('tipo', ['abertura', 'sangria', 'suprimento'])
            ->get();

        $saldo = $movimentosDesdeAbertura->sum(
            fn (Caixa $m) => $m->tipo === 'sangria' ? -$m->valor : $m->valor
        );

        return ['status' => 'aberto', 'saldo' => $saldo, 'abertura' => $aberturaAtual];
    }

    public function abrir(Empresa $empresa, int $usuarioId, float $valor, ?string $observacao): Caixa
    {
        if ($this->aberturaEmAberto($empresa->id) !== null) {
            throw new \RuntimeException('Já existe um caixa aberto para esta empresa.');
        }

        return Caixa::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuarioId,
            'tipo' => 'abertura',
            'valor' => $valor,
            'data_hora' => now(),
            'observacao' => $observacao,
        ]);
    }

    public function fechar(Empresa $empresa, int $usuarioId, float $valor, ?string $observacao): Caixa
    {
        if ($this->aberturaEmAberto($empresa->id) === null) {
            throw new \RuntimeException('Não há caixa aberto para fechar.');
        }

        return Caixa::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuarioId,
            'tipo' => 'fechamento',
            'valor' => $valor,
            'data_hora' => now(),
            'observacao' => $observacao,
        ]);
    }

    public function registrarMovimento(Empresa $empresa, int $usuarioId, string $tipo, float $valor, ?string $observacao): Caixa
    {
        if ($this->aberturaEmAberto($empresa->id) === null) {
            throw new \RuntimeException('Não há caixa aberto - abra o caixa antes de lançar sangria/suprimento.');
        }

        return Caixa::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'valor' => $valor,
            'data_hora' => now(),
            'observacao' => $observacao,
        ]);
    }

    private function aberturaEmAberto(int $empresaId): ?Caixa
    {
        $ultimaAbertura = Caixa::where('empresa_id', $empresaId)
            ->where('tipo', 'abertura')
            ->latest('id')
            ->first();

        if ($ultimaAbertura === null) {
            return null;
        }

        $temFechamentoPosterior = Caixa::where('empresa_id', $empresaId)
            ->where('tipo', 'fechamento')
            ->where('id', '>', $ultimaAbertura->id)
            ->exists();

        return $temFechamentoPosterior ? null : $ultimaAbertura;
    }
}
