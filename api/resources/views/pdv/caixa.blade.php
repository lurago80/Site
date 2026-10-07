<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PDV — Frente de Caixa</title>
    <style>
        :root {
            --bg: #131a22;
            --bg-elev: #1c2733;
            --bg-elev-2: #232f3d;
            --border: #2e3c4b;
            --border-strong: #3e4c59;
            --text: #e7ebf0;
            --text-dim: #8f9bab;
            --accent: #6b76d6;
            --accent-hover: #7c86e0;
            --danger: #ff8080;
            --ok: #6cd67a;
            --warn: #e0b95c;
            --warn-hover: #eac576;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            margin: 0;
            color: var(--text);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Cabeçalho */
        .topo {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 20px;
            background: var(--bg-elev);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .topo-marca { display: flex; align-items: center; gap: 12px; }
        .topo-marca img { height: 28px; border-radius: 4px; }
        h1 { font-size: 15px; margin: 0; font-weight: 600; letter-spacing: .2px; }
        h1 span { color: var(--text-dim); font-weight: 400; }
        .topo-usuario { display: flex; align-items: center; gap: 10px; }
        .topo-usuario span { font-size: 12px; color: var(--text-dim); }

        /* Barra de status do caixa */
        .barra-caixa {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 10px 20px;
            background: var(--bg-elev-2);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .caixa-status { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; margin-right: auto; }
        .caixa-status .ponto { width: 8px; height: 8px; border-radius: 50%; background: var(--text-dim); flex-shrink: 0; }
        .caixa-status.aberto .ponto { background: var(--ok); }
        .caixa-status.fechado .ponto { background: var(--danger); }
        .barra-caixa input { width: 130px; }

        /* Controles genéricos */
        input, select, button {
            font-size: 13px; padding: 9px 11px; border-radius: 6px;
            border: 1px solid var(--border-strong); background: #2a3846; color: var(--text);
            font-family: inherit;
        }
        input::placeholder { color: var(--text-dim); }
        input:focus, select:focus { outline: none; border-color: var(--accent); }
        button { cursor: pointer; transition: background .15s, opacity .15s; white-space: nowrap; }
        button.primario { background: var(--accent); color: #fff; border: none; font-weight: 600; }
        button.primario:hover { background: var(--accent-hover); }
        button.secundario { background: #384656; color: var(--text); border: 1px solid var(--border-strong); }
        button.secundario:hover { background: #40505f; }
        a.btn-visitas-pagas {
            background: #384656; color: var(--text); border: 1px solid var(--border-strong); border-radius: 6px;
            font-size: 13px; font-weight: 600; padding: 8px 14px; text-decoration: none; white-space: nowrap;
        }
        a.btn-visitas-pagas:hover { background: #40505f; }
        button.btn-verificar {
            background: var(--warn); color: #201a08; border: none; font-weight: 700;
            display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px;
        }
        button.btn-verificar:hover { background: var(--warn-hover); }
        button.btn-verificar svg { width: 16px; height: 16px; flex-shrink: 0; }

        .campo { display: flex; flex-direction: column; gap: 4px; }
        .campo label { font-size: 11px; color: var(--text-dim); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }

        /* Layout principal em duas colunas com altura fixa e rolagem interna */
        .layout {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 0;
            min-height: 0;
        }
        .coluna { display: flex; flex-direction: column; min-height: 0; padding: 16px 20px; overflow-y: auto; }
        .coluna-produtos { border-right: 1px solid var(--border); }
        .coluna-carrinho { background: var(--bg-elev); }

        .busca-wrap { position: relative; margin-bottom: 14px; flex-shrink: 0; }
        .busca { width: 100%; padding: 11px 14px; font-size: 14px; }

        .secao-titulo {
            font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-dim);
            font-weight: 700; margin: 18px 0 10px;
        }
        .secao-titulo:first-of-type { margin-top: 0; }

        .grid-produtos { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .produto-btn {
            text-align: left; background: var(--bg-elev-2); border: 1px solid var(--border);
            border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 6px;
            transition: border-color .15s, transform .1s;
            min-width: 0; white-space: normal;
        }
        .produto-btn:hover { border-color: var(--accent); transform: translateY(-1px); }
        .produto-btn:active { transform: translateY(0); }
        .produto-btn .produto-img {
            width: 100%; height: 90px; border-radius: 6px; object-fit: cover;
            background: var(--bg-elev); flex-shrink: 0;
        }
        .produto-btn .produto-img-vazia {
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; color: var(--text-dim);
        }
        .produto-btn .nome {
            font-size: 12.5px; line-height: 1.35;
            display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
            width: 100%; min-width: 0;
        }
        .produto-btn .preco { font-size: 14px; font-weight: 700; color: var(--accent); }
        .produto-btn .minimo { font-size: 10.5px; color: var(--text-dim); }
        .produto-btn select { width: 100%; font-size: 12px; padding: 6px 8px; }
        .produto-btn button.secundario { width: 100%; font-size: 12px; padding: 6px 8px; }

        .agenda-item {
            background: var(--bg-elev-2); border: 1px solid var(--border); border-radius: 8px;
            padding: 10px 12px; margin-bottom: 8px; font-size: 12.5px;
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
        }
        .agenda-item button { flex-shrink: 0; padding: 5px 12px; }

        /* Carrinho */
        .carrinho-tabela-wrap { flex: 1; min-height: 80px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-elev-2); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            position: sticky; top: 0; background: var(--bg-elev-2); text-align: left;
            font-size: 11px; text-transform: uppercase; color: var(--text-dim); font-weight: 700;
            padding: 8px 10px; border-bottom: 1px solid var(--border);
        }
        td { padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }

        .totais {
            display: flex; justify-content: space-between; align-items: baseline;
            font-size: 22px; font-weight: 700; margin: 14px 0; padding: 12px 14px;
            background: var(--bg-elev-2); border-radius: 8px; border: 1px solid var(--border);
            flex-shrink: 0;
        }
        .totais span:first-child { font-size: 12px; text-transform: uppercase; color: var(--text-dim); font-weight: 700; align-self: center; }

        .linha { display: flex; gap: 10px; margin-bottom: 10px; flex-shrink: 0; }
        .linha > * { flex: 1; min-width: 0; }

        .msg { font-size: 12px; margin-top: 8px; }
        .msg.erro { color: var(--danger); }
        .msg.ok { color: var(--ok); }

        .rm { background: transparent; border: none; color: var(--danger); cursor: pointer; padding: 4px 6px; font-size: 14px; }
        .rm:hover { opacity: .7; }

        .btn-finalizar { width: 100%; padding: 14px; font-size: 14px; margin-top: 4px; flex-shrink: 0; }

        /* Barra de atalhos do operador */
        .barra-atalhos {
            display: flex; align-items: center; gap: 22px; flex-wrap: wrap;
            padding: 8px 20px;
            background: var(--bg-elev-2);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            font-size: 12px; color: var(--text-dim);
        }
        .barra-atalhos kbd {
            display: inline-block; padding: 2px 7px; margin-right: 5px;
            background: #2a3846; border: 1px solid var(--border-strong); border-radius: 4px;
            font-family: inherit; font-size: 11px; font-weight: 700; color: var(--text);
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }

        /* Modal de verificação de comprovante */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.6);
            display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px;
        }
        .modal-overlay.aberto { display: flex; }
        .modal-verificar {
            background: var(--bg-elev); border: 1px solid var(--border); border-radius: 12px;
            width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; padding: 22px;
        }
        .modal-verificar h2 { margin: 0 0 4px; font-size: 16px; }
        .modal-verificar .modal-sub { font-size: 12px; color: var(--text-dim); margin: 0 0 18px; }
        .modal-fechar { position: absolute; top: 14px; right: 16px; background: transparent; border: none; color: var(--text-dim); font-size: 20px; cursor: pointer; padding: 4px 8px; }
        .modal-fechar:hover { color: var(--text); }
        .modal-verificar { position: relative; }

        /* Modal de vendas do dia (cancelamento - só administrador) */
        .modal-vendas { max-width: 820px; }
        .tabela-vendas { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tabela-vendas th, .tabela-vendas td { padding: 8px 6px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
        .tabela-vendas tr.cancelada td { color: var(--text-dim); text-decoration: line-through; }
        .tabela-vendas tr.cancelada td.sem-risco { text-decoration: none; }
        .tabela-vendas .tag { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 11px; border: 1px solid var(--border); }
        .tabela-vendas button { padding: 5px 10px; font-size: 12px; }
        .tabela-vendas .motivo { display: block; font-size: 11px; color: var(--text-dim); margin-top: 2px; }

        .busca-verificar { display: flex; gap: 10px; margin-bottom: 18px; }
        .busca-verificar input { flex: 1; font-size: 14px; padding: 11px 13px; }

        .resultado-verificar { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; }
        .icone-status { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
        .icone-status.valido { background: rgba(108,214,122,.15); }
        .icone-status.invalido { background: rgba(255,128,128,.15); }
        .icone-status svg { width: 38px; height: 38px; }
        .icone-status.valido svg { stroke: var(--ok); }
        .icone-status.invalido svg { stroke: var(--danger); }
        .resultado-titulo { font-size: 17px; font-weight: 700; margin: 0; }
        .resultado-titulo.valido { color: var(--ok); }
        .resultado-titulo.invalido { color: var(--danger); }
        .resultado-detalhe { font-size: 13px; color: var(--text-dim); margin: 0 0 10px; }

        .card-venda { width: 100%; background: var(--bg-elev-2); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-top: 6px; text-align: left; }
        .linha-item-v { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
        .linha-item-v:last-child { border-bottom: none; }
        .titulo-secao-v { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-dim); font-weight: 700; margin: 12px 0 4px; }
        .rodape-total-v { display: flex; justify-content: space-between; font-weight: 700; font-size: 15px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); }
    </style>
</head>
<body>
    <div class="topo">
        <div class="topo-marca">
            <img src="{{ $logoEmpresaUrl ?: asset('images/logo.jpg') }}" alt="Logo">
            <h1>PDV — Frente de Caixa <span>· {{ $empresaSlug }}</span></h1>
        </div>
        <div class="topo-usuario">
            <button type="button" class="btn-verificar" onclick="abrirModalVerificar()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                Verificar Ticket (F2)
            </button>
            @if ($podeCancelarVenda)
                <button type="button" class="btn-verificar" onclick="abrirModalVendas()" title="Vendas de hoje - cancelar venda (somente administrador)">Vendas do dia / Cancelar</button>
            @endif
            <button type="button" class="btn-verificar" onclick="abrirModalVendedor()" title="Cadastrar um novo vendedor (guia)">Cadastrar vendedor</button>
            <a class="btn-visitas-pagas" href="{{ url('/pdv/'.$empresaSlug.'/visitas-pagas') }}" target="_blank" rel="noopener" title="Lista de visitas pagas para conferência sem internet">Visitas pagas (PDF)</a>
            <a class="btn-visitas-pagas" href="{{ url('/pdv/'.$empresaSlug.'/caixa-extrato-impressao') }}" target="_blank" rel="noopener" title="Extrato do caixa para conferência e impressão">Extrato do caixa</a>
            <span>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ url('/logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="secundario">Sair</button>
            </form>
        </div>
    </div>

    <div class="barra-caixa" id="card-caixa">
        <div class="caixa-status" id="caixa-status-wrap"><span class="ponto"></span><span id="caixa-status-texto">Carregando status do caixa...</span></div>
        <input type="number" step="0.01" id="caixa-valor" placeholder="Valor (R$)">
        <input type="text" id="caixa-obs" placeholder="Observação (opcional)" style="width:200px;">
        <button class="secundario" onclick="caixaAbrir()" id="btn-caixa-abrir">Abrir caixa</button>
        <button class="secundario" onclick="caixaFechar()" id="btn-caixa-fechar" style="display:none;">Fechar caixa</button>
        <button class="secundario" onclick="caixaSuprimento()" id="btn-caixa-suprimento" style="display:none;">Suprimento</button>
        <button class="secundario" onclick="caixaSangria()" id="btn-caixa-sangria" style="display:none;">Sangria</button>
        <span class="msg" id="msg-caixa"></span>
    </div>

    <div class="layout">
        <div class="coluna coluna-produtos">
            <div class="busca-wrap">
                <input class="busca" type="text" id="busca" placeholder="Buscar produto...">
            </div>
            <div class="secao-titulo">Produtos</div>
            <div class="grid-produtos" id="grid-produtos"></div>

            <div class="secao-titulo">Visitas / experiências agendadas</div>
            <div id="lista-agenda"></div>
        </div>

        <div class="coluna coluna-carrinho">
            <div class="carrinho-tabela-wrap">
                <table>
                    <thead><tr><th>Item</th><th style="width:50px;">Qtd</th><th style="width:90px;">Total</th><th style="width:32px;"></th></tr></thead>
                    <tbody id="carrinho"><tr><td colspan="4" style="color:var(--text-dim);">Carrinho vazio</td></tr></tbody>
                </table>
            </div>

            <div class="totais"><span>Total</span><span id="total">R$ 0,00</span></div>
            <div class="totais" id="linha-desconto" style="display:none; color:#2e7d32;"><span id="rotulo-desconto">Desconto</span><span id="valor-desconto">- R$ 0,00</span></div>
            <div class="totais" id="linha-total-final" style="display:none;"><span>Total a pagar</span><span id="total-final">R$ 0,00</span></div>

            <div class="linha">
                <div class="campo">
                    <label for="tipo-doc">Tipo de documento</label>
                    <select id="tipo-doc">
                        <option value="nao_fiscal">Não fiscal</option>
                        <option value="fiscal">NFC-e (fiscal)</option>
                    </select>
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label for="vendedor">Vendedor</label>
                    <select id="vendedor"><option value="">Sem vendedor (guia)</option></select>
                </div>
                <div class="campo">
                    <label for="atendente">Atendente *</label>
                    <select id="atendente"><option value="">Selecione o atendente</option></select>
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label for="cliente-nome">Cliente</label>
                    <input type="text" id="cliente-nome" placeholder="Nome (opcional)">
                </div>
                <div class="campo">
                    <label for="cliente-cpf">CPF/CNPJ</label>
                    <input type="text" id="cliente-cpf" placeholder="Opcional">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label for="forma-pagamento">Forma de pagamento *</label>
                    <select id="forma-pagamento"><option value="">Selecione a forma de pagamento</option></select>
                </div>
                <div class="campo">
                    <label for="cupom-visita">Cupom (visitação)</label>
                    <input type="text" id="cupom-visita" placeholder="Opcional - só vale para a visita">
                </div>
            </div>

            <div class="linha">
                <div class="campo">
                    <label for="desconto-pdv">Desconto</label>
                    <select id="desconto-pdv" onchange="renderizarCarrinho()"><option value="">Sem desconto</option></select>
                </div>
            </div>

            <button class="primario btn-finalizar" onclick="finalizarVenda()">Finalizar Venda (F10)</button>
            <p class="msg" id="msg-venda"></p>
        </div>
    </div>

    <div class="barra-atalhos">
        <span><kbd>F2</kbd> Verificar comprovante</span>
        <span><kbd>F10</kbd> Finalizar venda</span>
        <span><kbd>Esc</kbd> Fechar janela</span>
    </div>

    <div class="modal-overlay" id="modal-verificar-overlay">
        <div class="modal-verificar">
            <button type="button" class="modal-fechar" onclick="fecharModalVerificar()">&times;</button>
            <h2>Verificar comprovante</h2>
            <p class="modal-sub">Digite o número do pedido do comprovante do cliente e confirme se está válido.</p>

            <div class="busca-verificar">
                <input type="text" id="verificar-busca-pedido" placeholder="Número do pedido (ex.: 1234)" inputmode="numeric">
                <button class="primario" onclick="buscarTicketModal()">Buscar</button>
            </div>

            <p class="msg" id="verificar-msg"></p>

            <div id="verificar-resultado"></div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-vendedor-overlay">
        <div class="modal-verificar">
            <button type="button" class="modal-fechar" onclick="fecharModalVendedor()">&times;</button>
            <h2>Cadastrar vendedor</h2>
            <p class="modal-sub">Informe os dados do vendedor (guia).</p>

            <div class="campo" style="margin-bottom:12px;">
                <label for="novo-vendedor-nome">Nome *</label>
                <input type="text" id="novo-vendedor-nome" maxlength="255" style="width:100%;">
            </div>
            <div class="campo" style="margin-bottom:12px;">
                <label for="novo-vendedor-telefone">Celular *</label>
                <input type="text" id="novo-vendedor-telefone" maxlength="20" inputmode="tel" placeholder="(00) 00000-0000" style="width:100%;">
            </div>
            <div class="campo" style="margin-bottom:12px;">
                <label for="novo-vendedor-pix">Chave PIX *</label>
                <input type="text" id="novo-vendedor-pix" maxlength="77" placeholder="CPF, celular, e-mail ou chave aleatória" style="width:100%;">
            </div>

            <button class="primario" style="width:100%;" onclick="salvarNovoVendedor()">Salvar vendedor</button>
            <p class="msg" id="novo-vendedor-msg"></p>
        </div>
    </div>

    @if ($podeCancelarVenda)
    <div class="modal-overlay" id="modal-vendas-overlay">
        <div class="modal-verificar modal-vendas">
            <button type="button" class="modal-fechar" onclick="fecharModalVendas()">&times;</button>
            <h2>Vendas do dia</h2>
            <p class="modal-sub">Cancelar uma venda devolve o estoque, estorna o dinheiro no caixa e, se houver NFC-e autorizada, cancela a nota na SEFAZ. Fica registrado quem cancelou e o motivo.</p>
            <p class="msg" id="vendas-msg"></p>
            <table class="tabela-vendas">
                <thead><tr><th>Nº</th><th>Hora</th><th>Itens</th><th>Total</th><th>Pagamento</th><th>Nota</th><th></th></tr></thead>
                <tbody id="vendas-tbody"><tr><td colspan="7">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>
    @endif

    <script>
        const empresa = @json($empresaSlug);
        const pdvImpressaoDireta = @json($pdvImpressaoDireta);
        const base = `{{ url('/pdv') }}/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        let carrinho = []; // { tipo: 'produto'|'agenda', id, nome, quantidade, valorUnitario }

        let produtosCache = [];

        function escapeHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto ?? '';
            return div.innerHTML;
        }

        async function carregarProdutos(busca = '') {
            const resp = await fetch(`${base}/produtos?busca=${encodeURIComponent(busca)}`);
            produtosCache = await resp.json();
            document.getElementById('grid-produtos').innerHTML = produtosCache.map(p => {
                const temVariacoes = p.variacoes && p.variacoes.length > 0;
                const imagemHtml = p.imagem_url
                    ? `<img class="produto-img" src="${p.imagem_url}" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;produto-img produto-img-vazia&quot;>Sem foto</span>'">`
                    : '<span class="produto-img produto-img-vazia">Sem foto</span>';
                const minimoHtml = p.quantidade_minima_venda
                    ? `<span class="minimo">Venda mínima: ${p.quantidade_minima_venda} un. (pode misturar variações)</span>`
                    : '';

                if (p.kit) {
                    const fixosHtml = p.kit.fixos.map(f => `<span class="minimo">${f.quantidade}x ${escapeHtml(f.nome)}</span>`).join('');
                    const escolhasHtml = p.kit.escolhas.map((g, gi) => Array.from({ length: g.quantidade }, (_, n) => `
                        <select class="kit-escolha-${p.id}" data-grupo="${gi}">
                            <option value="">${escapeHtml(g.nome)} ${g.quantidade > 1 ? n + 1 : ''}</option>
                            ${g.variacoes.map(v => `<option value="${v.id}" data-rotulo="${escapeHtml(g.nome)} (${escapeHtml(v.tamanho)})" ${v.estoque_atual <= 0 ? 'disabled' : ''}>${escapeHtml(v.tamanho)}${v.estoque_atual <= 0 ? ' (sem estoque)' : ''}</option>`).join('')}
                        </select>`).join('')).join('');

                    return `
                        <div class="produto-btn">
                            ${imagemHtml}
                            <span class="nome">${escapeHtml(p.nome)} (kit)</span>
                            <span class="preco">R$ ${Number(p.preco_venda).toFixed(2)}</span>
                            ${fixosHtml}
                            ${escolhasHtml}
                            <button type="button" class="secundario" onclick="adicionarKit(${p.id})" ${p.kit.disponivel ? '' : 'disabled'}>
                                ${p.kit.disponivel ? 'Adicionar kit' : 'Sem estoque'}
                            </button>
                        </div>
                    `;
                }

                if (temVariacoes) {
                    const semEstoque = p.variacoes.every(v => v.estoque_atual <= 0);
                    const opcoes = p.variacoes.map(v => `
                        <option value="${v.id}" ${v.estoque_atual <= 0 ? 'disabled' : ''}>
                            ${escapeHtml(v.tamanho)}${v.estoque_atual <= 0 ? ' (sem estoque)' : ''}
                        </option>
                    `).join('');

                    return `
                        <div class="produto-btn">
                            ${imagemHtml}
                            <span class="nome">${escapeHtml(p.nome)}</span>
                            <span class="preco">R$ ${Number(p.preco_venda).toFixed(2)}</span>
                            ${minimoHtml}
                            <select id="variacao-${p.id}" onchange="atualizarBotaoVariacao(${p.id})" ${semEstoque ? 'disabled' : ''}>
                                <option value="">Escolha o tamanho</option>
                                ${opcoes}
                            </select>
                            <button type="button" class="secundario" id="botao-variacao-${p.id}" onclick="adicionarProdutoVariacao(${p.id})" disabled>
                                ${semEstoque ? 'Sem estoque' : 'Adicionar'}
                            </button>
                        </div>
                    `;
                }

                return `
                    <button class="produto-btn" onclick="adicionarProdutoPorId(${p.id})">
                        ${imagemHtml}
                        <span class="nome">${escapeHtml(p.nome)}</span>
                        <span class="preco">R$ ${Number(p.preco_venda).toFixed(2)}</span>
                        ${minimoHtml}
                    </button>
                `;
            }).join('') || '<p style="color:#9aa5b1;">Nenhum produto encontrado.</p>';
        }

        function adicionarProdutoPorId(id) {
            const p = produtosCache.find(x => x.id === id);
            if (!p) return;
            adicionarProduto(p.id, p.nome, Number(p.preco_venda));
        }

        // Kit: cada escolha do cliente vira uma linha própria no carrinho (não soma com outro kit
        // igual, pois os sabores podem ser diferentes). O preço é o do kit; a nota rateia entre os itens.
        function adicionarKit(produtoId) {
            const p = produtosCache.find(x => x.id === produtoId);
            if (!p) return;

            const selects = [...document.querySelectorAll(`.kit-escolha-${produtoId}`)];
            if (selects.some(s => !s.value)) {
                alert('Escolha todos os itens do kit antes de adicionar.');
                return;
            }

            const porVariacao = {};
            selects.forEach(s => { porVariacao[s.value] = (porVariacao[s.value] || 0) + 1; });
            const escolhas = Object.entries(porVariacao).map(([variacaoId, quantidade]) => ({ variacao_id: Number(variacaoId), quantidade }));
            const detalhes = selects.map(s => s.selectedOptions[0].dataset.rotulo).join(', ');

            carrinho.push({
                tipo: 'produto', id: p.id, variacaoId: null, tamanho: null, kit: true, escolhas,
                nome: `${p.nome} (kit: ${detalhes})`, quantidade: 1, valorUnitario: Number(p.preco_venda),
            });
            selects.forEach(s => { s.value = ''; });
            renderizarCarrinho();
        }

        function atualizarBotaoVariacao(produtoId) {
            const select = document.getElementById(`variacao-${produtoId}`);
            const botao = document.getElementById(`botao-variacao-${produtoId}`);
            botao.disabled = !select.value;
        }

        function adicionarProdutoVariacao(produtoId) {
            const p = produtosCache.find(x => x.id === produtoId);
            if (!p) return;

            const select = document.getElementById(`variacao-${produtoId}`);
            const variacaoId = Number(select.value);
            const variacao = p.variacoes.find(v => v.id === variacaoId);
            if (!variacao) return;

            adicionarProduto(p.id, `${p.nome} (${variacao.tamanho})`, Number(p.preco_venda), variacao.id, variacao.tamanho);

            select.value = '';
            atualizarBotaoVariacao(produtoId);
        }

        async function carregarAgenda() {
            const resp = await fetch(`${base}/visitas`);
            const horarios = await resp.json();
            document.getElementById('lista-agenda').innerHTML = horarios.map(a => `
                <div class="agenda-item">
                    ${new Date(a.data_hora).toLocaleString('pt-BR')} — ${a.vagas_disponiveis} vagas — R$ ${Number(a.valor_visita).toFixed(2)}
                    <button class="secundario" style="float:right;" onclick="adicionarAgenda(${a.id}, '${a.data_hora}', ${a.valor_visita})">+</button>
                </div>
            `).join('') || '<p style="color:#9aa5b1; font-size:12px;">Nenhum horário em aberto.</p>';
        }

        let vendedoresCache = [];
        let atendentesCache = [];

        async function carregarVendedores() {
            const resp = await fetch(`${base}/vendedores`);
            vendedoresCache = await resp.json();
            document.getElementById('vendedor').innerHTML = '<option value="">Sem vendedor (guia)</option>' +
                vendedoresCache.map(v => `<option value="${v.id}">${v.nome} (${v.percentual_comissao}%)</option>`).join('');
        }

        async function carregarAtendentes() {
            const resp = await fetch(`${base}/atendentes`);
            atendentesCache = await resp.json();
            document.getElementById('atendente').innerHTML = '<option value="">Sem atendente</option>' +
                atendentesCache.map(a => `<option value="${a.id}">${a.nome}</option>`).join('');
        }

        async function carregarFormasPagamento() {
            const resp = await fetch(`${base}/formas-pagamento`);
            const formas = await resp.json();
            document.getElementById('forma-pagamento').innerHTML = '<option value="">Forma de pagamento (opcional)</option>' +
                formas.map(f => `<option value="${f.id}">${f.descricao}</option>`).join('');
        }

        function adicionarProduto(id, nome, preco, variacaoId = null, tamanho = null) {
            const existente = carrinho.find(i =>
                i.tipo === 'produto' && i.id === id && (i.variacaoId ?? null) === (variacaoId ?? null)
            );
            if (existente) { existente.quantidade++; } else {
                carrinho.push({ tipo: 'produto', id, variacaoId, tamanho, nome, quantidade: 1, valorUnitario: preco });
            }
            renderizarCarrinho();
        }

        function adicionarAgenda(id, dataHora, valor) {
            carrinho = carrinho.filter(i => i.tipo !== 'agenda'); // só uma reserva por venda, por simplicidade
            carrinho.push({ tipo: 'agenda', id, nome: `Visita ${new Date(dataHora).toLocaleString('pt-BR')}`, quantidade: 1, valorUnitario: valor });
            renderizarCarrinho();
        }

        function removerItem(index) {
            carrinho.splice(index, 1);
            renderizarCarrinho();
        }

        function renderizarCarrinho() {
            const tbody = document.getElementById('carrinho');
            tbody.innerHTML = carrinho.map((item, i) => `
                <tr>
                    <td>${item.nome}</td>
                    <td>${item.quantidade}</td>
                    <td>R$ ${(item.quantidade * item.valorUnitario).toFixed(2)}</td>
                    <td><button class="rm" onclick="removerItem(${i})">✕</button></td>
                </tr>
            `).join('') || '<tr><td colspan="4" style="color:#9aa5b1;">Carrinho vazio</td></tr>';

            const total = carrinho.reduce((soma, item) => soma + item.quantidade * item.valorUnitario, 0);
            document.getElementById('total').textContent = `R$ ${total.toFixed(2)}`;

            // Prévia do desconto do PDV - o servidor recalcula tudo ao finalizar.
            const desconto = descontoSelecionado();
            const valorDesconto = desconto ? calcularDescontoPdv(desconto) : 0;
            document.getElementById('linha-desconto').style.display = valorDesconto > 0 ? '' : 'none';
            document.getElementById('linha-total-final').style.display = valorDesconto > 0 ? '' : 'none';
            if (valorDesconto > 0) {
                document.getElementById('rotulo-desconto').textContent = desconto.descricao;
                document.getElementById('valor-desconto').textContent = `- R$ ${valorDesconto.toFixed(2)}`;
                document.getElementById('total-final').textContent = `R$ ${(total - valorDesconto).toFixed(2)}`;
            }
        }

        let descontosPdvCache = [];

        function descontoSelecionado() {
            const id = Number(document.getElementById('desconto-pdv').value);
            return descontosPdvCache.find(d => d.id === id) || null;
        }

        // Mesma regra do servidor (DescontoPdv::calcular): produtos = todo o
        // valor dos produtos; visitas = no máximo 2 tickets por vez.
        function calcularDescontoPdv(desconto) {
            const percentual = Number(desconto.percentual) / 100;
            if (desconto.aplica_em === 'visitas') {
                const agenda = carrinho.find(i => i.tipo === 'agenda');
                if (!agenda) return 0;
                return Math.round(agenda.valorUnitario * Math.min(agenda.quantidade, 2) * percentual * 100) / 100;
            }
            const produtos = carrinho.filter(i => i.tipo === 'produto').reduce((s, i) => s + i.quantidade * i.valorUnitario, 0);
            return Math.round(produtos * percentual * 100) / 100;
        }

        async function carregarDescontosPdv() {
            const resp = await fetch(`${base}/descontos`);
            descontosPdvCache = await resp.json();
            document.getElementById('desconto-pdv').innerHTML = '<option value="">Sem desconto</option>' +
                descontosPdvCache.map(d => `<option value="${d.id}">${escapeHtml(d.descricao)} (${Number(d.percentual)}% - ${d.aplica_em === 'visitas' ? 'visitas' : 'produtos'})</option>`).join('');
        }

        // Soma a quantidade de todas as variações de um mesmo produto no
        // carrinho e confere contra produto.quantidade_minima_venda - o
        // cliente pode misturar variações livremente, só o total precisa
        // bater o mínimo (ex.: caixa fechada de 6 cervejas).
        function validarQuantidadeMinima() {
            const totalPorProduto = {};
            carrinho.filter(i => i.tipo === 'produto').forEach(i => {
                totalPorProduto[i.id] = (totalPorProduto[i.id] || 0) + i.quantidade;
            });

            for (const produtoId in totalPorProduto) {
                const p = produtosCache.find(x => x.id === Number(produtoId));
                if (p && p.quantidade_minima_venda && totalPorProduto[produtoId] < p.quantidade_minima_venda) {
                    return `A venda mínima de "${p.nome}" é ${p.quantidade_minima_venda} unidades (pode misturar as variações) - há apenas ${totalPorProduto[produtoId]} no carrinho.`;
                }
            }
            return null;
        }

        async function finalizarVenda() {
            const msg = document.getElementById('msg-venda');
            const btn = document.querySelector('.btn-finalizar');

            if (!carrinho.length) {
                msg.className = 'msg erro';
                msg.textContent = 'Adicione ao menos um produto ou uma visita antes de finalizar.';
                return;
            }

            if (!document.getElementById('atendente').value) {
                msg.className = 'msg erro';
                msg.textContent = 'Selecione o atendente antes de finalizar.';
                return;
            }

            if (!document.getElementById('forma-pagamento').value) {
                msg.className = 'msg erro';
                msg.textContent = 'Selecione a forma de pagamento antes de finalizar.';
                return;
            }

            const descontoPdv = descontoSelecionado();
            if (descontoPdv && calcularDescontoPdv(descontoPdv) <= 0) {
                msg.className = 'msg erro';
                msg.textContent = descontoPdv.aplica_em === 'visitas'
                    ? 'Este desconto só vale para visitas - adicione uma visita à venda ou remova o desconto.'
                    : 'Este desconto só vale para produtos - adicione produtos à venda ou remova o desconto.';
                return;
            }
            if (descontoPdv && descontoPdv.aplica_em === 'visitas' && document.getElementById('cupom-visita').value) {
                msg.className = 'msg erro';
                msg.textContent = 'O desconto em visitas não pode ser usado junto com cupom. Escolha um dos dois.';
                return;
            }

            const erroMinimo = validarQuantidadeMinima();
            if (erroMinimo) {
                msg.className = 'msg erro';
                msg.textContent = erroMinimo;
                return;
            }

            const agendaItem = carrinho.find(i => i.tipo === 'agenda');
            const dados = {
                tipo_doc: document.getElementById('tipo-doc').value,
                vendedor_id: document.getElementById('vendedor').value || null,
                atendente_id: document.getElementById('atendente').value,
                forma_pagamento_id: document.getElementById('forma-pagamento').value,
                cliente: {
                    nome: document.getElementById('cliente-nome').value || null,
                    cpf_cnpj: document.getElementById('cliente-cpf').value || null,
                },
                itens: carrinho.filter(i => i.tipo === 'produto').map(i => ({ produto_id: i.id, variacao_id: i.variacaoId || null, quantidade: i.quantidade, ...(i.kit ? { escolhas: i.escolhas } : {}) })),
                agenda_visitacao_id: agendaItem ? agendaItem.id : null,
                agenda_quantidade: agendaItem ? agendaItem.quantidade : null,
                cupom_codigo: agendaItem ? (document.getElementById('cupom-visita').value || null) : null,
                desconto_pdv_id: descontoPdv ? descontoPdv.id : null,
            };

            btn.disabled = true;
            msg.className = 'msg'; msg.textContent = 'Processando venda...';

            let resp, resposta;
            try {
                resp = await fetch(`${base}/vendas`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
                resposta = await resp.json();
            } catch (erro) {
                btn.disabled = false;
                msg.className = 'msg erro';
                msg.textContent = 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.';
                console.error('Falha ao finalizar venda:', erro);
                return;
            }

            btn.disabled = false;

            if (!resp.ok) {
                msg.className = 'msg erro';
                msg.textContent = resposta.message || (resposta.errors ? Object.values(resposta.errors).flat().join(' ') : 'Erro ao finalizar a venda.');
                return;
            }

            msg.className = 'msg ok';
            msg.textContent = `Venda #${resposta.id} finalizada - total R$ ${Number(resposta.valor_total).toFixed(2)}.`;

            imprimirCupom(resposta);

            carrinho = [];
            renderizarCarrinho();
            document.getElementById('cliente-nome').value = '';
            document.getElementById('cliente-cpf').value = '';
            document.getElementById('cupom-visita').value = '';
            document.getElementById('desconto-pdv').value = '';
            renderizarCarrinho();
            carregarAgenda();
        }

        function formatarChaveAcesso(chave) {
            if (!chave) { return ''; }
            return chave.replace(/(\d{4})(?=\d)/g, '$1 ');
        }

        function imprimirCupom(venda) {
            const empresa = venda.empresa || {};
            const doc = venda.documento_fiscal;
            const dataVenda = new Date(venda.data_venda || Date.now());
            const linhaItens = (venda.itens || []).map(item => {
                const nome = item.produto
                    ? item.produto.nome + (item.produto_variacao ? ` (${item.produto_variacao.tamanho})` : '')
                    : (item.agenda_visitacao ? `Visita agendada` : 'Item');
                const qtd = Number(item.quantidade);
                const unit = Number(item.valor_unitario);
                const total = Number(item.valor_total);
                return `
                    <tr>
                        <td colspan="3" style="padding-top:6px;">${nome}</td>
                    </tr>
                    <tr>
                        <td>${qtd} x ${unit.toFixed(2)}</td>
                        <td></td>
                        <td style="text-align:right;">${total.toFixed(2)}</td>
                    </tr>`;
            }).join('');

            const enderecoEmpresa = [empresa.logradouro, empresa.numero, empresa.bairro, empresa.municipio, empresa.uf]
                .filter(Boolean).join(', ');

            const blocoFiscal = doc ? `
                <div class="sep"></div>
                <div class="centro"><strong>DOCUMENTO AUXILIAR DA NFC-e</strong></div>
                <div class="centro">Não permite aproveitamento de crédito de ICMS</div>
                <div class="sep"></div>
                <div>Modelo: ${doc.modelo} &nbsp; Série: ${doc.serie} &nbsp; Número: ${doc.numero}</div>
                <div>Ambiente: ${doc.ambiente === 'producao' ? 'Produção' : 'Homologação'}</div>
                <div>Status: ${doc.status === 'autorizada' ? 'Autorizada' : doc.status}</div>
                <div style="word-break:break-all; margin-top:4px;">Chave de acesso:<br>${formatarChaveAcesso(doc.chave_acesso)}</div>
                <div style="margin-top:2px;">Protocolo: ${doc.protocolo_autorizacao || '-'}</div>
                <div class="centro" style="margin-top:6px; font-size:10px;">Consulte pela chave de acesso no site da Sefaz</div>
            ` : `
                <div class="sep"></div>
                <div class="centro"><strong>DOCUMENTO NÃO FISCAL</strong></div>
                <div class="centro" style="font-size:10px;">Não possui valor fiscal</div>
            `;

            const janela = window.open('', 'cupom', 'width=380,height=640');
            janela.document.write(`
                <!doctype html>
                <html lang="pt-BR"><head><meta charset="utf-8"><title>Cupom - Venda #${venda.id}</title>
                <style>
                    @page { size: 80mm auto; margin: 0; }
                    * { box-sizing: border-box; }
                    html, body { width: 80mm; }
                    body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; margin: 0 auto; padding: 12px; }
                    .centro { text-align: center; }
                    .sep { border-top: 1px dashed #000; margin: 8px 0; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    td { padding: 1px 0; }
                    .totais-cupom { display:flex; justify-content:space-between; font-weight:bold; font-size:14px; margin-top:8px; }
                    .btn-imprimir { display:block; width:100%; margin-top:10px; padding:8px; font-size:13px; cursor:pointer; }
                    @media print {
                        .btn-imprimir { display:none; }
                        html, body { width: 80mm; }
                    }
                </style>
                </head><body${pdvImpressaoDireta ? ' onload="window.print()"' : ''}>
                    <div class="centro"><strong>${empresa.razao_social || 'Empresa'}</strong></div>
                    ${empresa.cnpj ? `<div class="centro">CNPJ: ${empresa.cnpj}</div>` : ''}
                    ${enderecoEmpresa ? `<div class="centro">${enderecoEmpresa}</div>` : ''}
                    <div class="sep"></div>
                    <div>Venda: #${venda.id}</div>
                    <div>Data: ${dataVenda.toLocaleString('pt-BR')}</div>
                    ${venda.vendedor ? `<div>Vendedor: ${venda.vendedor.nome}</div>` : ''}
                    ${venda.atendente ? `<div>Atendente: ${venda.atendente.nome}</div>` : ''}
                    ${venda.cliente ? `<div>Cliente: ${venda.cliente.nome}</div>` : ''}
                    <div class="sep"></div>
                    <table><tbody>${linhaItens}</tbody></table>
                    <div class="sep"></div>
                    ${Number(venda.valor_desconto) > 0 ? `<div class="totais-cupom" style="font-weight:normal;"><span>Desconto${venda.desconto_pdv ? ' - ' + venda.desconto_pdv.descricao : ''}</span><span>- R$ ${Number(venda.valor_desconto).toFixed(2)}</span></div>` : ''}
                    <div class="totais-cupom"><span>TOTAL</span><span>R$ ${Number(venda.valor_total).toFixed(2)}</span></div>
                    ${venda.forma_pagamento ? `<div>Forma de pagamento: ${venda.forma_pagamento.descricao}</div>` : ''}
                    ${blocoFiscal}
                    <div class="sep"></div>
                    <div class="centro" style="font-size:10px;">Obrigado pela preferência!</div>
                    ${pdvImpressaoDireta ? '' : '<button class="btn-imprimir" onclick="window.print()">Imprimir cupom</button>'}
                </body></html>
            `);
            janela.document.close();
        }

        async function caixaAtualizarStatus() {
            const resp = await fetch(`${base}/caixa-status`);
            const dados = await resp.json();
            const texto = document.getElementById('caixa-status-texto');
            const wrap = document.getElementById('caixa-status-wrap');
            const aberto = dados.status === 'aberto';

            texto.textContent = aberto
                ? `Caixa aberto - saldo atual: R$ ${Number(dados.saldo).toFixed(2)}`
                : 'Caixa fechado - abra o caixa antes de vender.';
            wrap.classList.toggle('aberto', aberto);
            wrap.classList.toggle('fechado', !aberto);

            document.getElementById('btn-caixa-abrir').style.display = aberto ? 'none' : 'inline-block';
            document.getElementById('btn-caixa-fechar').style.display = aberto ? 'inline-block' : 'none';
            document.getElementById('btn-caixa-suprimento').style.display = aberto ? 'inline-block' : 'none';
            document.getElementById('btn-caixa-sangria').style.display = aberto ? 'inline-block' : 'none';
        }

        async function caixaAcao(endpoint, exigeObservacao) {
            const valor = Number(document.getElementById('caixa-valor').value);
            const observacao = document.getElementById('caixa-obs').value || null;
            const msg = document.getElementById('msg-caixa');

            const resp = await fetch(`${base}/${endpoint}`, {
                method: 'POST', headers: headersJson, body: JSON.stringify({ valor, observacao }),
            });
            const resposta = await resp.json();

            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || 'Erro na operação de caixa.'; return; }

            msg.className = 'msg ok'; msg.textContent = 'Operação registrada.';
            document.getElementById('caixa-valor').value = '';
            document.getElementById('caixa-obs').value = '';
            caixaAtualizarStatus();
        }

        function caixaAbrir() { caixaAcao('caixa-abrir'); }
        function caixaFechar() { caixaAcao('caixa-fechar'); }
        function caixaSuprimento() { caixaAcao('caixa-suprimento'); }
        function caixaSangria() { caixaAcao('caixa-sangria'); }

        // Verificação de comprovante (F2)
        function formatarDataHoraV(iso) { return new Date(iso).toLocaleString('pt-BR'); }

        function abrirModalVerificar() {
            document.getElementById('modal-verificar-overlay').classList.add('aberto');
            document.getElementById('verificar-msg').textContent = '';
            document.getElementById('verificar-msg').className = 'msg';
            document.getElementById('verificar-resultado').innerHTML = '';
            const input = document.getElementById('verificar-busca-pedido');
            input.value = '';
            input.focus();
        }

        function fecharModalVerificar() {
            document.getElementById('modal-verificar-overlay').classList.remove('aberto');
        }

        function mostrarStatusVerificar(valido, titulo, detalhe) {
            const iconeValido = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            const iconeInvalido = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            return `
                <div class="resultado-verificar">
                    <div class="icone-status ${valido ? 'valido' : 'invalido'}">${valido ? iconeValido : iconeInvalido}</div>
                    <p class="resultado-titulo ${valido ? 'valido' : 'invalido'}">${titulo}</p>
                    ${detalhe ? `<p class="resultado-detalhe">${detalhe}</p>` : ''}
                </div>
            `;
        }

        document.getElementById('verificar-busca-pedido').addEventListener('keydown', (e) => { if (e.key === 'Enter') buscarTicketModal(); });

        async function buscarTicketModal() {
            const id = document.getElementById('verificar-busca-pedido').value.trim();
            const msg = document.getElementById('verificar-msg');
            const resultadoDiv = document.getElementById('verificar-resultado');
            resultadoDiv.innerHTML = '';
            msg.className = 'msg'; msg.textContent = '';

            if (!id) { msg.className = 'msg erro'; msg.textContent = 'Informe o número do pedido.'; return; }

            msg.textContent = 'Buscando...';

            const resp = await fetch(`${base}/verificar/${id}`);
            if (!resp.ok) {
                const resposta = await resp.json().catch(() => ({}));
                msg.textContent = '';
                resultadoDiv.innerHTML = mostrarStatusVerificar(false, 'Comprovante inválido', resposta.message || 'Pedido não encontrado.');
                return;
            }

            msg.textContent = '';
            renderizarVerificar(await resp.json());
        }

        function renderizarVerificar(venda) {
            const pago = venda.status_pagamento === 'pago';
            const jaTeveCheckin = !!venda.check_in_em;
            const valido = pago;

            const itensProduto = (venda.itens || []).filter(i => i.produto);
            const itensVisita = (venda.itens || []).filter(i => i.agenda_visitacao);

            let tituloStatus, detalheStatus;
            if (!pago) {
                tituloStatus = 'Comprovante inválido';
                detalheStatus = `Pedido #${venda.id} ainda não está pago.`;
            } else if (jaTeveCheckin) {
                tituloStatus = 'Entrada já confirmada';
                detalheStatus = `Confirmado em ${formatarDataHoraV(venda.check_in_em)}${venda.check_in_usuario ? ' por ' + venda.check_in_usuario.name : ''}.` +
                    (venda.atendente ? ` Atendente: ${venda.atendente.nome}.` : '') +
                    (venda.vendedor ? ` Vendedor: ${venda.vendedor.nome}.` : '');
            } else {
                tituloStatus = 'Comprovante válido';
                detalheStatus = `Pedido #${venda.id} pago${venda.cliente ? ' - Cliente: ' + venda.cliente.nome : ''}.`;
            }

            const itensHtml = `
                ${itensProduto.length ? '<div class="titulo-secao-v">Produtos</div>' + itensProduto.map(i => `
                    <div class="linha-item-v">
                        <span>${i.quantidade}x ${i.produto?.nome ?? ''}${i.produto_variacao ? ' (' + i.produto_variacao.tamanho + ')' : ''}</span>
                        <span>R$ ${Number(i.valor_total).toFixed(2)}</span>
                    </div>
                `).join('') : ''}
                ${itensVisita.length ? '<div class="titulo-secao-v">Visitas agendadas</div>' + itensVisita.map(i => `
                    <div class="linha-item-v">
                        <span>${i.quantidade}x visita${i.agenda_visitacao ? ' - ' + formatarDataHoraV(i.agenda_visitacao.data_hora) : ''}</span>
                        <span>R$ ${Number(i.valor_total).toFixed(2)}</span>
                    </div>
                `).join('') : ''}
            `;

            document.getElementById('verificar-resultado').innerHTML = `
                ${mostrarStatusVerificar(valido, tituloStatus, detalheStatus)}
                <div class="card-venda">
                    ${itensHtml}
                    <div class="rodape-total-v">
                        <span>Total</span>
                        <span>R$ ${Number(venda.valor_total).toFixed(2)}</span>
                    </div>
                    ${!jaTeveCheckin && pago ? `
                        <div class="titulo-secao-v" style="margin-top:14px;">Atendimento</div>
                        <div class="campo" style="margin-bottom:8px;">
                            <label for="verificar-vendedor">Vendedor</label>
                            <select id="verificar-vendedor" style="width:100%;">
                                <option value="">Sem vendedor (guia)</option>
                                ${vendedoresCache.map(v => `<option value="${v.id}">${v.nome} (${v.percentual_comissao}%)</option>`).join('')}
                            </select>
                        </div>
                        <div class="campo" style="margin-bottom:4px;">
                            <label for="verificar-atendente">Atendente *</label>
                            <select id="verificar-atendente" style="width:100%;">
                                <option value="">Selecione o atendente</option>
                                ${atendentesCache.map(a => `<option value="${a.id}">${a.nome}</option>`).join('')}
                            </select>
                        </div>
                        <button class="primario" style="width:100%; margin-top:10px;" onclick="confirmarEntradaModal(${venda.id})">Confirmar entrada</button>
                    ` : ''}
                </div>
            `;
        }

        async function confirmarEntradaModal(id) {
            const msg = document.getElementById('verificar-msg');
            const atendenteId = document.getElementById('verificar-atendente').value;
            const vendedorId = document.getElementById('verificar-vendedor').value;

            if (!atendenteId) {
                msg.className = 'msg erro';
                msg.textContent = 'Selecione o atendente antes de confirmar a entrada.';
                return;
            }

            const resp = await fetch(`${base}/verificar/${id}/check-in`, {
                method: 'POST',
                headers: headersJson,
                body: JSON.stringify({ atendente_id: Number(atendenteId), vendedor_id: vendedorId ? Number(vendedorId) : null }),
            });
            const resposta = await resp.json();

            if (!resp.ok) {
                msg.className = 'msg erro';
                msg.textContent = resposta.message || 'Não foi possível confirmar a entrada.';
                return;
            }

            msg.className = 'msg ok';
            msg.textContent = 'Entrada confirmada com sucesso!';
            renderizarVerificar(resposta);
        }

        // Cadastro rápido de vendedor (comissão fixa de 5% definida no servidor)
        function abrirModalVendedor() {
            ['nome', 'telefone', 'pix'].forEach(c => { document.getElementById(`novo-vendedor-${c}`).value = ''; });
            const msg = document.getElementById('novo-vendedor-msg');
            msg.className = 'msg'; msg.textContent = '';
            document.getElementById('modal-vendedor-overlay').classList.add('aberto');
            document.getElementById('novo-vendedor-nome').focus();
        }

        function fecharModalVendedor() {
            document.getElementById('modal-vendedor-overlay').classList.remove('aberto');
        }

        async function salvarNovoVendedor() {
            const msg = document.getElementById('novo-vendedor-msg');
            const nome = document.getElementById('novo-vendedor-nome').value.trim();
            const telefone = document.getElementById('novo-vendedor-telefone').value.trim();
            const chavePix = document.getElementById('novo-vendedor-pix').value.trim();

            if (!nome || !telefone || !chavePix) {
                msg.className = 'msg erro';
                msg.textContent = 'Preencha nome, celular e chave PIX.';
                return;
            }

            const resp = await fetch(`${base}/vendedores`, {
                method: 'POST', headers: headersJson,
                body: JSON.stringify({ nome, telefone, chave_pix: chavePix }),
            });
            const resposta = await resp.json();

            if (!resp.ok) {
                msg.className = 'msg erro';
                msg.textContent = resposta.message || 'Não foi possível cadastrar o vendedor.';
                return;
            }

            await carregarVendedores();
            document.getElementById('vendedor').value = resposta.id;
            msg.className = 'msg ok';
            msg.textContent = `Vendedor ${resposta.nome} cadastrado e selecionado na venda.`;
            setTimeout(fecharModalVendedor, 900);
        }

        @if ($podeCancelarVenda)
        // Vendas do dia / cancelamento (somente administrador - o servidor também confere o perfil)
        function abrirModalVendas() {
            document.getElementById('modal-vendas-overlay').classList.add('aberto');
            carregarVendasDoDia();
        }

        function fecharModalVendas() {
            document.getElementById('modal-vendas-overlay').classList.remove('aberto');
        }

        function rotuloNota(doc) {
            if (!doc) return 'Sem nota';
            const nomes = { autorizada: 'autorizada', cancelada: 'cancelada', rejeitada: 'rejeitada', contingencia: 'contingência' };
            return `${doc.modelo === 55 ? 'NF-e' : 'NFC-e'} ${doc.numero} (${nomes[doc.status] || doc.status})`;
        }

        async function carregarVendasDoDia() {
            const tbody = document.getElementById('vendas-tbody');
            const resp = await fetch(`${base}/vendas-do-dia`, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) { tbody.innerHTML = '<tr><td colspan="7">Não foi possível carregar as vendas.</td></tr>'; return; }
            const vendas = await resp.json();
            if (!vendas.length) { tbody.innerHTML = '<tr><td colspan="7">Nenhuma venda hoje.</td></tr>'; return; }

            tbody.innerHTML = vendas.map(v => `
                <tr class="${v.cancelada ? 'cancelada' : ''}">
                    <td>#${v.id}</td>
                    <td>${new Date(v.data_venda).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</td>
                    <td>${escapeHtml(v.itens.join(', '))}</td>
                    <td>R$ ${Number(v.valor_total).toFixed(2)}</td>
                    <td>${escapeHtml(v.forma_pagamento || '-')}</td>
                    <td>${escapeHtml(rotuloNota(v.documento))}</td>
                    <td class="sem-risco">${v.cancelada
                        ? `<span class="tag">Cancelada</span><span class="motivo">${escapeHtml(v.cancelada_por || '')} · ${escapeHtml(v.motivo_cancelamento || '')}</span>`
                        : `<button class="secundario" onclick="cancelarVendaPdv(${v.id})">Cancelar</button>`}</td>
                </tr>
            `).join('');
        }

        async function cancelarVendaPdv(id) {
            const msg = document.getElementById('vendas-msg');
            const motivo = prompt(`Cancelar a venda #${id}?\n\nInforme o motivo (mín. 15 caracteres):`);
            if (!motivo) return;

            msg.className = 'msg'; msg.textContent = 'Cancelando...';
            const resp = await fetch(`${base}/vendas/${id}/cancelar`, {
                method: 'POST', headers: headersJson, body: JSON.stringify({ motivo }),
            });
            const resposta = await resp.json();

            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || 'Não foi possível cancelar a venda.'; return; }

            msg.className = 'msg ok'; msg.textContent = `Venda #${id} cancelada.`;
            carregarVendasDoDia();
            caixaAtualizarStatus();
            carregarProdutos(document.getElementById('busca').value);
            carregarAgenda();
        }
        @endif

        document.getElementById('busca').addEventListener('input', (e) => carregarProdutos(e.target.value));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F10') { e.preventDefault(); finalizarVenda(); }
            if (e.key === 'F2') { e.preventDefault(); abrirModalVerificar(); }
            if (e.key === 'Escape' && document.getElementById('modal-verificar-overlay').classList.contains('aberto')) { fecharModalVerificar(); }
            if (e.key === 'Escape' && document.getElementById('modal-vendedor-overlay').classList.contains('aberto')) { fecharModalVendedor(); }
            @if ($podeCancelarVenda)
            if (e.key === 'Escape' && document.getElementById('modal-vendas-overlay').classList.contains('aberto')) { fecharModalVendas(); }
            @endif
        });

        carregarProdutos();
        carregarAgenda();
        carregarVendedores();
        carregarAtendentes();
        carregarFormasPagamento();
        carregarDescontosPdv();
        caixaAtualizarStatus();
    </script>
</body>
</html>
