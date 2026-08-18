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
            border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 6px;
            transition: border-color .15s, transform .1s;
        }
        .produto-btn:hover { border-color: var(--accent); transform: translateY(-1px); }
        .produto-btn:active { transform: translateY(0); }
        .produto-btn .nome { font-size: 12.5px; line-height: 1.35; }
        .produto-btn .preco { font-size: 14px; font-weight: 700; color: var(--accent); }

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

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
    </style>
</head>
<body>
    <div class="topo">
        <div class="topo-marca">
            <img src="/images/logo.jpg" alt="Logo">
            <h1>PDV — Frente de Caixa <span>· {{ $empresaSlug }}</span></h1>
        </div>
        <form method="POST" action="/logout" class="topo-usuario">
            @csrf
            <span>{{ auth()->user()->name }}</span>
            <button type="submit" class="secundario">Sair</button>
        </form>
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
                    <label for="atendente">Atendente</label>
                    <select id="atendente"><option value="">Sem atendente</option></select>
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
                    <label for="forma-pagamento">Forma de pagamento</label>
                    <select id="forma-pagamento"><option value="">Selecione (opcional)</option></select>
                </div>
            </div>

            <button class="primario btn-finalizar" onclick="finalizarVenda()">Finalizar Venda (F10)</button>
            <p class="msg" id="msg-venda"></p>
        </div>
    </div>

    <script>
        const empresa = @json($empresaSlug);
        const pdvImpressaoDireta = @json($pdvImpressaoDireta);
        const base = `/pdv/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        let carrinho = []; // { tipo: 'produto'|'agenda', id, nome, quantidade, valorUnitario }

        async function carregarProdutos(busca = '') {
            const resp = await fetch(`${base}/produtos?busca=${encodeURIComponent(busca)}`);
            const produtos = await resp.json();
            document.getElementById('grid-produtos').innerHTML = produtos.map(p => `
                <button class="produto-btn" onclick="adicionarProduto(${p.id}, '${p.nome.replace(/'/g, "\\'")}', ${p.preco_venda})">
                    <span class="nome">${p.nome}</span>
                    <span class="preco">R$ ${Number(p.preco_venda).toFixed(2)}</span>
                </button>
            `).join('') || '<p style="color:#9aa5b1;">Nenhum produto encontrado.</p>';
        }

        async function carregarAgenda() {
            const resp = await fetch(`${base}/agenda`);
            const horarios = await resp.json();
            document.getElementById('lista-agenda').innerHTML = horarios.map(a => `
                <div class="agenda-item">
                    ${new Date(a.data_hora).toLocaleString('pt-BR')} — ${a.vagas_disponiveis} vagas — R$ ${Number(a.valor_visita).toFixed(2)}
                    <button class="secundario" style="float:right;" onclick="adicionarAgenda(${a.id}, '${a.data_hora}', ${a.valor_visita})">+</button>
                </div>
            `).join('') || '<p style="color:#9aa5b1; font-size:12px;">Nenhum horário em aberto.</p>';
        }

        async function carregarVendedores() {
            const resp = await fetch(`${base}/vendedores`);
            const vendedores = await resp.json();
            document.getElementById('vendedor').innerHTML = '<option value="">Sem vendedor (guia)</option>' +
                vendedores.map(v => `<option value="${v.id}">${v.nome} (${v.percentual_comissao}%)</option>`).join('');
        }

        async function carregarAtendentes() {
            const resp = await fetch(`${base}/atendentes`);
            const atendentes = await resp.json();
            document.getElementById('atendente').innerHTML = '<option value="">Sem atendente</option>' +
                atendentes.map(a => `<option value="${a.id}">${a.nome}</option>`).join('');
        }

        async function carregarFormasPagamento() {
            const resp = await fetch(`${base}/formas-pagamento`);
            const formas = await resp.json();
            document.getElementById('forma-pagamento').innerHTML = '<option value="">Forma de pagamento (opcional)</option>' +
                formas.map(f => `<option value="${f.id}">${f.descricao}</option>`).join('');
        }

        function adicionarProduto(id, nome, preco) {
            const existente = carrinho.find(i => i.tipo === 'produto' && i.id === id);
            if (existente) { existente.quantidade++; } else {
                carrinho.push({ tipo: 'produto', id, nome, quantidade: 1, valorUnitario: preco });
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
        }

        async function finalizarVenda() {
            const msg = document.getElementById('msg-venda');
            const btn = document.querySelector('.btn-finalizar');

            if (!carrinho.length) {
                msg.className = 'msg erro';
                msg.textContent = 'Adicione ao menos um produto ou uma visita antes de finalizar.';
                return;
            }

            const agendaItem = carrinho.find(i => i.tipo === 'agenda');
            const dados = {
                tipo_doc: document.getElementById('tipo-doc').value,
                vendedor_id: document.getElementById('vendedor').value || null,
                atendente_id: document.getElementById('atendente').value || null,
                forma_pagamento_id: document.getElementById('forma-pagamento').value || null,
                cliente: {
                    nome: document.getElementById('cliente-nome').value || null,
                    cpf_cnpj: document.getElementById('cliente-cpf').value || null,
                },
                itens: carrinho.filter(i => i.tipo === 'produto').map(i => ({ produto_id: i.id, quantidade: i.quantidade })),
                agenda_visitacao_id: agendaItem ? agendaItem.id : null,
                agenda_quantidade: agendaItem ? agendaItem.quantidade : null,
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
                const nome = item.produto ? item.produto.nome : (item.agenda_visitacao ? `Visita agendada` : 'Item');
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

        document.getElementById('busca').addEventListener('input', (e) => carregarProdutos(e.target.value));
        document.addEventListener('keydown', (e) => { if (e.key === 'F10') { e.preventDefault(); finalizarVenda(); } });

        carregarProdutos();
        carregarAgenda();
        carregarVendedores();
        carregarAtendentes();
        carregarFormasPagamento();
        caixaAtualizarStatus();
    </script>
</body>
</html>
