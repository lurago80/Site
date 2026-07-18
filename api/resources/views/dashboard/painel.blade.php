<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard — {{ $empresaSlug }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f5f7; color: #1f2933; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 200px; background: #1f2933; color: #fff; padding: 16px 0; flex-shrink: 0; }
        .sidebar .empresa { padding: 0 16px 16px; font-weight: 700; font-size: 14px; border-bottom: 1px solid #323f4b; margin-bottom: 8px; }
        .sidebar button { display: block; width: 100%; text-align: left; background: none; border: none; color: #cbd2d9; padding: 10px 16px; font-size: 13px; cursor: pointer; }
        .sidebar button:hover, .sidebar button.ativo { background: #323f4b; color: #fff; }
        .conteudo { flex: 1; padding: 24px; }
        h1 { font-size: 18px; margin-top: 0; }
        .secao { display: none; }
        .secao.ativa { display: block; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat { background: #fff; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .stat .label { font-size: 11px; color: #616e7c; }
        .stat .valor { font-size: 22px; font-weight: 700; margin-top: 4px; }
        .card { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e4e7eb; }
        th { color: #616e7c; font-weight: 600; }
        input, select, button.acao { font-size: 13px; padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd2d9; }
        button.acao { background: #1a56db; color: #fff; border: none; cursor: pointer; }
        button.secundario { background: #616e7c; color:#fff; border: none; cursor: pointer; }
        .linha-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .linha-form label { display: block; font-size: 11px; color: #616e7c; margin-bottom: 2px; }
        .msg { font-size: 12px; margin-top: 6px; }
        .msg.erro { color: #c81e1e; }
        .msg.ok { color: #046c4e; }
        .status { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .status-em_aberto, .status-aberta { background: #fff3bf; color: #8a6d00; }
        .status-pago, .status-lotada { background: #d1fae5; color: #046c4e; }
        .status-atrasado, .status-cancelada { background: #ffe3e3; color: #b02525; }
    </style>
</head>
<body>
    <div class="layout">
        <div class="sidebar">
            <div class="empresa">{{ $empresaSlug }}</div>
            <button class="ativo" onclick="mostrarSecao('dashboard', this)">Dashboard</button>
            <button onclick="mostrarSecao('agenda', this)">Agenda de Visitas</button>
            <button onclick="mostrarSecao('produtos', this)">Produtos</button>
            <button onclick="mostrarSecao('clientes', this)">Clientes</button>
            <button onclick="mostrarSecao('vendedores', this)">Vendedores</button>
            <button onclick="mostrarSecao('financeiro', this)">Financeiro</button>
            <button onclick="mostrarSecao('usuarios', this)">Usuários</button>
            <form method="POST" action="/logout" style="padding: 16px;">
                @csrf
                <button type="submit" class="secundario" style="width:100%;">Sair</button>
            </form>
        </div>

        <div class="conteudo">
            <section id="secao-dashboard" class="secao ativa">
                <h1>Dashboard</h1>
                <div class="cards">
                    <div class="stat"><div class="label">Vagas ocupadas hoje</div><div class="valor" id="ind-vagas-hoje">-</div></div>
                    <div class="stat"><div class="label">Vendas do mês</div><div class="valor" id="ind-vendas-mes">-</div></div>
                    <div class="stat"><div class="label">Ocupação média</div><div class="valor" id="ind-ocupacao">-</div></div>
                    <div class="stat"><div class="label">Comissões do mês</div><div class="valor" id="ind-comissoes">-</div></div>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Próximas visitas</h2>
                    <table>
                        <thead><tr><th>Data/hora</th><th>Vagas</th><th>Status</th></tr></thead>
                        <tbody id="tbody-proximas-visitas"></tbody>
                    </table>
                </div>
            </section>

            <section id="secao-agenda" class="secao">
                <h1>Agenda de Visitas</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Data/hora</label><input type="datetime-local" id="ag-data"></div>
                        <div><label>Vagas totais</label><input type="number" id="ag-vagas" style="width:100px"></div>
                        <div><label>Valor por visitante (R$)</label><input type="number" step="0.01" id="ag-valor" style="width:120px"></div>
                        <div><button class="acao" onclick="criarAgenda()">Adicionar horário</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Data/hora</th><th>Vagas</th><th>Valor</th><th>Status</th></tr></thead>
                        <tbody id="tbody-agenda"></tbody>
                    </table>
                    <p class="msg" id="msg-agenda"></p>
                </div>
            </section>

            <section id="secao-produtos" class="secao">
                <h1>Produtos</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="pr-nome"></div>
                        <div><label>Tipo</label><select id="pr-tipo"><option value="fisico">Físico</option><option value="agendamento">Agendamento</option></select></div>
                        <div><label>Preço (R$)</label><input type="number" step="0.01" id="pr-preco" style="width:100px"></div>
                        <div><label>Estoque</label><input type="number" id="pr-estoque" style="width:80px"></div>
                        <div><button class="acao" onclick="criarProduto()">Cadastrar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Tipo</th><th>Preço</th><th>Estoque</th></tr></thead>
                        <tbody id="tbody-produtos"></tbody>
                    </table>
                    <p class="msg" id="msg-produtos"></p>
                </div>
            </section>

            <section id="secao-clientes" class="secao">
                <h1>Clientes</h1>
                <div class="card">
                    <table>
                        <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>E-mail</th><th>Telefone</th><th>LGPD</th></tr></thead>
                        <tbody id="tbody-clientes"></tbody>
                    </table>
                </div>
            </section>

            <section id="secao-vendedores" class="secao">
                <h1>Vendedores</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="ve-nome"></div>
                        <div><label>Comissão (%)</label><input type="number" step="0.01" id="ve-comissao" style="width:100px"></div>
                        <div><button class="acao" onclick="criarVendedor()">Cadastrar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Comissão</th></tr></thead>
                        <tbody id="tbody-vendedores"></tbody>
                    </table>
                    <p class="msg" id="msg-vendedores"></p>
                </div>
            </section>

            <section id="secao-financeiro" class="secao">
                <h1>Financeiro</h1>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Contas a pagar</h2>
                    <div class="linha-form">
                        <div><label>Valor (R$)</label><input type="number" step="0.01" id="cp-valor" style="width:100px"></div>
                        <div><label>Vencimento</label><input type="date" id="cp-vencimento"></div>
                        <div><button class="acao" onclick="criarContaPagar()">Lançar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Fornecedor</th><th>Valor</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody-contas-pagar"></tbody>
                    </table>
                    <p class="msg" id="msg-contas-pagar"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Contas a receber</h2>
                    <div class="linha-form">
                        <div><label>Valor (R$)</label><input type="number" step="0.01" id="cr-valor" style="width:100px"></div>
                        <div><label>Vencimento</label><input type="date" id="cr-vencimento"></div>
                        <div><button class="acao" onclick="criarContaReceber()">Lançar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Cliente</th><th>Valor</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody-contas-receber"></tbody>
                    </table>
                    <p class="msg" id="msg-contas-receber"></p>
                </div>
            </section>

            <section id="secao-usuarios" class="secao">
                <h1>Usuários</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="us-nome"></div>
                        <div><label>E-mail</label><input type="email" id="us-email"></div>
                        <div><label>Senha</label><input type="password" id="us-senha"></div>
                        <div><label>Perfil</label>
                            <select id="us-perfil">
                                <option value="atendente">Atendente</option>
                                <option value="caixa">Caixa</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div><button class="acao" onclick="criarUsuario()">Cadastrar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-usuarios"></tbody>
                    </table>
                    <p class="msg" id="msg-usuarios"></p>
                </div>
            </section>
        </div>
    </div>

    <script>
        const empresa = @json($empresaSlug);
        const base = `/dashboard/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        const carregadores = {
            dashboard: carregarIndicadores,
            agenda: carregarAgenda,
            produtos: carregarProdutos,
            clientes: carregarClientes,
            vendedores: carregarVendedores,
            financeiro: () => { carregarContasPagar(); carregarContasReceber(); },
            usuarios: carregarUsuarios,
        };

        function mostrarSecao(nome, botao) {
            document.querySelectorAll('.secao').forEach(s => s.classList.remove('ativa'));
            document.getElementById(`secao-${nome}`).classList.add('ativa');
            document.querySelectorAll('.sidebar button').forEach(b => b.classList.remove('ativo'));
            if (botao) botao.classList.add('ativo');
            carregadores[nome]?.();
        }

        async function carregarIndicadores() {
            const resp = await fetch(`${base}/indicadores`);
            const dados = await resp.json();
            document.getElementById('ind-vagas-hoje').textContent = dados.vagas_hoje;
            document.getElementById('ind-vendas-mes').textContent = `R$ ${Number(dados.vendas_mes).toFixed(2)}`;
            document.getElementById('ind-ocupacao').textContent = `${dados.ocupacao_media}%`;
            document.getElementById('ind-comissoes').textContent = `R$ ${Number(dados.comissoes_a_pagar).toFixed(2)}`;
            document.getElementById('tbody-proximas-visitas').innerHTML = dados.proximas_visitas.map(v => `
                <tr>
                    <td>${new Date(v.data_hora).toLocaleString('pt-BR')}</td>
                    <td>${v.vagas_reservadas}/${v.vagas_total}</td>
                    <td><span class="status status-${v.status}">${v.status}</span></td>
                </tr>
            `).join('') || '<tr><td colspan="3">Nenhuma visita agendada.</td></tr>';
        }

        async function carregarAgenda() {
            const resp = await fetch(`${base}/agenda`);
            const lista = await resp.json();
            document.getElementById('tbody-agenda').innerHTML = lista.map(a => `
                <tr>
                    <td>${new Date(a.data_hora).toLocaleString('pt-BR')}</td>
                    <td>${a.vagas_reservadas}/${a.vagas_total}</td>
                    <td>R$ ${Number(a.valor_visita).toFixed(2)}</td>
                    <td><span class="status status-${a.status}">${a.status}</span></td>
                </tr>
            `).join('') || '<tr><td colspan="4">Nenhum horário cadastrado.</td></tr>';
        }

        async function criarAgenda() {
            const dados = {
                data_hora: document.getElementById('ag-data').value,
                vagas_total: Number(document.getElementById('ag-vagas').value),
                valor_visita: Number(document.getElementById('ag-valor').value),
            };
            const resp = await fetch(`${base}/agenda`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-agenda');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Horário adicionado.';
            carregarAgenda();
        }

        async function carregarProdutos() {
            const resp = await fetch(`${base}/produtos`);
            const lista = await resp.json();
            document.getElementById('tbody-produtos').innerHTML = lista.map(p => `
                <tr>
                    <td>${p.nome}</td>
                    <td>${p.tipo}</td>
                    <td>R$ ${Number(p.preco_venda).toFixed(2)}</td>
                    <td>${p.estoque_atual ?? '-'}</td>
                </tr>
            `).join('') || '<tr><td colspan="4">Nenhum produto cadastrado.</td></tr>';
        }

        async function criarProduto() {
            const dados = {
                nome: document.getElementById('pr-nome').value,
                tipo: document.getElementById('pr-tipo').value,
                preco_venda: Number(document.getElementById('pr-preco').value),
                estoque_atual: document.getElementById('pr-estoque').value || null,
            };
            const resp = await fetch(`${base}/produtos`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-produtos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Produto cadastrado.';
            carregarProdutos();
        }

        async function carregarClientes() {
            const resp = await fetch(`${base}/clientes`);
            const lista = await resp.json();
            document.getElementById('tbody-clientes').innerHTML = lista.map(c => `
                <tr>
                    <td>${c.nome}</td>
                    <td>${c.cpf_cnpj ?? '-'}</td>
                    <td>${c.email ?? '-'}</td>
                    <td>${c.telefone ?? '-'}</td>
                    <td>${c.consentimento_lgpd ? 'Sim' : 'Não'}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum cliente cadastrado.</td></tr>';
        }

        async function carregarVendedores() {
            const resp = await fetch(`${base}/vendedores`);
            const lista = await resp.json();
            document.getElementById('tbody-vendedores').innerHTML = lista.map(v => `
                <tr><td>${v.nome}</td><td>${v.percentual_comissao}%</td></tr>
            `).join('') || '<tr><td colspan="2">Nenhum vendedor cadastrado.</td></tr>';
        }

        async function criarVendedor() {
            const dados = {
                nome: document.getElementById('ve-nome').value,
                percentual_comissao: Number(document.getElementById('ve-comissao').value),
            };
            const resp = await fetch(`${base}/vendedores`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-vendedores');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Vendedor cadastrado.';
            carregarVendedores();
        }

        async function carregarContasPagar() {
            const resp = await fetch(`${base}/contas-pagar`);
            const lista = await resp.json();
            document.getElementById('tbody-contas-pagar').innerHTML = lista.map(c => `
                <tr>
                    <td>${c.fornecedor ? c.fornecedor.razao_social : '-'}</td>
                    <td>R$ ${Number(c.valor).toFixed(2)}</td>
                    <td>${c.vencimento}</td>
                    <td><span class="status status-${c.status}">${c.status}</span></td>
                    <td>${c.status !== 'pago' ? `<button class="secundario" onclick="pagarContaPagar(${c.id})">Marcar pago</button>` : ''}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhuma conta a pagar.</td></tr>';
        }

        async function criarContaPagar() {
            const dados = {
                valor: Number(document.getElementById('cp-valor').value),
                vencimento: document.getElementById('cp-vencimento').value,
            };
            const resp = await fetch(`${base}/contas-pagar`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-contas-pagar');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Conta lançada.';
            carregarContasPagar();
        }

        async function pagarContaPagar(id) {
            await fetch(`${base}/contas-pagar/${id}/pagar`, { method: 'PUT', headers: headersJson });
            carregarContasPagar();
        }

        async function carregarContasReceber() {
            const resp = await fetch(`${base}/contas-receber`);
            const lista = await resp.json();
            document.getElementById('tbody-contas-receber').innerHTML = lista.map(c => `
                <tr>
                    <td>${c.cliente ? c.cliente.nome : '-'}</td>
                    <td>R$ ${Number(c.valor).toFixed(2)}</td>
                    <td>${c.vencimento}</td>
                    <td><span class="status status-${c.status}">${c.status}</span></td>
                    <td>${c.status !== 'pago' ? `<button class="secundario" onclick="pagarContaReceber(${c.id})">Marcar pago</button>` : ''}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhuma conta a receber.</td></tr>';
        }

        async function criarContaReceber() {
            const dados = {
                valor: Number(document.getElementById('cr-valor').value),
                vencimento: document.getElementById('cr-vencimento').value,
            };
            const resp = await fetch(`${base}/contas-receber`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-contas-receber');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Conta lançada.';
            carregarContasReceber();
        }

        async function pagarContaReceber(id) {
            await fetch(`${base}/contas-receber/${id}/pagar`, { method: 'PUT', headers: headersJson });
            carregarContasReceber();
        }

        async function carregarUsuarios() {
            const resp = await fetch(`${base}/usuarios`);
            if (resp.status === 403) {
                document.getElementById('tbody-usuarios').innerHTML = '<tr><td colspan="5">Apenas administradores podem ver esta seção.</td></tr>';
                return;
            }
            const lista = await resp.json();
            document.getElementById('tbody-usuarios').innerHTML = lista.map(u => `
                <tr>
                    <td>${u.name}</td>
                    <td>${u.email}</td>
                    <td>${u.perfil}</td>
                    <td>${u.ativo ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="alternarUsuario(${u.id}, ${!u.ativo})">${u.ativo ? 'Desativar' : 'Ativar'}</button></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum usuário cadastrado.</td></tr>';
        }

        async function criarUsuario() {
            const dados = {
                name: document.getElementById('us-nome').value,
                email: document.getElementById('us-email').value,
                password: document.getElementById('us-senha').value,
                perfil: document.getElementById('us-perfil').value,
            };
            const resp = await fetch(`${base}/usuarios`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-usuarios');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Usuário cadastrado.';
            carregarUsuarios();
        }

        async function alternarUsuario(id, ativo) {
            await fetch(`${base}/usuarios/${id}`, { method: 'PUT', headers: headersJson, body: JSON.stringify({ ativo }) });
            carregarUsuarios();
        }

        carregarIndicadores();
    </script>
</body>
</html>
