<?php

namespace App\Services\Pdv;

use App\Models\Caixa;
use App\Models\Empresa;
use App\Models\Venda;

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
            ->whereIn('tipo', ['abertura', 'sangria', 'suprimento', 'venda'])
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

    /**
     * Lança a entrada de uma venda paga em dinheiro no caixa físico
     * aberto no momento. Se não houver caixa aberto (empresa vendendo
     * fora do fluxo normal do PDV), não lança nada - não há gaveta para
     * receber o valor, e travar a venda por isso seria pior que só não
     * refletir no caixa.
     */
    public function registrarVenda(Empresa $empresa, int $usuarioId, float $valor, ?string $observacao): ?Caixa
    {
        if ($this->aberturaEmAberto($empresa->id) === null) {
            return null;
        }

        return Caixa::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuarioId,
            'tipo' => 'venda',
            'valor' => $valor,
            'data_hora' => now(),
            'observacao' => $observacao,
        ]);
    }

    /**
     * Desfaz no caixa a entrada em dinheiro de uma venda cancelada: lança uma
     * linha tipo=venda com valor negativo (o saldo soma as linhas de venda).
     * Devolve null quando a venda nunca entrou no caixa (não havia caixa
     * aberto na hora). O dinheiro só pode sair da gaveta do turno em que
     * entrou - vender num turno e cancelar noutro quebraria o fechamento
     * daquele turno, então isso é recusado.
     */
    public function estornarVenda(Empresa $empresa, int $usuarioId, Venda $venda): ?Caixa
    {
        $entrada = Caixa::where('empresa_id', $empresa->id)
            ->where('tipo', 'venda')
            ->where('observacao', "Venda #{$venda->id}")
            ->first();

        if ($entrada === null) {
            return null;
        }

        $abertura = $this->aberturaEmAberto($empresa->id);

        if ($abertura === null) {
            throw new \RuntimeException('Esta venda foi paga em dinheiro: abra o caixa para estornar o valor antes de cancelar.');
        }

        if ($entrada->id < $abertura->id) {
            throw new \RuntimeException('Esta venda em dinheiro é de um turno de caixa já fechado e não pode ser cancelada aqui.');
        }

        return Caixa::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuarioId,
            'tipo' => 'venda',
            'valor' => -abs((float) $entrada->valor),
            'data_hora' => now(),
            'observacao' => "Estorno da venda #{$venda->id} (cancelada)",
        ]);
    }

    /**
     * Resumo de um turno de caixa (da abertura até o fechamento, ou até
     * agora se ainda aberto): vendas do PDV por forma de pagamento (espécie),
     * suprimentos, sangrias e o saldo esperado em dinheiro na gaveta.
     * Sem $aberturaId, usa o turno mais recente.
     *
     * @return array<string, mixed>|null null quando a empresa nunca abriu o caixa
     */
    public function resumoTurno(int $empresaId, ?int $aberturaId = null): ?array
    {
        $abertura = Caixa::with('usuario:id,name')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'abertura')
            ->when($aberturaId, fn ($q) => $q->where('id', $aberturaId))
            ->latest('id')
            ->first();

        if ($abertura === null) {
            return null;
        }

        $fechamento = Caixa::with('usuario:id,name')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'fechamento')
            ->where('id', '>', $abertura->id)
            ->orderBy('id')
            ->first();

        $fim = $fechamento?->data_hora ?? now();

        $movimentos = Caixa::with('usuario:id,name')
            ->where('empresa_id', $empresaId)
            ->where('id', '>', $abertura->id)
            ->when($fechamento, fn ($q) => $q->where('id', '<', $fechamento->id))
            ->whereIn('tipo', ['suprimento', 'sangria'])
            ->orderBy('id')
            ->get();

        $vendas = Venda::with('formaPagamento')
            ->where('empresa_id', $empresaId)
            ->where('canal', 'pdv')
            ->where('status_pagamento', 'pago')
            ->whereBetween('data_venda', [$abertura->data_hora, $fim])
            ->get();

        $porEspecie = $vendas
            ->groupBy(fn (Venda $v) => $v->formaPagamento?->descricao ?? 'Não informada')
            ->map(fn ($grupo, $descricao) => [
                'descricao' => $descricao,
                'dinheiro' => $grupo->first()->formaPagamento?->tipo === 'dinheiro',
                'quantidade' => $grupo->count(),
                'valor' => round($grupo->sum(fn (Venda $v) => (float) $v->valor_total), 2),
            ])
            ->sortByDesc('valor')
            ->values();

        $vendasDinheiro = (float) $porEspecie->where('dinheiro', true)->sum('valor');
        $suprimentos = (float) $movimentos->where('tipo', 'suprimento')->sum('valor');
        $sangrias = (float) $movimentos->where('tipo', 'sangria')->sum('valor');
        $esperado = (float) $abertura->valor + $vendasDinheiro + $suprimentos - $sangrias;

        return [
            'abertura' => $abertura,
            'fechamento' => $fechamento,
            'fim' => $fim,
            'movimentos' => $movimentos,
            'por_especie' => $porEspecie,
            'total_vendas' => round((float) $porEspecie->sum('valor'), 2),
            'qtd_vendas' => $vendas->count(),
            'vendas_dinheiro' => $vendasDinheiro,
            'suprimentos' => $suprimentos,
            'sangrias' => $sangrias,
            'saldo_esperado' => round($esperado, 2),
            'diferenca' => $fechamento ? round((float) $fechamento->valor - $esperado, 2) : null,
        ];
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
