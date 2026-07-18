<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Painel Super Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; padding: 24px; color: #1f2933; }
        h1 { font-size: 20px; }
        h2 { font-size: 15px; margin-top: 0; }
        .card { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e4e7eb; }
        th { color: #616e7c; font-weight: 600; }
        button, input, select { font-size: 13px; padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd2d9; }
        button { background: #1a56db; color: #fff; border: none; cursor: pointer; }
        button.secundario { background: #616e7c; }
        .linha-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .linha-form label { display: block; font-size: 11px; color: #616e7c; margin-bottom: 2px; }
        .status { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .status-ativa, .status-em_dia { background: #d1fae5; color: #046c4e; }
        .status-suspensa, .status-atrasado { background: #fff3bf; color: #8a6d00; }
        .status-cancelada, .status-cancelado { background: #ffe3e3; color: #b02525; }
        .msg { font-size: 12px; margin-top: 6px; }
        .msg.erro { color: #c81e1e; }
        .msg.ok { color: #046c4e; }
    </style>
</head>
<body>
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Painel Super Admin</h1>
        <form method="POST" action="/logout">
            @csrf
            <span style="font-size:12px; color:#616e7c; margin-right:8px;">{{ auth()->user()->name }}</span>
            <button type="submit" class="secundario">Sair</button>
        </form>
    </div>

    <div class="card">
        <h2>Empresas</h2>
        <div class="linha-form">
            <div><label>Razão social</label><input type="text" id="e-razao"></div>
            <div><label>CNPJ</label><input type="text" id="e-cnpj" placeholder="00.000.000/0001-00"></div>
            <div><label>Slug (URL)</label><input type="text" id="e-slug" placeholder="minha-empresa"></div>
            <div><label>Plano</label><select id="e-plano"></select></div>
            <div><button onclick="criarEmpresa()">Cadastrar empresa</button></div>
        </div>
        <table>
            <thead><tr><th>Razão social</th><th>CNPJ</th><th>Slug</th><th>Plano</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody id="tbody-empresas"><tr><td colspan="6">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-empresas"></p>
    </div>

    <div class="card">
        <h2>Planos</h2>
        <div class="linha-form">
            <div><label>Nome</label><input type="text" id="p-nome"></div>
            <div><label>Valor mensal (R$)</label><input type="number" step="0.01" id="p-valor"></div>
            <div><button onclick="criarPlano()">Cadastrar plano</button></div>
        </div>
        <table>
            <thead><tr><th>Nome</th><th>Valor mensal</th></tr></thead>
            <tbody id="tbody-planos"><tr><td colspan="2">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-planos"></p>
    </div>

    <div class="card">
        <h2>Assinaturas</h2>
        <div class="linha-form">
            <div><label>Empresa</label><select id="a-empresa"></select></div>
            <div><label>Plano</label><select id="a-plano"></select></div>
            <div><label>Status</label>
                <select id="a-status">
                    <option value="em_dia">Em dia</option>
                    <option value="atrasado">Atrasado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div><label>Início</label><input type="date" id="a-inicio"></div>
            <div><button onclick="criarAssinatura()">Registrar assinatura</button></div>
        </div>
        <table>
            <thead><tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Início</th></tr></thead>
            <tbody id="tbody-assinaturas"><tr><td colspan="4">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-assinaturas"></p>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken };
        let planosCache = [];

        async function carregarPlanos() {
            const resp = await fetch('/superadmin/planos');
            planosCache = await resp.json();

            const tbody = document.getElementById('tbody-planos');
            tbody.innerHTML = planosCache.map(p => `
                <tr><td>${p.nome}</td><td>R$ ${Number(p.valor_mensal).toFixed(2)}</td></tr>
            `).join('') || '<tr><td colspan="2">Nenhum plano cadastrado.</td></tr>';

            const opcoes = planosCache.map(p => `<option value="${p.id}">${p.nome}</option>`).join('');
            document.getElementById('e-plano').innerHTML = opcoes;
            document.getElementById('a-plano').innerHTML = opcoes;
        }

        async function criarPlano() {
            const dados = {
                nome: document.getElementById('p-nome').value,
                valor_mensal: Number(document.getElementById('p-valor').value),
            };
            const resp = await fetch('/superadmin/planos', { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-planos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Plano cadastrado.';
            carregarPlanos();
        }

        async function carregarEmpresas() {
            const resp = await fetch('/superadmin/empresas');
            const empresas = await resp.json();

            const tbody = document.getElementById('tbody-empresas');
            tbody.innerHTML = empresas.map(e => `
                <tr>
                    <td>${e.razao_social}</td>
                    <td>${e.cnpj}</td>
                    <td>${e.slug}</td>
                    <td>${e.plano ? e.plano.nome : '-'}</td>
                    <td><span class="status status-${e.status}">${e.status}</span></td>
                    <td>
                        ${e.status === 'ativa'
                            ? `<button class="secundario" onclick="mudarStatusEmpresa(${e.id}, 'suspensa')">Suspender</button>`
                            : `<button onclick="mudarStatusEmpresa(${e.id}, 'ativa')">Reativar</button>`}
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhuma empresa cadastrada.</td></tr>';

            const opcoes = empresas.map(e => `<option value="${e.id}">${e.razao_social}</option>`).join('');
            document.getElementById('a-empresa').innerHTML = opcoes;
        }

        async function criarEmpresa() {
            const dados = {
                razao_social: document.getElementById('e-razao').value,
                cnpj: document.getElementById('e-cnpj').value,
                slug: document.getElementById('e-slug').value,
                plano_id: Number(document.getElementById('e-plano').value),
            };
            const resp = await fetch('/superadmin/empresas', { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-empresas');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Empresa cadastrada.';
            carregarEmpresas();
        }

        async function mudarStatusEmpresa(empresaId, status) {
            const resp = await fetch(`/superadmin/empresas/${empresaId}`, {
                method: 'PUT', headers: headersJson, body: JSON.stringify({ status }),
            });
            if (resp.ok) carregarEmpresas();
        }

        async function carregarAssinaturas() {
            const resp = await fetch('/superadmin/assinaturas');
            const assinaturas = await resp.json();

            const tbody = document.getElementById('tbody-assinaturas');
            tbody.innerHTML = assinaturas.map(a => `
                <tr>
                    <td>${a.empresa ? a.empresa.razao_social : '-'}</td>
                    <td>${a.plano ? a.plano.nome : '-'}</td>
                    <td><span class="status status-${a.status_pagamento}">${a.status_pagamento}</span></td>
                    <td>${a.inicio}</td>
                </tr>
            `).join('') || '<tr><td colspan="4">Nenhuma assinatura registrada.</td></tr>';
        }

        async function criarAssinatura() {
            const dados = {
                empresa_id: Number(document.getElementById('a-empresa').value),
                plano_id: Number(document.getElementById('a-plano').value),
                status_pagamento: document.getElementById('a-status').value,
                inicio: document.getElementById('a-inicio').value,
            };
            const resp = await fetch('/superadmin/assinaturas', { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-assinaturas');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Assinatura registrada.';
            carregarAssinaturas();
        }

        carregarPlanos().then(carregarEmpresas);
        carregarAssinaturas();
    </script>
</body>
</html>
