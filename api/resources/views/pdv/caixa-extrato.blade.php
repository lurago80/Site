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
        /* Modo bobina (impressora térmica 80 mm / ~48 colunas) */
        body.bobina { font-family: Arial, Helvetica, sans-serif; font-weight: 700; color: #000; font-size: 13px; line-height: 1.3; width: 76mm; max-width: 76mm; margin: 0 auto; padding: 0 2mm; -webkit-font-smoothing: none; }
        body.bobina h1 { font-size: 15px; text-align: center; }
        body.bobina h2 { font-size: 13px; background: none; border-top: 2px dashed #000; border-bottom: 2px dashed #000; text-align: center; padding: 3px 0; margin: 10px 0 4px; }
        body.bobina .sub { font-size: 12px; color: #000; }
        body.bobina table, body.bobina tbody { display: block; width: 100%; }
        body.bobina thead, body.bobina .b-oculta { display: none; }
        body.bobina tr { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px; border: 0; }
        body.bobina th, body.bobina td { border: 0; padding: 2px 0; color: #000; font-size: 13px; }
        body.bobina small { font-size: 11px; }
        body.bobina td.b-obs { flex-basis: 100%; font-size: 12px; }
        body.bobina tr.total td, body.bobina tr.resultado td { border-top: 2px dashed #000; }
        body.bobina tr.resultado td { font-size: 15px; border-bottom: 2px dashed #000; }
        body.bobina .assinatura { display: block; margin-top: 30px; }
        body.bobina .assinatura div { margin-bottom: 26px; border-top: 2px solid #000; color: #000; font-size: 12px; }
        body.bobina .neg { color: #000; }
        @media print { .acoes, .filtro-dia { display: none; } body { margin: 10mm; max-width: none; } }
    </style>
</head>
<body>
@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
    <h1>Extrato do caixa — {{ $empresaNome }}</h1>
    <p class="sub">Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>

    <form class="acoes filtro-dia" method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
        <label for="data-extrato"><strong>Dia:</strong></label>
        <input type="date" id="data-extrato" name="data" value="{{ $data }}" max="{{ $geradoEm->format('Y-m-d') }}" style="padding:6px;">
        <button type="submit">Ver este dia</button>
        @if ($data)
            <a href="?" style="font-size:12px;">Voltar ao último turno</a>
        @endif
    </form>
    @if ($data)
        <p class="sub" style="margin:0 0 8px;">Extrato do dia {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}.
            @if ($turnosDoDia->count() > 1)
                Houve {{ $turnosDoDia->count() }} turnos de caixa neste dia:
                @foreach ($turnosDoDia as $t)
                    <a href="?data={{ $data }}&abertura={{ $t->id }}" @if ($t->id === $aberturaSelecionada) style="font-weight:700;" @endif>turno das {{ $t->data_hora->copy()->timezone('America/Sao_Paulo')->format('H:i') }}</a>@if (! $loop->last) · @endif
                @endforeach
            @endif
        </p>
    @endif

    <div class="acoes">
        <button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
        <button type="button" onclick="imprimirBobina()">Imprimir 80 colunas (bobina)</button>
    </div>

    @if ($resumo === null)
        <p class="vazio">{{ $data ? 'Nenhum caixa foi aberto nesta data.' : 'Nenhum caixa foi aberto ainda.' }}</p>
    @else
        @php
            $a = $resumo['abertura'];
            $f = $resumo['fechamento'];
        @endphp
        <p class="sub">
            Turno: aberto em <strong>{{ $a->data_hora->copy()->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</strong>@if($a->usuario) por {{ $a->usuario->name }}@endif —
            @if ($f) fechado em <strong>{{ $f->data_hora->copy()->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</strong>@if($f->usuario) por {{ $f->usuario->name }}@endif
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
                        <td class="b-oculta">{{ $m->data_hora->copy()->timezone('America/Sao_Paulo')->format('d/m H:i') }}</td>
                        <td>{{ $m->tipo === 'sangria' ? 'Sangria (saída)' : 'Suprimento (entrada)' }}</td>
                        <td class="b-oculta">{{ $m->usuario?->name ?? '—' }}</td>
                        <td class="b-obs">{{ $m->observacao ?? '—' }}</td>
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
    <script>
        function imprimirBobina() {
            const estilo = document.createElement('style');
            estilo.textContent = '@page { size: 80mm auto; margin: 0; }';
            document.head.appendChild(estilo);
            document.body.classList.add('bobina');
            window.addEventListener('afterprint', () => {
                document.body.classList.remove('bobina');
                estilo.remove();
            }, { once: true });
            window.print();
        }
    </script>
</body>
</html>
