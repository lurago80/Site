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
    </style>
</head>
<body>
    <h1>Visitas pagas — {{ $empresaNome }}</h1>
    <p class="sub">Pedidos da loja virtual, de hoje em diante. Gerada em {{ $geradoEm->format('d/m/Y H:i') }}. O nº do pedido é o mesmo usado para validar o ticket.</p>

    <div class="acoes">
        <button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
        <span>Na janela de impressão, escolha o destino "Salvar como PDF".</span>
    </div>

    @forelse ($horarios as $h)
        <section class="horario">
            <h2>
                <span>{{ $h->data_hora->locale('pt_BR')->isoFormat('DD/MM/YYYY (dddd) HH:mm') }}</span>
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
                            <td class="pedido">{{ $l->venda->id }}</td>
                            <td>{{ $l->venda->cliente?->nome ?? '—' }}@if($l->venda->cliente?->telefone)<br><small>{{ $l->venda->cliente->telefone }}</small>@endif</td>
                            <td class="num">{{ $l->tickets }}</td>
                            <td class="num">R$ {{ number_format($l->valor, 2, ',', '.') }}</td>
                            <td>{{ $l->venda->formaPagamento?->descricao ?? '—' }}</td>
                            <td>{{ $l->venda->data_venda?->format('d/m/Y H:i') }}</td>
                            <td>{{ $l->venda->check_in_em ? 'Já entrou '.$l->venda->check_in_em->format('d/m H:i') : '—' }}</td>
                            <td><span class="caixa"></span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="vazio">Nenhuma visita paga a partir de hoje.</p>
    @endforelse

    @if ($horarios->isNotEmpty())
        <p class="total">Total: {{ $horarios->sum('tickets') }} ticket(s) · R$ {{ number_format($horarios->sum('valor'), 2, ',', '.') }}</p>
    @endif
</body>
</html>
