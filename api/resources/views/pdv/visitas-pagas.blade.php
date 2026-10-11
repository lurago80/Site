<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visitas pagas — {{ $geradoEm->format('d-m-Y H-i') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #111; margin: 24px; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; margin: 0 0 14px; }
        .acoes { margin-bottom: 16px; display: flex; gap: 8px; align-items: center; }
        .acoes button { font-size: 13px; padding: 8px 14px; cursor: pointer; }
        .acoes span { color: #555; }
        .horario { margin-top: 18px; break-inside: avoid-page; }
        .horario h2 { font-size: 14px; margin: 0 0 4px; padding: 6px 8px; background: #eee; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ccc; padding: 5px 6px; text-align: left; vertical-align: middle; }
        th { font-size: 11px; text-transform: uppercase; color: #555; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .pedido { font-weight: 700; font-size: 14px; }
        .caixa { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #111; }
        tr { break-inside: avoid; }
        .vazio { color: #555; padding: 20px 0; }
        .total { margin-top: 20px; font-weight: 700; font-size: 13px; text-align: right; }
        @media print { .acoes { display: none; } body { margin: 10mm; } }

        /* Modo impressora térmica 80 colunas (80 mm) */
        .t80 { display: none; }
        body.modo80 { font-family: Arial, Helvetica, sans-serif; font-weight: 700; font-size: 12px; width: 76mm; margin: 0 auto; padding: 2mm; }
        body.modo80 .normal, body.modo80 .sub { display: none; }
        body.modo80 .t80 { display: block; }
        body.modo80 h1 { font-size: 14px; text-align: center; }
        .t80 .hor { border-top: 2px dashed #000; border-bottom: 2px dashed #000; margin: 8px 0 4px; padding: 3px 0; text-align: center; font-size: 13px; }
        .t80 .ped { padding: 4px 0; border-bottom: 1px dashed #000; }
        .t80 .ped .l1 { display: flex; justify-content: space-between; font-size: 14px; }
        .t80 .ped small { font-size: 11px; font-weight: 700; }
        .t80 .tot { margin-top: 8px; text-align: right; font-size: 13px; border-top: 2px solid #000; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>Visitas pagas — {{ $empresaNome }}</h1>
    <p class="sub">Pedidos pagos (loja virtual e PDV) com visita agendada para hoje. Gerada em {{ $geradoEm->format('d/m/Y H:i') }}. O nº do pedido é o mesmo usado para validar o ticket.</p>

    <div class="acoes">
        <button type="button" onclick="imprimir(false)">Imprimir / Salvar como PDF</button>
        <button type="button" onclick="imprimir(true)">Imprimir 80 colunas (térmica)</button>
        <span>Para PDF, escolha o destino "Salvar como PDF". Para a térmica, escolha a impressora de cupom.</span>
    </div>

    <div class="normal">
    @forelse ($horarios as $h)
        <section class="horario">
            <h2>
                <span>{{ $h->data_hora->copy()->timezone('America/Sao_Paulo')->locale('pt_BR')->isoFormat('DD/MM/YYYY (dddd) HH:mm') }}</span>
                <span>{{ $h->tickets }} ticket(s) · R$ {{ number_format($h->valor, 2, ',', '.') }}</span>
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Pedido / recibo</th>
                        <th>Cliente</th>
                        <th class="num">Tickets</th>
                        <th class="num">Valor da visita</th>
                        <th>Pagamento</th>
                        <th>Pago em</th>
                        <th>Entrada</th>
                        <th>Conferido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($h->linhas as $l)
                        <tr>
                            <td class="pedido">{{ $l->venda->id }}<br><small style="font-weight:400;">{{ $l->venda->canal === 'pdv' ? 'PDV' : 'Site' }}</small></td>
                            <td>{{ $l->venda->cliente?->nome ?? '—' }}@if($l->venda->cliente?->telefone)<br><small>{{ $l->venda->cliente->telefone }}</small>@endif</td>
                            <td class="num">{{ $l->tickets }}</td>
                            <td class="num">R$ {{ number_format($l->valor, 2, ',', '.') }}</td>
                            <td>{{ $l->venda->formaPagamento?->descricao ?? '—' }}</td>
                            <td>{{ $l->venda->data_venda?->copy()->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                            <td>{{ $l->venda->check_in_em ? 'Já entrou '.$l->venda->check_in_em->copy()->timezone('America/Sao_Paulo')->format('d/m H:i') : '—' }}</td>
                            <td><span class="caixa"></span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="vazio">Nenhuma visita paga para hoje.</p>
    @endforelse

    @if ($horarios->isNotEmpty())
        <p class="total">Total: {{ $horarios->sum('tickets') }} ticket(s) · R$ {{ number_format($horarios->sum('valor'), 2, ',', '.') }}</p>
    @endif
    </div>

    {{-- Versão para impressora térmica 80 colunas --}}
    <div class="t80">
        <div style="text-align:center;">Visitas de hoje — {{ $geradoEm->format('d/m/Y H:i') }}</div>
        @forelse ($horarios as $h)
            <div class="hor">{{ $h->data_hora->copy()->timezone('America/Sao_Paulo')->format('H:i') }} — {{ $h->tickets }} ticket(s)</div>
            @foreach ($h->linhas as $l)
                <div class="ped">
                    <div class="l1"><span>Pedido {{ $l->venda->id }} ({{ $l->venda->canal === 'pdv' ? 'PDV' : 'SITE' }})</span><span>{{ $l->tickets }} tk</span></div>
                    <div>{{ $l->venda->cliente?->nome ?? '—' }}</div>
                    <small>
                        @if ($l->venda->cliente?->telefone){{ $l->venda->cliente->telefone }} · @endif
                        R$ {{ number_format($l->valor, 2, ',', '.') }} · {{ $l->venda->formaPagamento?->descricao ?? '—' }}
                        @if ($l->venda->check_in_em) · JÁ ENTROU @endif
                    </small>
                    <div>[&nbsp;&nbsp;] Conferido</div>
                </div>
            @endforeach
        @empty
            <p>Nenhuma visita paga para hoje.</p>
        @endforelse
        @if ($horarios->isNotEmpty())
            <div class="tot">TOTAL: {{ $horarios->sum('tickets') }} ticket(s)<br>R$ {{ number_format($horarios->sum('valor'), 2, ',', '.') }}</div>
        @endif
    </div>

    <script>
        function imprimir(termica) {
            document.body.classList.toggle('modo80', termica);
            var estilo = document.getElementById('estilo-pagina');
            if (estilo) estilo.remove();
            if (termica) {
                estilo = document.createElement('style');
                estilo.id = 'estilo-pagina';
                estilo.textContent = '@page { size: 80mm auto; margin: 0; } @media print { body.modo80 { margin: 0 auto; } }';
                document.head.appendChild(estilo);
            }
            window.print();
        }
        window.onafterprint = function () { document.body.classList.remove('modo80'); };
    </script>
</body>
</html>
