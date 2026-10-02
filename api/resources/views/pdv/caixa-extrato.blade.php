<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extrato do caixa — {{ $geradoEm->format('d-m-Y H-i') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #111; margin: 24px auto; max-width: 780px; font-size: 13px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .4px; margin: 20px 0 4px; padding: 5px 8px; background: #eee; }
        .sub { color: #555; margin: 0 0 12px; }
        .acoes { margin-bottom: 14px; }
        .acoes button { font-size: 13px; padding: 8px 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; color: #555; }
        .num { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: 700; border-top: 2px solid #111; border-bottom: none; }
        tr.resultado td { font-weight: 700; font-size: 16px; border-top: 2px solid #111; border-bottom: 3px double #111; }
        .neg { color: #b00020; }
        .assinatura { margin-top: 50px; display: flex; gap: 40px; }
        .assinatura div { flex: 1; border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 11px; color: #555; }
        .vazio { color: #555; padding: 10px 0; }
        @media print { .acoes { display: none; } body { margin: 10mm; max-width: none; } }
    </style>
</head>
<body>
@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
    <h1>Extrato do caixa — {{ $empresaNome }}</h1>
    <p class="sub">Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>

    <div class="acoes">
        <button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
    </div>

    @if ($resumo === null)
        <p class="vazio">Nenhum caixa foi aberto ainda.</p>
    @else
        @php
            $a = $resumo['abertura'];
            $f = $resumo['fechamento'];
        @endphp
        <p class="sub">
            Turno: aberto em <strong>{{ $a->data_hora->format('d/m/Y H:i') }}</strong>@if($a->usuario) por {{ $a->usuario->name }}@endif —
            @if ($f) fechado em <strong>{{ $f->data_hora->format('d/m/Y H:i') }}</strong>@if($f->usuario) por {{ $f->usuario->name }}@endif
            @else <strong>caixa ainda aberto</strong> (posição até agora)
            @endif
        </p>

        <h2>Vendas por espécie (forma de pagamento)</h2>
        <table>
            <thead><tr><th>Espécie</th><th class="num">Vendas</th><th class="num">Valor</th></tr></thead>
            <tbody>
                @forelse ($resumo['por_especie'] as $e)
                    <tr>
                        <td>{{ $e['descricao'] }}@if($e['dinheiro']) <small>(entra na gaveta)</small>@endif</td>
                        <td class="num">{{ $e['quantidade'] }}</td>
                        <td class="num">{{ $brl($e['valor']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="vazio">Nenhuma venda no período.</td></tr>
                @endforelse
                <tr class="total"><td>Total de vendas</td><td class="num">{{ $resumo['qtd_vendas'] }}</td><td class="num">{{ $brl($resumo['total_vendas']) }}</td></tr>
            </tbody>
        </table>

        <h2>Suprimentos e sangrias</h2>
        <table>
            <thead><tr><th>Hora</th><th>Tipo</th><th>Operador</th><th>Observação</th><th class="num">Valor</th></tr></thead>
            <tbody>
                @forelse ($resumo['movimentos'] as $m)
                    <tr>
                        <td>{{ $m->data_hora->format('d/m H:i') }}</td>
                        <td>{{ $m->tipo === 'sangria' ? 'Sangria (saída)' : 'Suprimento (entrada)' }}</td>
                        <td>{{ $m->usuario?->name ?? '—' }}</td>
                        <td>{{ $m->observacao ?? '—' }}</td>
                        <td class="num {{ $m->tipo === 'sangria' ? 'neg' : '' }}">{{ $m->tipo === 'sangria' ? '− ' : '' }}{{ $brl($m->valor) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vazio">Nenhum movimento.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Resultado do caixa em dinheiro</h2>
        <table>
            <tbody>
                <tr><td>Abertura (troco inicial)</td><td class="num">{{ $brl($a->valor) }}</td></tr>
                <tr><td>+ Vendas em dinheiro</td><td class="num">{{ $brl($resumo['vendas_dinheiro']) }}</td></tr>
                <tr><td>+ Suprimentos</td><td class="num">{{ $brl($resumo['suprimentos']) }}</td></tr>
                <tr><td>− Sangrias (saídas)</td><td class="num neg">− {{ $brl($resumo['sangrias']) }}</td></tr>
                <tr class="resultado"><td>= Saldo esperado em dinheiro</td><td class="num">{{ $brl($resumo['saldo_esperado']) }}</td></tr>
                @if ($f)
                    <tr><td>Valor contado no fechamento</td><td class="num">{{ $brl($f->valor) }}</td></tr>
                    <tr class="total"><td>Diferença (contado − esperado)</td><td class="num {{ $resumo['diferenca'] != 0 ? 'neg' : '' }}">{{ $brl($resumo['diferenca']) }}</td></tr>
                @endif
            </tbody>
        </table>
        <p class="sub" style="margin-top:8px;">Outras espécies (cartão, PIX etc.) não passam pela gaveta e ficam só no total acima.</p>

        <div class="assinatura"><div>Operador</div><div>Conferência / responsável</div></div>
    @endif
</body>
</html>
