<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PDV — Frente de Caixa</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #17202a; margin: 0; padding: 16px; color: #e4e7eb; }
        .topo { display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; }
        h1 { font-size: 16px; margin: 0; }
        .layout { display: grid; grid-template-columns: 1fr 360px; gap: 16px; }
        .card { background: #1f2933; border-radius: 8px; padding: 16px; }
        input, select, button { font-size: 13px; padding: 8px 10px; border-radius: 4px; border: 1px solid #3e4c59; background: #323f4b; color: #e4e7eb; }
        input::placeholder { color: #9aa5b1; }
        button { cursor: pointer; }
        button.primario { background: #f0a020; color: #17202a; border: none; font-weight: 600; }
        button.secundario { background: #616e7c; color: #fff; border: none; }
        .busca { width: 100%; margin-bottom: 12px; }
        .grid-produtos { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
        .produto-btn { text-align: left; background: #2c3947; border: 1px solid #3e4c59; border-radius: 6px; padding: 10px; }
        .produto-btn:hover { border-color: #f0a020; }
        .produto-btn .nome { font-size: 12px; display: block; }
        .produto-btn .preco { font-size: 13px; font-weight: 600; color: #f0a020; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td, th { padding: 6px 4px; border-bottom: 1px solid #3e4c59; }
        .totais { display:flex; justify-content:space-between; font-size: 20px; font-weight: 700; margin: 10px 0; }
        .linha { display:flex; gap:8px; margin-bottom:8px; }
        .linha > * { flex: 1; }
        .msg { font-size: 12px; margin-top: 8px; }
        .msg.erro { color: #ff8080; }
        .msg.ok { color: #6cd67a; }
        .agenda-item { background: #2c3947; border: 1px solid #3e4c59; border-radius: 6px; padding: 8px; margin-bottom: 6px; font-size: 12px; }
        .rm { background: transparent; border: none; color: #ff8080; cursor: pointer; }
    </style>
</head>
<body>
    <div class="topo">
        <h1>PDV — Frente de Caixa — {{ $empresaSlug }}</h1>
        <form method="POST" action="/logout">
            @csrf
            <span style="font-size:12px; color:#9aa5b1; margin-right:8px;">{{ auth()->user()->name }}</span>
            <button type="submit" class="secundario">Sair</button>
        </form>
    </div>

    <div class="layout">
        <div class="card">
            <input class="busca" type="text" id="busca" placeholder="Buscar produto...">
            <div class="grid-produtos" id="grid-produtos"></div>

            <h3 style="margin-top:20px; font-size:13px;">Visitas / experiências agendadas</h3>
            <div id="lista-agenda"></div>
        </div>

        <div class="card">
            <table>
                <thead><tr><th>Item</th><th>Qtd</th><th>Total</th><th></th></tr></thead>
                <tbody id="carrinho"><tr><td colspan="4" style="color:#9aa5b1;">Carrinho vazio</td></tr></tbody>
            </table>

            <div class="totais"><span>Total</span><span id="total">R$ 0,00</span></div>

            <div class="linha">
                <select id="tipo-doc">
                    <option value="nao_fiscal">Não fiscal</option>
                    <option value="fiscal">NFC-e (fiscal)</option>
                </select>
                <select id="vendedor"><option value="">Sem vendedor</option></select>
            </div>

            <div class="linha">
                <input type="text" id="cliente-nome" placeholder="Cliente (opcional)">
                <input type="text" id="cliente-cpf" placeholder="CPF/CNPJ (opcional)">
            </div>

            <button class="primario" style="width:100%; padding:12px;" onclick="finalizarVenda()">Finalizar Venda (F10)</button>
            <p class="msg" id="msg-venda"></p>
        </div>
    </div>

    <script>
        const empresa = @json($empresaSlug);
        const base = `/pdv/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken };

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
            document.getElementById('vendedor').innerHTML = '<option value="">Sem vendedor</option>' +
                vendedores.map(v => `<option value="${v.id}">${v.nome} (${v.percentual_comissao}%)</option>`).join('');
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
            if (!carrinho.length) { return; }

            const agendaItem = carrinho.find(i => i.tipo === 'agenda');
            const dados = {
                tipo_doc: document.getElementById('tipo-doc').value,
                vendedor_id: document.getElementById('vendedor').value || null,
                cliente: {
                    nome: document.getElementById('cliente-nome').value || null,
                    cpf_cnpj: document.getElementById('cliente-cpf').value || null,
                },
                itens: carrinho.filter(i => i.tipo === 'produto').map(i => ({ produto_id: i.id, quantidade: i.quantidade })),
                agenda_visitacao_id: agendaItem ? agendaItem.id : null,
                agenda_quantidade: agendaItem ? agendaItem.quantidade : null,
            };

            const resp = await fetch(`${base}/vendas`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-venda');

            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }

            msg.className = 'msg ok';
            msg.textContent = `Venda #${resposta.id} finalizada - total R$ ${Number(resposta.valor_total).toFixed(2)}.`;
            carrinho = [];
            renderizarCarrinho();
            document.getElementById('cliente-nome').value = '';
            document.getElementById('cliente-cpf').value = '';
            carregarAgenda();
        }

        document.getElementById('busca').addEventListener('input', (e) => carregarProdutos(e.target.value));
        document.addEventListener('keydown', (e) => { if (e.key === 'F10') { e.preventDefault(); finalizarVenda(); } });

        carregarProdutos();
        carregarAgenda();
        carregarVendedores();
    </script>
</body>
</html>
