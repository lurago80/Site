<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Painel Super Admin</title>
    <link rel="stylesheet" href="/css/sistema.css">
    <style>
        body { padding: 0 24px 24px; }
    </style>
</head>
<body>
    @include('partials.topo', ['titulo' => 'Super Admin'])

    <div class="card">
        <h2>Empresas</h2>
        <input type="hidden" id="e-id">
        <div class="linha-form">
            <div><label>Razão social</label><input type="text" id="e-razao"></div>
            <div><label>CNPJ</label><input type="text" id="e-cnpj" placeholder="00.000.000/0001-00"></div>
            <div><label>Slug (URL)</label><input type="text" id="e-slug" placeholder="minha-empresa"></div>
            <div><label>Plano</label><select id="e-plano"></select></div>
            <div><button id="e-botao" onclick="salvarEmpresa()">Cadastrar empresa</button></div>
            <div><button class="secundario" onclick="limparFormularioEmpresa()" style="display:none;" id="e-cancelar">Cancelar edição</button></div>
        </div>
        <p style="font-size:11px; color:var(--cor-texto-suave); margin-top:0;">
            "Editar" permite reassociar a empresa a outro plano ou corrigir a razão social - CNPJ e slug não
            mudam depois de criados (afetam URL da loja e identificação fiscal já em uso).
        </p>
        <table>
            <thead><tr><th>Razão social</th><th>CNPJ</th><th>Slug</th><th>Plano</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody id="tbody-empresas"><tr><td colspan="6">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-empresas"></p>
    </div>

    <div class="card">
        <h2>Planos</h2>
        <input type="hidden" id="p-id">
        <div class="linha-form">
            <div><label>Nome</label><input type="text" id="p-nome"></div>
            <div><label>Valor mensal (R$)</label><input type="number" step="0.01" id="p-valor"></div>
            <div><button id="p-botao" onclick="salvarPlano()">Cadastrar plano</button></div>
            <div><button class="secundario" onclick="limparFormularioPlano()" style="display:none;" id="p-cancelar">Cancelar edição</button></div>
        </div>
        <table>
            <thead><tr><th>Nome</th><th>Valor mensal</th><th></th></tr></thead>
            <tbody id="tbody-planos"><tr><td colspan="3">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-planos"></p>
    </div>

    <div class="card">
        <h2>Cobrança de assinatura (Asaas)</h2>
        <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
            Configuração única e global: é a plataforma cobrando cada empresa cliente pela mensalidade
            (diferente do gateway de pagamento, que é a empresa cliente cobrando o consumidor final dela).
            Asaas não cobra mensalidade da própria plataforma - só uma taxa quando uma cobrança acontece de
            fato. Sem configurar aqui, o cadastro de assinatura abaixo fica manual (você define o status à mão).
        </p>
        <p id="asa-status" style="font-size:13px;">Carregando...</p>
        <div class="linha-form">
            <div><label>Ambiente</label>
                <select id="asa-ambiente">
                    <option value="sandbox">Sandbox (testes)</option>
                    <option value="producao">Produção</option>
                </select>
            </div>
            <div><label><input type="checkbox" id="asa-ativo"> Ativo</label></div>
            <div style="flex:1"><label>API Key</label><input type="password" id="asa-api-key" placeholder="Deixe em branco para manter a atual"></div>
            <div><button onclick="salvarConfigAssinatura()">Salvar</button></div>
        </div>
        <p class="msg" id="msg-config-assinatura"></p>
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
        <p style="font-size:11px; color:var(--cor-texto-suave); margin-top:0;">
            Com o Asaas ativo acima, a cobrança recorrente é criada automaticamente e o status inicial
            aqui é só o ponto de partida - o Asaas atualiza sozinho depois (webhook). "Dar baixa" marca a
            assinatura como paga na mão (fora do Asaas) e reativa a empresa se estiver suspensa.
        </p>
        <table>
            <thead><tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Início</th><th></th></tr></thead>
            <tbody id="tbody-assinaturas"><tr><td colspan="5">Carregando...</td></tr></tbody>
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
                <tr>
                    <td>${p.nome}</td>
                    <td>R$ ${Number(p.valor_mensal).toFixed(2)}</td>
                    <td><button class="secundario" onclick="editarPlano(${p.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="3">Nenhum plano cadastrado.</td></tr>';

            const opcoes = planosCache.map(p => `<option value="${p.id}">${p.nome}</option>`).join('');
            document.getElementById('e-plano').innerHTML = opcoes;
            document.getElementById('a-plano').innerHTML = opcoes;
        }

        function editarPlano(id) {
            const p = planosCache.find(x => x.id === id);
            if (!p) return;
            document.getElementById('p-id').value = p.id;
            document.getElementById('p-nome').value = p.nome;
            document.getElementById('p-valor').value = p.valor_mensal;
            document.getElementById('p-botao').textContent = 'Salvar edição';
            document.getElementById('p-cancelar').style.display = 'inline-block';
        }

        function limparFormularioPlano() {
            document.getElementById('p-id').value = '';
            document.getElementById('p-nome').value = '';
            document.getElementById('p-valor').value = '';
            document.getElementById('p-botao').textContent = 'Cadastrar plano';
            document.getElementById('p-cancelar').style.display = 'none';
        }

        async function salvarPlano() {
            const id = document.getElementById('p-id').value;
            const dados = {
                nome: document.getElementById('p-nome').value,
                valor_mensal: Number(document.getElementById('p-valor').value),
            };
            const url = id ? `/superadmin/planos/${id}` : '/superadmin/planos';
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-planos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Plano atualizado.' : 'Plano cadastrado.';
            limparFormularioPlano();
            carregarPlanos();
        }

        let empresasCache = [];

        async function carregarEmpresas() {
            const resp = await fetch('/superadmin/empresas');
            empresasCache = await resp.json();

            const tbody = document.getElementById('tbody-empresas');
            tbody.innerHTML = empresasCache.map(e => `
                <tr>
                    <td>${e.razao_social}</td>
                    <td>${e.cnpj}</td>
                    <td>${e.slug}</td>
                    <td>${e.plano ? e.plano.nome : '-'}</td>
                    <td><span class="status status-${e.status}">${e.status}</span></td>
                    <td>
                        <button class="secundario" onclick="editarEmpresa(${e.id})">Editar</button>
                        ${e.status === 'ativa'
                            ? `<button class="secundario" onclick="mudarStatusEmpresa(${e.id}, 'suspensa')">Suspender</button>`
                            : `<button onclick="mudarStatusEmpresa(${e.id}, 'ativa')">Reativar</button>`}
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhuma empresa cadastrada.</td></tr>';

            const opcoes = empresasCache.map(e => `<option value="${e.id}">${e.razao_social}</option>`).join('');
            document.getElementById('a-empresa').innerHTML = opcoes;
        }

        function editarEmpresa(id) {
            const e = empresasCache.find(x => x.id === id);
            if (!e) return;
            document.getElementById('e-id').value = e.id;
            document.getElementById('e-razao').value = e.razao_social;
            document.getElementById('e-cnpj').value = e.cnpj;
            document.getElementById('e-cnpj').disabled = true;
            document.getElementById('e-slug').value = e.slug;
            document.getElementById('e-slug').disabled = true;
            document.getElementById('e-plano').value = e.plano_id ?? '';
            document.getElementById('e-botao').textContent = 'Salvar edição';
            document.getElementById('e-cancelar').style.display = 'inline-block';
        }

        function limparFormularioEmpresa() {
            document.getElementById('e-id').value = '';
            document.getElementById('e-razao').value = '';
            document.getElementById('e-cnpj').value = '';
            document.getElementById('e-cnpj').disabled = false;
            document.getElementById('e-slug').value = '';
            document.getElementById('e-slug').disabled = false;
            document.getElementById('e-plano').value = '';
            document.getElementById('e-botao').textContent = 'Cadastrar empresa';
            document.getElementById('e-cancelar').style.display = 'none';
        }

        async function salvarEmpresa() {
            const id = document.getElementById('e-id').value;
            const msg = document.getElementById('msg-empresas');

            if (id) {
                const dados = {
                    razao_social: document.getElementById('e-razao').value,
                    plano_id: Number(document.getElementById('e-plano').value),
                };
                const resp = await fetch(`/superadmin/empresas/${id}`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
                const resposta = await resp.json();
                if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
                msg.className = 'msg ok'; msg.textContent = 'Empresa atualizada.';
                limparFormularioEmpresa();
                carregarEmpresas();
                return;
            }

            const dados = {
                razao_social: document.getElementById('e-razao').value,
                cnpj: document.getElementById('e-cnpj').value,
                slug: document.getElementById('e-slug').value,
                plano_id: Number(document.getElementById('e-plano').value),
            };
            const resp = await fetch('/superadmin/empresas', { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
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
                    <td>${a.status_pagamento !== 'em_dia' ? `<button class="secundario" onclick="baixarAssinatura(${a.id})">Dar baixa</button>` : ''}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhuma assinatura registrada.</td></tr>';
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

        async function baixarAssinatura(assinaturaId) {
            const resp = await fetch(`/superadmin/assinaturas/${assinaturaId}/baixar`, { method: 'PUT', headers: headersJson, body: '{}' });
            const msg = document.getElementById('msg-assinaturas');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = 'Erro ao dar baixa na assinatura.'; return; }
            msg.className = 'msg ok'; msg.textContent = 'Baixa registrada - assinatura marcada como paga.';
            carregarAssinaturas();
        }

        async function carregarConfigAssinatura() {
            const resp = await fetch('/superadmin/config-assinatura');
            const dados = await resp.json();
            const status = document.getElementById('asa-status');
            if (!dados) { status.textContent = 'Asaas não configurado ainda - cadastro de assinatura fica manual.'; return; }
            document.getElementById('asa-ambiente').value = dados.ambiente;
            document.getElementById('asa-ativo').checked = dados.ativo;
            status.textContent = dados.tem_credenciais
                ? `Asaas configurado (${dados.ambiente}) - ${dados.ativo ? 'ativo' : 'inativo'}.`
                : 'Asaas selecionado, mas sem chave de API salva ainda.';
        }

        async function salvarConfigAssinatura() {
            const dados = {
                provider: 'asaas',
                ambiente: document.getElementById('asa-ambiente').value,
                ativo: document.getElementById('asa-ativo').checked,
            };
            const apiKey = document.getElementById('asa-api-key').value;
            if (apiKey) { dados.api_key = apiKey; }

            const resp = await fetch('/superadmin/config-assinatura', { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-assinatura');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Configuração do Asaas salva.';
            document.getElementById('asa-api-key').value = '';
            carregarConfigAssinatura();
        }

        carregarPlanos().then(carregarEmpresas);
        carregarAssinaturas();
        carregarConfigAssinatura();
    </script>
</body>
</html>
