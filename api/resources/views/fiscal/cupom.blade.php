@php
    // ---------- Dados do DANFE NFC-e: XML emitido quando existe; senão o banco ----------
    $xml = null;
    if (! empty($documento->xml_path) && is_file($documento->xml_path)) {
        $limpo = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', file_get_contents($documento->xml_path));
        $xml = @simplexml_load_string($limpo) ?: null;
        if ($xml && isset($xml->NFe)) {
            $xml = $xml->NFe;
        }
    }
    $inf = $xml->infNFe ?? null;
    $v = fn ($obj, $campo, $padrao = '') => ($obj && isset($obj->{$campo}) && (string) $obj->{$campo} !== '') ? (string) $obj->{$campo} : $padrao;
    $dinheiro = fn ($n) => number_format((float) $n, 2, ',', '.');
    $mascara = function ($valor) {
        $d = preg_replace('/\D/', '', (string) $valor);
        if (strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        if (strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        return $valor;
    };
    $tz = 'America/Sao_Paulo';

    $empresa = $documento->empresa;
    $emit = $inf->emit ?? null;
    $ender = $emit->enderEmit ?? null;
    $ide = $inf->ide ?? null;
    $tot = $inf->total->ICMSTot ?? null;

    $emitNome = $v($emit, 'xNome', $empresa->razao_social);
    $emitCnpj = $mascara($v($emit, 'CNPJ', $empresa->cnpj));
    $emitIe = $v($emit, 'IE');
    $emitEndereco = $ender
        ? trim($v($ender, 'xLgr').', '.$v($ender, 'nro').' - '.$v($ender, 'xBairro').', '.$v($ender, 'xMun').'/'.$v($ender, 'UF'))
        : trim($empresa->logradouro.', '.$empresa->numero.' - '.$empresa->bairro.', '.$empresa->municipio.'/'.$empresa->uf);

    // Itens
    // Composição do kit (garrafas/copos escolhidos) e data/hora da visita: não
    // fazem parte do XML autorizado (a NFC-e tem só a linha do kit/produto),
    // são só um complemento informativo impresso abaixo do item.
    $itensVendaPorId = $documento->venda?->itens->keyBy('id') ?? collect();
    $itens = [];
    foreach ($documento->itens as $i => $item) {
        $prod = ($inf->det[$i] ?? null)?->prod;
        $itemVenda = $item->item_venda_id ? $itensVendaPorId->get($item->item_venda_id) : null;

        $detalhe = [];
        foreach ($itemVenda?->composicao ?? [] as $componente) {
            $nomeComponente = $componente['nome'] ?? '';
            if (! empty($componente['tamanho'])) {
                $nomeComponente .= ' ('.$componente['tamanho'].')';
            }
            $detalhe[] = '- '.($componente['quantidade'] ?? 1).'x '.$nomeComponente;
        }
        if ($itemVenda?->agendaVisitacao) {
            $detalhe[] = '- Visita agendada: '.$itemVenda->agendaVisitacao->data_hora->timezone($tz)->format('d/m/Y H:i');
        }

        $itens[] = [
            'codigo' => $v($prod, 'cProd', (string) ($item->produto_id ?? ($i + 1))),
            'descricao' => $v($prod, 'xProd', $item->descricao ?: ($item->produto->nome ?? 'Item')),
            'qtd' => (float) $v($prod, 'qCom', $item->quantidade),
            'un' => $v($prod, 'uCom', 'UN'),
            'vun' => (float) $v($prod, 'vUnCom', $item->valor_unitario),
            'vtotal' => (float) $v($prod, 'vProd', $item->valor_total),
            'detalhe' => $detalhe,
        ];
    }

    $vProd = (float) $v($tot, 'vProd', $documento->valor_produtos ?: $documento->total);
    $vDesc = (float) $v($tot, 'vDesc', $documento->desconto ?? 0);
    $vFrete = (float) $v($tot, 'vFrete', $documento->frete ?? 0);
    $vNF = (float) $v($tot, 'vNF', $documento->total);
    $vTotTrib = (float) $v($tot, 'vTotTrib', 0);
    if ($vTotTrib <= 0 && $inf) {
        foreach ($inf->det as $det) {
            $vTotTrib += (float) ($det->imposto->vTotTrib ?? 0);
        }
    }

    // Pagamento
    $formas = ['01' => 'Dinheiro', '02' => 'Cheque', '03' => 'Cartão de Crédito', '04' => 'Cartão de Débito', '05' => 'Crédito Loja',
        '10' => 'Vale Alimentação', '11' => 'Vale Refeição', '12' => 'Vale Presente', '13' => 'Vale Combustível', '15' => 'Boleto Bancário',
        '16' => 'Depósito Bancário', '17' => 'PIX', '18' => 'Transferência', '19' => 'Programa de Fidelidade', '90' => 'Sem Pagamento', '99' => 'Outros'];
    $pagamentos = [];
    foreach ($inf->pag->detPag ?? [] as $dp) {
        $pagamentos[] = [$formas[(string) $dp->tPag] ?? 'Outros', (float) $dp->vPag];
    }
    if (! $pagamentos) {
        $cod = $documento->tpag ?? $documento->venda?->formaPagamento?->codigo_tpag;
        $pagamentos[] = [$documento->venda?->formaPagamento?->descricao ?? ($formas[$cod] ?? 'Outros'), $vNF];
    }
    $vTroco = (float) ($inf->pag->vTroco ?? 0);

    // Consumidor
    $dest = $inf->dest ?? null;
    $consDoc = $v($dest, 'CPF', $v($dest, 'CNPJ'));
    $consNome = $v($dest, 'xNome', $documento->venda?->cliente?->nome ?? '');
    if (! $consDoc && ! $dest && ! empty($documento->venda?->cliente?->cpf_cnpj)) {
        $consDoc = $documento->venda->cliente->cpf_cnpj;
    }

    // Emissão / autorização (sempre em horário de Brasília)
    $dhEmi = $v($ide, 'dhEmi');
    $emissao = $dhEmi ? \Carbon\Carbon::parse($dhEmi)->format('d/m/Y H:i:s') : $documento->created_at?->timezone($tz)->format('d/m/Y H:i:s');
    $dataAutorizacao = $documento->created_at?->timezone($tz)->format('d/m/Y H:i:s');

    $chave = preg_replace('/\D/', '', (string) $documento->chave_acesso);
    $chaveFmt = trim(chunk_split($chave, 4, ' '));
    $supl = $xml->infNFeSupl ?? null;
    $qrUrl = $v($supl, 'qrCode');
    $urlChave = $v($supl, 'urlChave', 'https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica');
    $infCpl = $v($inf->infAdic ?? null, 'infCpl', (string) $documento->informacoes_adicionais);

    // Logotipo da empresa (embutido em base64)
    $logoSrc = null;
    if (! empty($empresa->logo_url)) {
        $relativo = ltrim(urldecode((string) parse_url($empresa->logo_url, PHP_URL_PATH)), '/');
        $relativo = preg_replace('#^.*?storage/#', '', $relativo);
        $arquivoLogo = storage_path('app/public/'.$relativo);
        if (is_file($arquivoLogo)) {
            $logoSrc = 'data:'.(mime_content_type($arquivoLogo) ?: 'image/png').';base64,'.base64_encode(file_get_contents($arquivoLogo));
        }
    }
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DANFE NFC-e {{ $documento->numero }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { background: #fff; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; font-weight: 700; color: #000; width: 80mm; max-width: 100%; margin: 0 auto; padding: 4mm 3mm; line-height: 1.3; }
        .centro { text-align: center; }
        .sep { border-top: 2px dashed #000; margin: 6px 0; }
        .nome { font-size: 14px; text-transform: uppercase; }
        .peq { font-size: 10.5px; }
        .tit { font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 10px; text-transform: uppercase; text-align: left; border-bottom: 1px solid #000; padding: 2px 0; }
        td { padding: 2px 0; vertical-align: top; font-size: 11.5px; }
        td.n, th.n { text-align: right; white-space: nowrap; padding-left: 3px; }
        .linha { display: flex; justify-content: space-between; gap: 6px; }
        .total { font-size: 15px; }
        .chave { word-break: break-all; font-size: 11px; letter-spacing: .2px; }
        .qr { text-align: center; margin: 6px 0; }
        .qr svg { width: 38mm; height: 38mm; }
        .aviso { text-align: center; border: 2px solid #000; padding: 4px; margin: 6px 0; text-transform: uppercase; }
        .acoes { margin-top: 10px; text-align: center; }
        .acoes button { width: 100%; padding: 8px; font-size: 13px; cursor: pointer; }
        @media print { .acoes { display: none; } body { padding: 2mm 3mm; } }
    </style>
</head>
<body>
    @if ($logoSrc)
        <div class="centro"><img src="{{ $logoSrc }}" alt="Logo" style="max-height:18mm; max-width:60mm; object-fit:contain;"></div>
    @endif
    <div class="centro nome">{{ $emitNome }}</div>
    <div class="centro peq">CNPJ: {{ $emitCnpj }}@if ($emitIe) &nbsp; IE: {{ $emitIe }}@endif</div>
    <div class="centro peq">{{ $emitEndereco }}</div>

    <div class="sep"></div>
    <div class="centro tit">DANFE NFC-e<br>Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</div>
    <div class="centro peq">Não permite aproveitamento de crédito de ICMS</div>

    @if ($documento->ambiente === 'homologacao')
        <div class="aviso">Emitida em ambiente de homologação — sem valor fiscal</div>
    @endif
    @if ($documento->status === 'cancelada')
        <div class="aviso">NFC-e cancelada<br><span class="peq">{{ $documento->motivo_cancelamento }}</span></div>
    @endif
    @if ($documento->status === 'rejeitada')
        <div class="aviso">NFC-e rejeitada — sem valor fiscal</div>
    @endif

    <div class="sep"></div>
    <table>
        <thead>
            <tr><th>Cód.</th><th>Descrição</th><th class="n">Qtd</th><th class="n">Un</th><th class="n">Vl.Unit</th><th class="n">Vl.Total</th></tr>
        </thead>
        <tbody>
            @foreach ($itens as $it)
                <tr>
                    <td>{{ $it['codigo'] }}</td>
                    <td>{{ $it['descricao'] }}</td>
                    <td class="n">{{ rtrim(rtrim(number_format($it['qtd'], 3, ',', '.'), '0'), ',') }}</td>
                    <td class="n">{{ $it['un'] }}</td>
                    <td class="n">{{ $dinheiro($it['vun']) }}</td>
                    <td class="n">{{ $dinheiro($it['vtotal']) }}</td>
                </tr>
                @if (! empty($it['detalhe']))
                    <tr>
                        <td></td>
                        <td colspan="5" class="peq">{!! implode('<br>', array_map('e', $it['detalhe'])) !!}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="sep"></div>
    <div class="linha"><span>Qtd. total de itens</span><span>{{ count($itens) }}</span></div>
    @if ($vDesc > 0 || $vFrete > 0)
        <div class="linha"><span>Valor dos produtos</span><span>R$ {{ $dinheiro($vProd) }}</span></div>
    @endif
    @if ($vDesc > 0)
        <div class="linha"><span>Desconto</span><span>- R$ {{ $dinheiro($vDesc) }}</span></div>
    @endif
    @if ($vFrete > 0)
        <div class="linha"><span>Frete</span><span>R$ {{ $dinheiro($vFrete) }}</span></div>
    @endif
    <div class="linha total"><span>VALOR TOTAL R$</span><span>{{ $dinheiro($vNF) }}</span></div>

    <div class="sep"></div>
    <div class="linha tit"><span>Forma de pagamento</span><span>Valor pago R$</span></div>
    @foreach ($pagamentos as [$formaNome, $formaValor])
        <div class="linha"><span>{{ $formaNome }}</span><span>{{ $dinheiro($formaValor) }}</span></div>
    @endforeach
    @if ($vTroco > 0)
        <div class="linha"><span>Troco</span><span>{{ $dinheiro($vTroco) }}</span></div>
    @endif

    @if ($vTotTrib > 0)
        <div class="sep"></div>
        <div class="peq centro">Tributos Totais Incidentes (Lei Federal 12.741/2012): R$ {{ $dinheiro($vTotTrib) }}@if ($vNF > 0) ({{ number_format($vTotTrib / $vNF * 100, 2, ',', '.') }}%)@endif — Fonte: IBPT</div>
    @endif

    <div class="sep"></div>
    <div class="centro">
        <div>NFC-e nº {{ $documento->numero }} &nbsp; Série {{ $documento->serie }}</div>
        <div>Emissão: {{ $emissao }}</div>
        <div class="peq">Via Consumidor</div>
    </div>

    <div class="sep"></div>
    <div class="centro peq">Consulte pela Chave de Acesso em<br>{{ $urlChave }}</div>
    <div class="centro chave" style="margin-top:3px;">{{ $chaveFmt }}</div>

    <div class="sep"></div>
    <div class="centro">
        <div class="tit">Consumidor</div>
        @if ($consDoc || $consNome)
            <div>{{ $consNome }}@if ($consDoc) — {{ $mascara($consDoc) }}@endif</div>
        @else
            <div>CONSUMIDOR NÃO IDENTIFICADO</div>
        @endif
    </div>

    @if ($qrUrl !== '')
        <div class="qr" id="qr" data-url="{{ $qrUrl }}"></div>
    @endif

    <div class="centro peq">
        @if ($documento->protocolo_autorizacao)
            Protocolo de autorização: {{ $documento->protocolo_autorizacao }}<br>
            Data de autorização: {{ $dataAutorizacao }}
        @endif
    </div>

    @if ($infCpl)
        <div class="sep"></div>
        <div class="peq">{{ $infCpl }}</div>
    @endif

    <div class="acoes">
        <button onclick="window.print()">Imprimir</button>
        <button id="btn-enviar" style="display:none; margin-top:6px;" onclick="window.opener.enviarDocumentoFiscal({{ $documento->id }}, '{{ preg_replace('/\D/', '', (string) ($documento->venda?->cliente?->telefone ?? '')) }}', '{{ $documento->venda?->cliente?->email ?? '' }}')">Enviar por WhatsApp / e-mail</button>
    </div>
    <script>
        try { if (window.opener && typeof window.opener.enviarDocumentoFiscal === 'function') document.getElementById('btn-enviar').style.display = 'block'; } catch (e) {}
    </script>

    @include('fiscal._qrcode')
    <script>
        (function () {
            var el = document.getElementById('qr');
            if (el && typeof qrcode === 'function') {
                var qr = qrcode(0, 'M');
                qr.addData(el.getAttribute('data-url'));
                qr.make();
                el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
            }
            @if (request()->boolean('imprimir'))
                window.onload = function () { window.print(); };
            @endif
        })();
    </script>
</body>
</html>
