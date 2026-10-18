<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Balancete — {{ $geradoEm->format('d-m-Y H-i') }}</title>
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
        @media print { .acoes, .filtro { display: none; } body { margin: 10mm; max-width: none; } }
    </style>
</head>
<body>
@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
    <h1>Balancete — {{ $empresaNome }}</h1>
    <p class="sub">Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>

    <form class="acoes filtro" method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
        <label for="data-inicio"><strong>De:</strong></label>
        <input type="date" id="data-inicio" name="data_inicio" value="{{ $dataInicio }}" style="padding:6px;">
        <label for="data-fim"><strong>Até:</strong></label>
        <input type="date" id="data-fim" name="data_fim" value="{{ $dataFim }}" max="{{ $geradoEm->format('Y-m-d') }}" style="padding:6px;">
        <button type="submit">Filtrar período</button>
        @if ($dataInicio || $dataFim)
            <a href="?" style="font-size:12px;">Ver tudo</a>
        @endif
    </form>

    <div class="acoes">
        <button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
    </div>

    @if ($dataInicio || $dataFim)
        <p class="sub" style="margin:0 0 8px;">
            Período: {{ $dataInicio ? \Illuminate\Support\Carbon::parse($dataInicio)->format('d/m/Y') : 'início' }}
            até {{ $dataFim ? \Illuminate\Support\Carbon::parse($dataFim)->format('d/m/Y') : 'hoje' }}.
        </p>
    @endif

    <h2>Receitas</h2>
    <table>
        <thead><tr><th>Código</th><th>Conta</th><th class="num">Total</th></tr></thead>
        <tbody>
            @forelse ($receitas as $r)
                <tr>
                    <td>{{ $r['codigo'] }}</td>
                    <td>{{ $r['nome'] }}</td>
                    <td class="num">{{ $brl($r['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vazio">Nenhuma receita no período.</td></tr>
            @endforelse
            <tr class="total"><td colspan="2">Total de receitas</td><td class="num">{{ $brl($totalReceitas) }}</td></tr>
        </tbody>
    </table>

    <h2>Despesas</h2>
    <table>
        <thead><tr><th>Código</th><th>Conta</th><th class="num">Total</th></tr></thead>
        <tbody>
            @forelse ($despesas as $d)
                <tr>
                    <td>{{ $d['codigo'] }}</td>
                    <td>{{ $d['nome'] }}</td>
                    <td class="num">{{ $brl($d['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vazio">Nenhuma despesa no período.</td></tr>
            @endforelse
            <tr class="total"><td colspan="2">Total de despesas</td><td class="num">{{ $brl($totalDespesas) }}</td></tr>
        </tbody>
    </table>

    <h2>Resultado</h2>
    <table>
        <tbody>
            <tr class="resultado">
                <td>Receitas − despesas</td>
                <td class="num {{ $resultado < 0 ? 'neg' : '' }}">{{ $brl($resultado) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="assinatura"><div>Responsável</div><div>Contabilidade</div></div>
</body>
</html>
