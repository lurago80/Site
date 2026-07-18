<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>NFe {{ $documento->numero }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; max-width: 720px; margin: 0 auto; padding: 24px; color: #1f2933; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .cabecalho { display: flex; justify-content: space-between; border-bottom: 2px solid #1f2933; padding-bottom: 10px; margin-bottom: 12px; }
        .bloco { border: 1px solid #cbd2d9; border-radius: 4px; padding: 10px 12px; margin-bottom: 10px; }
        .bloco h2 { font-size: 11px; text-transform: uppercase; color: #616e7c; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e4e7eb; }
        th { background: #f4f5f7; }
        td.num { text-align: right; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 10px; font-weight: bold; font-size: 11px; }
        .status.autorizada { background: #d1fae5; color: #046c4e; }
        .status.cancelada { background: #ffe3e3; color: #b02525; text-decoration: line-through; }
        .status.rejeitada { background: #ffe3e3; color: #b02525; }
        .chave { word-break: break-all; font-size: 10px; text-align: center; margin-top: 14px; color: #616e7c; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <div class="cabecalho">
        <div>
            <h1>{{ $documento->empresa->razao_social }}</h1>
            <p>CNPJ: {{ $documento->empresa->cnpj }}<br>
            {{ $documento->empresa->logradouro }}, {{ $documento->empresa->numero }} —
            {{ $documento->empresa->bairro }} — {{ $documento->empresa->municipio }}/{{ $documento->empresa->uf }}</p>
        </div>
        <div style="text-align:right;">
            <strong>NFe nº {{ $documento->numero }}</strong><br>
            Série {{ $documento->serie }}<br>
            <span class="status {{ $documento->status }}">{{ strtoupper($documento->status) }}</span>
        </div>
    </div>

    @if ($documento->documento_fiscal_origem_id)
        <p style="font-size:11px; color:#616e7c;">
            Regularização da NFC-e nº {{ $documento->documentoOrigem->numero ?? '-' }}
            (chave {{ $documento->documentoOrigem->chave_acesso ?? '-' }})
        </p>
    @endif

    <div class="bloco">
        <h2>Destinatário</h2>
        <p>
            {{ $documento->venda->cliente->nome ?? 'Não identificado' }}
            — {{ $documento->venda->cliente->cpf_cnpj ?? '-' }}<br>
            @if ($documento->venda->cliente?->logradouro)
                {{ $documento->venda->cliente->logradouro }}, {{ $documento->venda->cliente->numero }} —
                {{ $documento->venda->cliente->bairro }} —
                {{ $documento->venda->cliente->municipio }}/{{ $documento->venda->cliente->uf }}
            @endif
        </p>
    </div>

    <div class="bloco">
        <h2>Itens</h2>
        <table>
            <thead><tr><th>Produto</th><th>NCM</th><th>CFOP</th><th>Qtd</th><th class="num">Valor</th></tr></thead>
            <tbody>
                @foreach ($documento->itens as $item)
                    <tr>
                        <td>{{ $item->produto->nome ?? 'Item' }}</td>
                        <td>{{ $item->ncm }}</td>
                        <td>{{ $item->cfop }}</td>
                        <td>{{ $item->quantidade }}</td>
                        <td class="num">R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bloco">
        <h2>Totais</h2>
        <p style="font-size:16px; font-weight:bold; text-align:right;">
            R$ {{ number_format($documento->total, 2, ',', '.') }}
        </p>
    </div>

    @if ($documento->status === 'cancelada')
        <div class="bloco" style="border-color:#b02525;">
            <h2 style="color:#b02525;">Documento cancelado</h2>
            <p>{{ $documento->motivo_cancelamento }}</p>
        </div>
    @endif

    <p class="chave">
        Chave de acesso: {{ $documento->chave_acesso }}<br>
        Protocolo de autorização: {{ $documento->protocolo_autorizacao }}
    </p>

    <p style="text-align:center; margin-top:16px;">
        <button onclick="window.print()">Imprimir</button>
    </p>
</body>
</html>
