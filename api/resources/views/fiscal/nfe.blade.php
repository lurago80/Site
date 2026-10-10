@php
    // ---------- Dados do DANFE: usa o XML emitido quando existe; senão o banco ----------
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
    $mascara = function ($valor, $tipo) {
        $d = preg_replace('/\D/', '', (string) $valor);
        if ($tipo === 'cnpj' && strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        if ($tipo === 'cpf' && strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        if (strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        if (strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        return $valor;
    };
    $dataHora = function ($iso) {
        try { return $iso ? \Carbon\Carbon::parse($iso)->format('d/m/Y H:i:s') : ''; } catch (\Throwable $e) { return ''; }
    };

    $empresa = $documento->empresa;
    $emit = $inf->emit ?? null;
    $dest = $inf->dest ?? null;
    $ide = $inf->ide ?? null;
    $tot = $inf->total->ICMSTot ?? null;

    $destinatarioDb = $documento->cliente ?? $documento->venda?->cliente;
    $enderecoDb = ($documento->venda?->tipo_entrega === 'entrega' && $documento->venda->endereco_entrega)
        ? $documento->venda->endereco_entrega
        : ($destinatarioDb ? $destinatarioDb->only(['logradouro', 'numero', 'bairro', 'municipio', 'uf', 'cep']) : []);

    $chave = preg_replace('/\D/', '', (string) $documento->chave_acesso);
    $chaveFmt = trim(chunk_split($chave, 4, ' '));

    // Emitente
    $emitNome = $v($emit, 'xNome', $empresa->razao_social);
    $emitCnpj = $mascara($v($emit, 'CNPJ', $empresa->cnpj), 'cnpj');
    $emitIe = $v($emit, 'IE');
    $ender = $emit->enderEmit ?? null;
    $emitEndereco = $ender
        ? trim($v($ender, 'xLgr').', '.$v($ender, 'nro').($v($ender, 'xCpl') ? ' - '.$v($ender, 'xCpl') : ''))
        : trim($empresa->logradouro.', '.$empresa->numero);
    $emitBairro = $v($ender, 'xBairro', $empresa->bairro);
    $emitMun = $v($ender, 'xMun', $empresa->municipio);
    $emitUf = $v($ender, 'UF', $empresa->uf);
    $emitCep = $v($ender, 'CEP', $empresa->cep);

    // Destinatário
    $destNome = $v($dest, 'xNome', $destinatarioDb->nome ?? ($documento->compra?->fornecedor?->razao_social ?? 'Não identificado'));
    $destDoc = $mascara($v($dest, 'CNPJ', $v($dest, 'CPF', $destinatarioDb->cpf_cnpj ?? ($documento->compra?->fornecedor?->cnpj ?? ''))), '');
    $destIe = $v($dest, 'IE');
    $de = $dest->enderDest ?? null;
    $destEndereco = $de
        ? trim($v($de, 'xLgr').', '.$v($de, 'nro').($v($de, 'xCpl') ? ' - '.$v($de, 'xCpl') : ''))
        : trim(($enderecoDb['logradouro'] ?? '').(! empty($enderecoDb['logradouro']) ? ', '.($enderecoDb['numero'] ?? '') : ''));
    $destBairro = $v($de, 'xBairro', $enderecoDb['bairro'] ?? '');
    $destMun = $v($de, 'xMun', $enderecoDb['municipio'] ?? '');
    $destUf = $v($de, 'UF', $enderecoDb['uf'] ?? '');
    $destCep = $v($de, 'CEP', $enderecoDb['cep'] ?? '');
    $destFone = $v($de, 'fone', $v($dest, 'fone'));

    // Datas
    $dhEmi = $v($ide, 'dhEmi');
    $dataEmissao = $dhEmi ? \Carbon\Carbon::parse($dhEmi)->format('d/m/Y') : $documento->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y');
    $dataSaida = $v($ide, 'dhSaiEnt') ? \Carbon\Carbon::parse($v($ide, 'dhSaiEnt'))->format('d/m/Y') : $dataEmissao;
    $horaSaida = $v($ide, 'dhSaiEnt') ? \Carbon\Carbon::parse($v($ide, 'dhSaiEnt'))->format('H:i:s') : ($dhEmi ? \Carbon\Carbon::parse($dhEmi)->format('H:i:s') : '');
    $tipoNf = $v($ide, 'tpNF', ($documento->direcao_devolucao ?? null) === 'entrada' ? '0' : '1');

    // Itens (banco, enriquecido com o XML quando existir)
    $itens = [];
    foreach ($documento->itens as $i => $item) {
        $det = $inf->det[$i] ?? null;
        $prod = $det->prod ?? null;
        $icms = $det ? ($det->imposto->ICMS->children()[0] ?? null) : null;
        $itens[] = [
            'codigo' => $v($prod, 'cProd', (string) ($item->produto_id ?? ($i + 1))),
            'descricao' => $v($prod, 'xProd', $item->descricao ?: ($item->produto->nome ?? 'Item')),
            'ncm' => $v($prod, 'NCM', $item->ncm),
            'cst' => $v($icms, 'CSOSN', $v($icms, 'CST', $item->cst_csosn)),
            'cfop' => $v($prod, 'CFOP', $item->cfop),
            'un' => $v($prod, 'uCom', 'UN'),
            'qtd' => (float) $v($prod, 'qCom', $item->quantidade),
            'vun' => (float) $v($prod, 'vUnCom', $item->valor_unitario),
            'vtotal' => (float) $v($prod, 'vProd', $item->valor_total),
            'vdesc' => (float) $v($prod, 'vDesc', 0),
            'bc' => (float) $v($icms, 'vBC', $item->base_calculo_icms ?? 0),
            'vicms' => (float) $v($icms, 'vICMS', $item->valor_icms ?? 0),
            'picms' => (float) $v($icms, 'pICMS', $item->aliquota_icms ?? 0),
        ];
    }

    // Totais
    $vBC = (float) $v($tot, 'vBC', 0);
    $vICMS = (float) $v($tot, 'vICMS', $documento->valor_icms ?? 0);
    $vProd = (float) $v($tot, 'vProd', $documento->valor_produtos);
    $vFrete = (float) $v($tot, 'vFrete', $documento->frete ?? 0);
    $vDesc = (float) $v($tot, 'vDesc', $documento->desconto ?? 0);
    $vOutro = (float) $v($tot, 'vOutro', 0);
    $vSeg = (float) $v($tot, 'vSeg', 0);
    $vNF = (float) $v($tot, 'vNF', $documento->total);

    // Transporte
    $transp = $inf->transp ?? null;
    $modFrete = $v($transp, 'modFrete', (string) ($documento->modalidade_frete ?? '9'));
    $modFreteTxt = [
        '0' => '0 - Remetente (CIF)', '1' => '1 - Destinatário (FOB)', '2' => '2 - Terceiros',
        '3' => '3 - Próprio remetente', '4' => '4 - Próprio destinatário', '9' => '9 - Sem frete',
    ][$modFrete] ?? $modFrete;
    $tr = $transp->transporta ?? null;
    $trNome = $v($tr, 'xNome', $documento->transportadora['nome'] ?? '');
    $trDoc = $mascara($v($tr, 'CNPJ', $v($tr, 'CPF', $documento->transportadora['documento'] ?? '')), '');
    $trIe = $v($tr, 'IE');
    $trEnd = $v($tr, 'xEnder');
    $trMun = $v($tr, 'xMun', $documento->transportadora['municipio'] ?? '');
    $trUf = $v($tr, 'UF', $documento->transportadora['uf'] ?? '');
    $vol = $transp->vol ?? null;

    // Informações complementares (+ tributos aproximados, Lei 12.741/2012)
    $infCpl = $v($inf->infAdic ?? null, 'infCpl', (string) $documento->informacoes_adicionais);
    $vTotTribNf = (float) $v($tot, 'vTotTrib', 0);
    if ($vTotTribNf > 0) {
        $infCpl = trim($infCpl.' Valor aproximado dos tributos (Lei 12.741/2012): R$ '.number_format($vTotTribNf, 2, ',', '.')
            .($vNF > 0 ? ' ('.number_format($vTotTribNf / $vNF * 100, 2, ',', '.').'%)' : '').' - Fonte: IBPT.');
    }

    // Protocolo
    $protocolo = $documento->protocolo_autorizacao;
    $dataProtocolo = $documento->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s');

    // Natureza da operação
    $natOp = $v($ide, 'natOp', (string) $documento->natureza_operacao);

    // Logotipo da empresa (embutido em base64 a partir do disco público)
    $logoSrc = null;
    if (! empty($empresa->logo_url)) {
        $relativo = ltrim(urldecode((string) parse_url($empresa->logo_url, PHP_URL_PATH)), '/');
        $relativo = preg_replace('#^.*?storage/#', '', $relativo);
        $arquivoLogo = storage_path('app/public/'.$relativo);
        if (is_file($arquivoLogo)) {
            $mime = mime_content_type($arquivoLogo) ?: 'image/png';
            $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($arquivoLogo));
        }
    }

    // Código de barras Code 128-C da chave de acesso
    $padroes = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];
    $codigos = [105];
    for ($p = 0; $p + 1 < strlen($chave); $p += 2) {
        $codigos[] = (int) substr($chave, $p, 2);
    }
    $soma = $codigos[0];
    foreach ($codigos as $idx => $c) {
        if ($idx > 0) $soma += $c * $idx;
    }
    $codigos[] = $soma % 103;
    $codigos[] = 106;
    $barras = '';
    $x = 0;
    foreach ($codigos as $c) {
        foreach (str_split($padroes[$c]) as $k => $w) {
            if ($k % 2 === 0) $barras .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="40"/>';
            $x += (int) $w;
        }
    }
    $larguraBarras = $x;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DANFE NFe {{ $documento->numero }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; margin: 0; padding: 10px; background: #fff; }
        .danfe { width: 194mm; margin: 0 auto; position: relative; }
        .linha { display: flex; }
        .box { border: 1px solid #000; padding: 2px 4px; margin: 0 -1px -1px 0; min-height: 24px; overflow: hidden; }
        .box .rot { display: block; font-size: 6.5px; text-transform: uppercase; color: #000; }
        .box .val { display: block; font-size: 9.5px; font-weight: bold; word-break: break-word; }
        .box .val.n { font-weight: normal; }
        .dir { text-align: right; }
        .centro { text-align: center; }
        .titulo { font-size: 7.5px; font-weight: bold; text-transform: uppercase; margin: 6px 0 2px; }
        .f1 { flex: 1; } .f2 { flex: 2; } .f3 { flex: 3; } .f4 { flex: 4; } .f5 { flex: 5; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 2px 3px; font-size: 8.5px; }
        th { font-size: 6.5px; text-transform: uppercase; font-weight: normal; text-align: center; }
        td.n { text-align: right; white-space: nowrap; }
        td.c { text-align: center; }
        .canhoto { border: 1px solid #000; margin-bottom: 4px; }
        .corte { border-top: 1px dashed #000; margin: 5px 0 6px; }
        .emitente { text-align: center; padding: 4px; }
        .emitente .nome { font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .danfe-tit { text-align: center; padding: 2px 4px; }
        .danfe-tit .t { font-size: 14px; font-weight: bold; }
        .tipo { display: inline-block; border: 1px solid #000; padding: 0 6px; font-size: 13px; font-weight: bold; margin: 0 4px; }
        .barras svg { width: 100%; height: 34px; display: block; }
        .marca { position: absolute; top: 240px; left: 0; right: 0; text-align: center; font-size: 50px; font-weight: bold; color: rgba(176,37,37,.18); transform: rotate(-20deg); pointer-events: none; }
        .acoes { text-align: center; margin: 14px 0; }
        .acoes button { padding: 6px 18px; font-size: 13px; cursor: pointer; }
        @media print { body { padding: 0; } .acoes { display: none; } }
    </style>
</head>
<body>
<div class="danfe">

    @if ($documento->ambiente === 'homologacao')
        <div class="marca">SEM VALOR FISCAL</div>
    @endif
    @if ($documento->status === 'cancelada')
        <div class="marca" style="color:rgba(176,37,37,.3);">CANCELADA</div>
    @endif

    {{-- Canhoto --}}
    <div class="canhoto">
        <div class="linha">
            <div class="box f5" style="margin:0; border:0; border-right:1px solid #000;">
                <span class="rot" style="font-size:7px;">Recebemos de {{ $emitNome }} os produtos e/ou serviços constantes da nota fiscal eletrônica indicada ao lado. Emissão: {{ $dataEmissao }} — Valor total: R$ {{ $dinheiro($vNF) }} — Destinatário: {{ $destNome }}</span>
            </div>
            <div class="box f1 centro" style="margin:0; border:0;">
                <span class="val" style="font-size:11px;">NF-e</span>
                <span class="val">Nº {{ str_pad($documento->numero, 9, '0', STR_PAD_LEFT) }}</span>
                <span class="val">Série {{ $documento->serie }}</span>
            </div>
        </div>
        <div class="linha" style="border-top:1px solid #000;">
            <div class="box f1" style="margin:0; border:0; border-right:1px solid #000;"><span class="rot">Data de recebimento</span></div>
            <div class="box f3" style="margin:0; border:0;"><span class="rot">Identificação e assinatura do recebedor</span></div>
        </div>
    </div>
    <div class="corte"></div>

    {{-- Cabeçalho --}}
    <div class="linha">
        <div class="box f5 emitente" style="min-height:92px;">
            <span class="rot" style="text-align:left;">Identificação do emitente</span>
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo" style="max-height:38px; max-width:140px; object-fit:contain; display:block; margin:0 auto 2px;">
            @endif
            <div class="nome">{{ $emitNome }}</div>
            <div>{{ $emitEndereco }}</div>
            <div>{{ $emitBairro }} — {{ $emitCep ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/\D/', '', $emitCep)) : '' }}</div>
            <div>{{ $emitMun }} - {{ $emitUf }}</div>
        </div>
        <div class="box f3 danfe-tit" style="min-height:92px;">
            <div class="t">DANFE</div>
            <div style="font-size:7.5px;">Documento Auxiliar da Nota Fiscal Eletrônica</div>
            <div style="margin:4px 0;">
                <span style="font-size:8px;">0 - ENTRADA<br>1 - SAÍDA</span>
                <span class="tipo">{{ $tipoNf }}</span>
            </div>
            <div style="font-weight:bold; font-size:10px;">Nº {{ str_pad($documento->numero, 9, '0', STR_PAD_LEFT) }}</div>
            <div style="font-weight:bold; font-size:10px;">Série {{ $documento->serie }}</div>
            <div style="font-size:8px;">Folha 1/1</div>
        </div>
        <div class="f5" style="display:flex; flex-direction:column;">
            <div class="box barras" style="min-height:44px; padding:3px 5px;">
                @if ($chave !== '')
                    <svg viewBox="0 0 {{ $larguraBarras }} 40" preserveAspectRatio="none" shape-rendering="crispEdges" fill="#000">{!! $barras !!}</svg>
                @endif
            </div>
            <div class="box" style="min-height:24px;">
                <span class="rot">Chave de acesso</span>
                <span class="val" style="font-size:9px; letter-spacing:.3px;">{{ $chaveFmt }}</span>
            </div>
            <div class="box centro" style="min-height:22px; flex:1;">
                <span class="val n" style="font-size:8px;">Consulta de autenticidade no portal nacional da NF-e<br>www.nfe.fazenda.gov.br/portal ou no site da Sefaz autorizadora</span>
            </div>
        </div>
    </div>

    <div class="linha">
        <div class="box f4"><span class="rot">Natureza da operação</span><span class="val">{{ $natOp }}</span></div>
        <div class="box f3">
            <span class="rot">Protocolo de autorização de uso</span>
            <span class="val">{{ $protocolo }}{{ $dataProtocolo ? ' - '.$dataProtocolo : '' }}</span>
        </div>
    </div>
    <div class="linha">
        <div class="box f2"><span class="rot">Inscrição estadual</span><span class="val">{{ $emitIe }}</span></div>
        <div class="box f2"><span class="rot">Inscrição estadual do subst. trib.</span><span class="val">&nbsp;</span></div>
        <div class="box f2"><span class="rot">CNPJ</span><span class="val">{{ $emitCnpj }}</span></div>
    </div>

    @if ($documento->documento_fiscal_origem_id)
        <div class="box" style="margin-top:4px;">
            <span class="val n">Regularização da NFC-e nº {{ $documento->documentoOrigem->numero ?? '-' }} (chave {{ $documento->documentoOrigem->chave_acesso ?? '-' }})</span>
        </div>
    @endif

    {{-- Destinatário --}}
    <div class="titulo">Destinatário / Remetente</div>
    <div class="linha">
        <div class="box f5"><span class="rot">Nome / Razão social</span><span class="val">{{ $destNome }}</span></div>
        <div class="box f2"><span class="rot">CNPJ / CPF</span><span class="val">{{ $destDoc }}</span></div>
        <div class="box f2"><span class="rot">Data da emissão</span><span class="val">{{ $dataEmissao }}</span></div>
    </div>
    <div class="linha">
        <div class="box f4"><span class="rot">Endereço</span><span class="val">{{ $destEndereco }}</span></div>
        <div class="box f2"><span class="rot">Bairro / Distrito</span><span class="val">{{ $destBairro }}</span></div>
        <div class="box f1"><span class="rot">CEP</span><span class="val">{{ $destCep ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/\D/', '', $destCep)) : '' }}</span></div>
        <div class="box f2"><span class="rot">Data da saída / entrada</span><span class="val">{{ $dataSaida }}</span></div>
    </div>
    <div class="linha">
        <div class="box f4"><span class="rot">Município</span><span class="val">{{ $destMun }}</span></div>
        <div class="box f1"><span class="rot">UF</span><span class="val">{{ $destUf }}</span></div>
        <div class="box f2"><span class="rot">Fone / Fax</span><span class="val">{{ $destFone }}</span></div>
        <div class="box f2"><span class="rot">Inscrição estadual</span><span class="val">{{ $destIe }}</span></div>
        <div class="box f2"><span class="rot">Hora da saída</span><span class="val">{{ $horaSaida }}</span></div>
    </div>

    {{-- Imposto --}}
    <div class="titulo">Cálculo do imposto</div>
    <div class="linha">
        <div class="box f1"><span class="rot">Base de cálc. do ICMS</span><span class="val dir">{{ $dinheiro($vBC) }}</span></div>
        <div class="box f1"><span class="rot">Valor do ICMS</span><span class="val dir">{{ $dinheiro($vICMS) }}</span></div>
        <div class="box f1"><span class="rot">Base de cálc. ICMS ST</span><span class="val dir">{{ $dinheiro($v($tot, 'vBCST', 0)) }}</span></div>
        <div class="box f1"><span class="rot">Valor do ICMS ST</span><span class="val dir">{{ $dinheiro($v($tot, 'vST', 0)) }}</span></div>
        <div class="box f1"><span class="rot">Valor total dos produtos</span><span class="val dir">{{ $dinheiro($vProd) }}</span></div>
    </div>
    <div class="linha">
        <div class="box f1"><span class="rot">Valor do frete</span><span class="val dir">{{ $dinheiro($vFrete) }}</span></div>
        <div class="box f1"><span class="rot">Valor do seguro</span><span class="val dir">{{ $dinheiro($vSeg) }}</span></div>
        <div class="box f1"><span class="rot">Desconto</span><span class="val dir">{{ $dinheiro($vDesc) }}</span></div>
        <div class="box f1"><span class="rot">Outras desp. acessórias</span><span class="val dir">{{ $dinheiro($vOutro) }}</span></div>
        <div class="box f1"><span class="rot">Valor total da nota</span><span class="val dir">{{ $dinheiro($vNF) }}</span></div>
    </div>

    {{-- Transportador --}}
    <div class="titulo">Transportador / Volumes transportados</div>
    <div class="linha">
        <div class="box f4"><span class="rot">Razão social</span><span class="val">{{ $trNome }}</span></div>
        <div class="box f2"><span class="rot">Frete</span><span class="val">{{ $modFreteTxt }}</span></div>
        <div class="box f2"><span class="rot">CNPJ / CPF</span><span class="val">{{ $trDoc }}</span></div>
    </div>
    <div class="linha">
        <div class="box f4"><span class="rot">Endereço</span><span class="val">{{ $trEnd }}</span></div>
        <div class="box f3"><span class="rot">Município</span><span class="val">{{ $trMun }}</span></div>
        <div class="box f1"><span class="rot">UF</span><span class="val">{{ $trUf }}</span></div>
        <div class="box f2"><span class="rot">Inscrição estadual</span><span class="val">{{ $trIe }}</span></div>
    </div>
    <div class="linha">
        <div class="box f1"><span class="rot">Quantidade</span><span class="val">{{ $v($vol, 'qVol') }}</span></div>
        <div class="box f2"><span class="rot">Espécie</span><span class="val">{{ $v($vol, 'esp') }}</span></div>
        <div class="box f2"><span class="rot">Marca</span><span class="val">{{ $v($vol, 'marca') }}</span></div>
        <div class="box f2"><span class="rot">Numeração</span><span class="val">{{ $v($vol, 'nVol') }}</span></div>
        <div class="box f1"><span class="rot">Peso bruto</span><span class="val">{{ $v($vol, 'pesoB') }}</span></div>
        <div class="box f1"><span class="rot">Peso líquido</span><span class="val">{{ $v($vol, 'pesoL') }}</span></div>
    </div>

    {{-- Itens --}}
    <div class="titulo">Dados dos produtos / serviços</div>
    <table>
        <thead>
            <tr>
                <th style="width:9%;">Código</th>
                <th>Descrição do produto / serviço</th>
                <th style="width:8%;">NCM/SH</th>
                <th style="width:5%;">CST/<br>CSOSN</th>
                <th style="width:5%;">CFOP</th>
                <th style="width:4%;">UN</th>
                <th style="width:7%;">Quant.</th>
                <th style="width:8%;">Valor unit.</th>
                <th style="width:8%;">Valor total</th>
                <th style="width:7%;">BC ICMS</th>
                <th style="width:6%;">Valor ICMS</th>
                <th style="width:5%;">% ICMS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($itens as $it)
                <tr>
                    <td>{{ $it['codigo'] }}</td>
                    <td>{{ $it['descricao'] }}</td>
                    <td class="c">{{ $it['ncm'] }}</td>
                    <td class="c">{{ $it['cst'] }}</td>
                    <td class="c">{{ $it['cfop'] }}</td>
                    <td class="c">{{ $it['un'] }}</td>
                    <td class="n">{{ number_format($it['qtd'], 4, ',', '.') }}</td>
                    <td class="n">{{ number_format($it['vun'], 4, ',', '.') }}</td>
                    <td class="n">{{ $dinheiro($it['vtotal']) }}</td>
                    <td class="n">{{ $dinheiro($it['bc']) }}</td>
                    <td class="n">{{ $dinheiro($it['vicms']) }}</td>
                    <td class="n">{{ $dinheiro($it['picms']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Dados adicionais --}}
    <div class="titulo">Dados adicionais</div>
    <div class="linha">
        <div class="box f5" style="min-height:70px;">
            <span class="rot">Informações complementares</span>
            <span class="val n" style="font-size:8.5px;">{{ $infCpl }}</span>
            @if ($documento->status === 'cancelada')
                <span class="val" style="color:#b02525; margin-top:4px;">DOCUMENTO CANCELADO: {{ $documento->motivo_cancelamento }}</span>
            @endif
        </div>
        <div class="box f3" style="min-height:70px;"><span class="rot">Reservado ao fisco</span></div>
    </div>

    <div class="acoes">
        <button onclick="window.print()">Imprimir</button>
    </div>
</div>
@if (request()->boolean('imprimir'))
    <script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
