<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Painel Fiscal — {{ $empresaSlug }}</title>
    <link rel="stylesheet" href="/css/sistema.css">
    <style>
        body { padding: 0 24px 24px; }
    </style>
</head>
<body>
    @include('partials.topo', ['titulo' => "Gestão Fiscal — {$empresaSlug}"])

    <div class="card">
        <h2>Documentos fiscais</h2>
        <div class="linha-form">
            <div><label>Modelo</label>
                <select id="f-modelo">
                    <option value="">Todos</option>
                    <option value="65">NFC-e</option>
                    <option value="55">NFe</option>
                </select>
            </div>
            <div><label>Data início</label><input type="date" id="f-data-inicio"></div>
            <div><label>Data fim</label><input type="date" id="f-data-fim"></div>
            <div>
                <label>Status</label>
                <select id="f-status">
                    <option value="">Todos</option>
                    <option value="autorizada">Autorizada</option>
                    <option value="cancelada">Cancelada</option>
                    <option value="rejeitada">Rejeitada</option>
                    <option value="contingencia">Contingência</option>
                </select>
            </div>
            <div><button onclick="carregarRelatorio()">Filtrar</button></div>
            <div><button class="secundario" onclick="exportar('xmls')">Exportar XMLs (.zip)</button></div>
            <div><button class="secundario" onclick="exportar('relatorio-contador')">Relatório contador (.csv)</button></div>
        </div>
        <table>
            <thead>
                <tr><th>Nº</th><th>Série</th><th>Modelo</th><th>Status</th><th>Total</th><th>Emitido em</th><th>Ações</th></tr>
            </thead>
            <tbody id="tbody-documentos"><tr><td colspan="7">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-documentos"></p>
    </div>

    <div class="card">
        <h2>Importar venda não fiscal → emitir documento</h2>
        <div class="linha-form">
            <div><label>Emitir como</label>
                <select id="imp-modelo"><option value="65">NFC-e (65)</option><option value="55">NFe (55)</option></select>
            </div>
        </div>
        <table>
            <thead><tr><th>Venda</th><th>Cliente</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
            <tbody id="tbody-vendas"><tr><td colspan="5">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-importar"></p>
    </div>

    <div class="card">
        <h2>Importar NFC-e → NFe (regularização)</h2>
        <p style="font-size:12px; color:#616e7c; margin-top:0;">
            Gera uma NFe formal referenciando uma NFC-e já autorizada, com CFOP 5929 (mesmo estado)
            ou 6929 (fora do estado) - útil quando o cliente pessoa jurídica precisa de NFe para a contabilidade dele.
            Exige que o cliente da venda tenha endereço completo cadastrado.
        </p>
        <table>
            <thead><tr><th>NFC-e</th><th>Cliente</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
            <tbody id="tbody-nfces-disponiveis"><tr><td colspan="5">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-importar-nfe"></p>
    </div>

    <div class="card">
        <h2>Inutilizar numeração</h2>
        <div class="linha-form">
            <div><label>Modelo</label>
                <select id="inut-modelo"><option value="65">NFC-e (65)</option><option value="55">NFe (55)</option></select>
            </div>
            <div><label>Série</label><input type="text" id="inut-serie" value="1" style="width:60px"></div>
            <div><label>Nº inicial</label><input type="number" id="inut-inicial" style="width:100px"></div>
            <div><label>Nº final</label><input type="number" id="inut-final" style="width:100px"></div>
            <div style="flex:1"><label>Justificativa (mín. 15 caracteres)</label><input type="text" id="inut-justificativa" style="width:100%"></div>
            <div><button class="perigo" onclick="inutilizar()">Inutilizar</button></div>
        </div>
        <p class="msg" id="msg-inutilizar"></p>
    </div>

    <script>
        const empresa = @json($empresaSlug);
        const apiBase = `/fiscal/${empresa}`;
        const webBase = `/fiscal/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        async function carregarRelatorio() {
            const params = new URLSearchParams({
                modelo: document.getElementById('f-modelo').value,
                data_inicio: document.getElementById('f-data-inicio').value,
                data_fim: document.getElementById('f-data-fim').value,
                status: document.getElementById('f-status').value,
            });
            const resp = await fetch(`${apiBase}/relatorio?${params}`);
            const docs = await resp.json();
            const tbody = document.getElementById('tbody-documentos');
            if (!docs.length) { tbody.innerHTML = '<tr><td colspan="7">Nenhum documento encontrado.</td></tr>'; return; }
            tbody.innerHTML = docs.map(d => `
                <tr>
                    <td>${d.numero}</td>
                    <td>${d.serie}</td>
                    <td>${d.modelo === 55 ? 'NFe' : 'NFC-e'}</td>
                    <td><span class="status status-${d.status}">${d.status}</span></td>
                    <td>R$ ${Number(d.total).toFixed(2)}</td>
                    <td>${new Date(d.created_at).toLocaleString('pt-BR')}</td>
                    <td>
                        <button class="secundario" onclick="reimprimir(${d.id})">Reimprimir</button>
                        ${d.status === 'autorizada' ? `<button class="perigo" onclick="cancelar(${d.id})">Cancelar</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function reimprimir(documentoId) {
            window.open(`${webBase}/documentos/${documentoId}/reimprimir`, '_blank');
        }

        async function cancelar(documentoId) {
            const justificativa = prompt('Justificativa do cancelamento (mín. 15 caracteres):');
            if (!justificativa) return;
            const resp = await fetch(`${apiBase}/documentos/${documentoId}/cancelar`, {
                method: 'POST',
                headers: headersJson,
                body: JSON.stringify({ justificativa }),
            });
            const dados = await resp.json();
            const msg = document.getElementById('msg-documentos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = 'Documento cancelado com sucesso.';
            carregarRelatorio();
        }

        function exportar(tipo) {
            const params = new URLSearchParams({
                modelo: document.getElementById('f-modelo').value,
                data_inicio: document.getElementById('f-data-inicio').value,
                data_fim: document.getElementById('f-data-fim').value,
            });
            window.location = `${webBase}/exportar/${tipo}?${params}`;
        }

        async function carregarVendasNaoFiscais() {
            const resp = await fetch(`${apiBase}/vendas-nao-fiscais`);
            const vendas = await resp.json();
            const tbody = document.getElementById('tbody-vendas');
            if (!vendas.length) { tbody.innerHTML = '<tr><td colspan="5">Nenhuma venda não fiscal pendente.</td></tr>'; return; }
            tbody.innerHTML = vendas.map(v => `
                <tr>
                    <td>#${v.id}</td>
                    <td>${v.cliente ? v.cliente.nome : 'Consumidor não identificado'}</td>
                    <td>R$ ${Number(v.valor_total).toFixed(2)}</td>
                    <td>${new Date(v.data_venda).toLocaleString('pt-BR')}</td>
                    <td><button onclick="importar(${v.id})">Emitir NFC-e</button></td>
                </tr>
            `).join('');
        }

        async function importar(vendaId) {
            const modelo = Number(document.getElementById('imp-modelo').value);
            const resp = await fetch(`${apiBase}/vendas/${vendaId}/importar`, {
                method: 'POST',
                headers: headersJson,
                body: JSON.stringify({ modelo }),
            });
            const dados = await resp.json();
            const msg = document.getElementById('msg-importar');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = `${modelo === 55 ? 'NFe' : 'NFC-e'} emitida: status ${dados.status}.`;
            carregarVendasNaoFiscais();
            carregarRelatorio();
        }

        async function carregarNfcesDisponiveis() {
            const resp = await fetch(`${apiBase}/nfces-disponiveis-para-nfe`);
            const lista = await resp.json();
            const tbody = document.getElementById('tbody-nfces-disponiveis');
            if (!lista.length) { tbody.innerHTML = '<tr><td colspan="5">Nenhuma NFC-e disponível para importar.</td></tr>'; return; }
            tbody.innerHTML = lista.map(d => `
                <tr>
                    <td>#${d.numero}</td>
                    <td>${d.cliente || 'Não identificado'}${d.cliente_completo ? '' : ' <span style="color:#c81e1e;">(endereço incompleto)</span>'}</td>
                    <td>R$ ${Number(d.total).toFixed(2)}</td>
                    <td>${new Date(d.created_at).toLocaleString('pt-BR')}</td>
                    <td><button onclick="importarNfce(${d.id})" ${d.cliente_completo ? '' : 'disabled'}>Gerar NFe</button></td>
                </tr>
            `).join('');
        }

        async function importarNfce(documentoNfceId) {
            const resp = await fetch(`${apiBase}/nfces/${documentoNfceId}/importar-para-nfe`, {
                method: 'POST',
                headers: headersJson,
            });
            const dados = await resp.json();
            const msg = document.getElementById('msg-importar-nfe');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = `NFe emitida: status ${dados.status}.`;
            carregarNfcesDisponiveis();
            carregarRelatorio();
        }

        async function inutilizar() {
            const dados = {
                modelo: Number(document.getElementById('inut-modelo').value),
                serie: document.getElementById('inut-serie').value,
                numero_inicial: Number(document.getElementById('inut-inicial').value),
                numero_final: Number(document.getElementById('inut-final').value),
                justificativa: document.getElementById('inut-justificativa').value,
            };
            const resp = await fetch(`${apiBase}/inutilizacoes`, {
                method: 'POST',
                headers: headersJson,
                body: JSON.stringify(dados),
            });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-inutilizar');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = `Inutilização ${resposta.status}.`;
        }

        carregarRelatorio();
        carregarVendasNaoFiscais();
        carregarNfcesDisponiveis();
    </script>
</body>
</html>
