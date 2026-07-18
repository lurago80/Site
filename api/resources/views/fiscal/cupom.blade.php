<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cupom NFC-e {{ $documento->numero }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 300px; margin: 0 auto; padding: 12px; }
        h1 { font-size: 14px; text-align: center; margin: 4px 0; }
        .linha { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        td { padding: 2px 0; }
        .totais td:last-child, .itens td:last-child { text-align: right; }
        .chave { word-break: break-all; font-size: 10px; text-align: center; margin-top: 8px; }
        .status { text-align: center; font-weight: bold; margin: 6px 0; }
        .status.cancelada { text-decoration: line-through; }
        @media print { body { width: auto; } }
    </style>
</head>
<body>
    <h1>{{ $documento->empresa->razao_social }}</h1>
    <p style="text-align:center">CNPJ: {{ $documento->empresa->cnpj }}</p>
    <p style="text-align:center">
        {{ $documento->empresa->logradouro }}, {{ $documento->empresa->numero }} -
        {{ $documento->empresa->bairro }} - {{ $documento->empresa->municipio }}/{{ $documento->empresa->uf }}
    </p>

    <div class="linha"></div>
    <p class="status {{ $documento->status === 'cancelada' ? 'cancelada' : '' }}">
        {{ $documento->modelo === 55 ? 'NFe' : 'NFC-e' }} nº {{ $documento->numero }} série {{ $documento->serie }}
        — {{ strtoupper($documento->status) }}
    </p>
    <div class="linha"></div>

    <table class="itens">
        @foreach ($documento->itens as $item)
            <tr>
                <td colspan="2">{{ $item->produto->nome ?? 'Item' }}</td>
            </tr>
            <tr>
                <td>{{ $item->quantidade }} x {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                <td>R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linha"></div>
    <table class="totais">
        <tr><td>Total</td><td>R$ {{ number_format($documento->total, 2, ',', '.') }}</td></tr>
    </table>
    <div class="linha"></div>

    <p>Cliente: {{ $documento->venda->cliente->nome ?? 'Consumidor não identificado' }}</p>
    <p>Emissão: {{ $documento->created_at->format('d/m/Y H:i:s') }}</p>

    @if ($documento->status === 'cancelada')
        <p><strong>DOCUMENTO CANCELADO</strong><br>{{ $documento->motivo_cancelamento }}</p>
    @endif

    <p class="chave">
        Chave de acesso:<br>{{ $documento->chave_acesso }}<br>
        Protocolo: {{ $documento->protocolo_autorizacao }}
    </p>

    <p style="text-align:center; margin-top:12px;">
        <button onclick="window.print()">Imprimir</button>
    </p>
</body>
</html>
