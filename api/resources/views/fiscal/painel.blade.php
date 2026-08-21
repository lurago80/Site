<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Painel Fiscal — {{ $empresaSlug }}</title>
    <link rel="stylesheet" href="{{ asset('css/sistema.css') }}">
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
        <h2>Devolução de venda (gerar NFe de devolução)</h2>
        <p style="font-size:12px; color:#616e7c; margin-top:0;">
            Cliente devolvendo mercadoria comprada. Gera NFe (modelo 55) com CFOP 1202 (mesmo estado)
            ou 2202 (fora do estado), referenciando a nota original quando possível.
        </p>
        <table>
            <thead><tr><th>Documento</th><th>Cliente</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
            <tbody id="tbody-devolucao-documentos"><tr><td colspan="5">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-devolucao-lista"></p>

        <div id="painel-devolucao-itens" style="display:none; margin-top:16px;">
            <h3 style="font-size:14px;">Itens disponíveis para devolução — documento #<span id="devolucao-documento-numero"></span></h3>
            <table>
                <thead><tr><th>Devolver?</th><th>Produto</th><th>Vendido</th><th>Já devolvido</th><th>Disponível</th><th>Quantidade a devolver</th></tr></thead>
                <tbody id="tbody-devolucao-itens"><tr><td colspan="6">Carregando...</td></tr></tbody>
            </table>
            <div class="linha-form">
                <div><button onclick="confirmarDevolucao()">Confirmar devolução</button></div>
                <div><button class="secundario" onclick="fecharPainelDevolucao()">Cancelar</button></div>
            </div>
            <p class="msg" id="msg-devolucao-confirmar"></p>
        </div>
    </div>

    <div class="card">
        <h2>Devolução a fornecedor (gerar NFe de devolução)</h2>
        <p style="font-size:12px; color:#616e7c; margin-top:0;">
            Empresa devolvendo mercadoria comprada. Gera NFe (modelo 55) com CFOP 5202 (mesmo estado)
            ou 6202 (fora do estado), referenciando a nota do fornecedor quando possível.
            Exige compra confirmada e fornecedor com endereço completo cadastrado.
        </p>
        <table>
            <thead><tr><th>Compra</th><th>Fornecedor</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
            <tbody id="tbody-devolucao-fornecedor-compras"><tr><td colspan="5">Carregando...</td></tr></tbody>
        </table>
        <p class="msg" id="msg-devolucao-fornecedor-lista"></p>

        <div id="painel-devolucao-fornecedor-itens" style="display:none; margin-top:16px;">
            <h3 style="font-size:14px;">Itens disponíveis para devolução — compra #<span id="devolucao-fornecedor-compra-numero"></span></h3>
            <table>
                <thead><tr><th>Devolver?</th><th>Produto</th><th>Comprado</th><th>Já devolvido</th><th>Disponível</th><th>Quantidade a devolver</th></tr></thead>
                <tbody id="tbody-devolucao-fornecedor-itens"><tr><td colspan="6">Carregando...</td></tr></tbody>
            </table>
            <div class="linha-form">
                <div><button onclick="confirmarDevolucaoFornecedor()">Confirmar devolução</button></div>
                <div><button class="secundario" onclick="fecharPainelDevolucaoFornecedor()">Cancelar</button></div>
            </div>
            <p class="msg" id="msg-devolucao-fornecedor-confirmar"></p>
        </div>
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
        const apiBase = `{{ url('/fiscal') }}/${empresa}`;
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

        let devolucaoDocumentoId = null;

        async function carregarDocumentosElegiveisDevolucao() {
            const resp = await fetch(`${apiBase}/documentos-elegiveis-devolucao`);
            const lista = await resp.json();
            const tbody = document.getElementById('tbody-devolucao-documentos');
            if (!lista.length) { tbody.innerHTML = '<tr><td colspan="5">Nenhum documento disponível para devolução.</td></tr>'; return; }
            tbody.innerHTML = lista.map(d => `
                <tr>
                    <td>#${d.numero} (${d.modelo === 55 ? 'NFe' : 'NFC-e'})</td>
                    <td>${d.cliente || 'Não identificado'}</td>
                    <td>R$ ${Number(d.total).toFixed(2)}</td>
                    <td>${new Date(d.created_at).toLocaleString('pt-BR')}</td>
                    <td><button onclick="abrirPainelDevolucao(${d.id}, ${d.numero})">Devolver</button></td>
                </tr>
            `).join('');
        }

        async function abrirPainelDevolucao(documentoId, numero) {
            devolucaoDocumentoId = documentoId;
            document.getElementById('devolucao-documento-numero').textContent = numero;
            document.getElementById('painel-devolucao-itens').style.display = 'block';
            document.getElementById('msg-devolucao-confirmar').textContent = '';

            const resp = await fetch(`${apiBase}/documentos/${documentoId}/itens-disponiveis-devolucao`);
            const itens = await resp.json();
            const tbody = document.getElementById('tbody-devolucao-itens');
            if (!itens.length) { tbody.innerHTML = '<tr><td colspan="6">Nenhum item disponível.</td></tr>'; return; }
            tbody.innerHTML = itens.map(i => `
                <tr>
                    <td><input type="checkbox" class="devolucao-item-check" data-item-venda-id="${i.item_venda_id}"></td>
                    <td>${i.produto || '-'}</td>
                    <td>${i.quantidade_vendida}</td>
                    <td>${i.quantidade_ja_devolvida}</td>
                    <td>${i.quantidade_disponivel}</td>
                    <td><input type="number" step="0.001" min="0" max="${i.quantidade_disponivel}" class="devolucao-item-qtd" data-item-venda-id="${i.item_venda_id}" style="width:90px" ${i.quantidade_disponivel <= 0 ? 'disabled' : ''}></td>
                </tr>
            `).join('');
        }

        function fecharPainelDevolucao() {
            devolucaoDocumentoId = null;
            document.getElementById('painel-devolucao-itens').style.display = 'none';
        }

        async function confirmarDevolucao() {
            const itens = Array.from(document.querySelectorAll('.devolucao-item-check'))
                .filter(chk => chk.checked)
                .map(chk => {
                    const itemVendaId = Number(chk.dataset.itemVendaId);
                    const input = document.querySelector(`.devolucao-item-qtd[data-item-venda-id="${itemVendaId}"]`);
                    return { item_venda_id: itemVendaId, quantidade: Number(input.value) };
                });

            const msg = document.getElementById('msg-devolucao-confirmar');
            if (!itens.length) { msg.className = 'msg erro'; msg.textContent = 'Selecione ao menos um item.'; return; }

            const resp = await fetch(`${apiBase}/documentos/${devolucaoDocumentoId}/devolucao`, {
                method: 'POST',
                headers: headersJson,
                body: JSON.stringify({ itens }),
            });
            const dados = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = `NFe de devolução emitida: status ${dados.status}.`;
            fecharPainelDevolucao();
            carregarDocumentosElegiveisDevolucao();
            carregarRelatorio();
        }

        let devolucaoFornecedorCompraId = null;

        async function carregarComprasElegiveisDevolucao() {
            const resp = await fetch(`${apiBase}/compras-elegiveis-devolucao`);
            const lista = await resp.json();
            const tbody = document.getElementById('tbody-devolucao-fornecedor-compras');
            if (!lista.length) { tbody.innerHTML = '<tr><td colspan="5">Nenhuma compra confirmada disponível para devolução.</td></tr>'; return; }
            tbody.innerHTML = lista.map(c => `
                <tr>
                    <td>#${c.id}${c.numero_nota ? ' (NF ' + c.numero_nota + ')' : ''}</td>
                    <td>${c.fornecedor || 'Não identificado'}</td>
                    <td>R$ ${Number(c.total).toFixed(2)}</td>
                    <td>${new Date(c.data_entrada).toLocaleDateString('pt-BR')}</td>
                    <td><button onclick="abrirPainelDevolucaoFornecedor(${c.id})">Devolver</button></td>
                </tr>
            `).join('');
        }

        async function abrirPainelDevolucaoFornecedor(compraId) {
            devolucaoFornecedorCompraId = compraId;
            document.getElementById('devolucao-fornecedor-compra-numero').textContent = compraId;
            document.getElementById('painel-devolucao-fornecedor-itens').style.display = 'block';
            document.getElementById('msg-devolucao-fornecedor-confirmar').textContent = '';

            const resp = await fetch(`${apiBase}/compras/${compraId}/itens-disponiveis-devolucao`);
            const itens = await resp.json();
            const tbody = document.getElementById('tbody-devolucao-fornecedor-itens');
            if (!itens.length) { tbody.innerHTML = '<tr><td colspan="6">Nenhum item disponível.</td></tr>'; return; }
            tbody.innerHTML = itens.map(i => `
                <tr>
                    <td><input type="checkbox" class="devolucao-fornecedor-item-check" data-item-compra-id="${i.item_compra_id}"></td>
                    <td>${i.produto || '-'}</td>
                    <td>${i.quantidade_comprada}</td>
                    <td>${i.quantidade_ja_devolvida}</td>
                    <td>${i.quantidade_disponivel}</td>
                    <td><input type="number" step="0.001" min="0" max="${i.quantidade_disponivel}" class="devolucao-fornecedor-item-qtd" data-item-compra-id="${i.item_compra_id}" style="width:90px" ${i.quantidade_disponivel <= 0 ? 'disabled' : ''}></td>
                </tr>
            `).join('');
        }

        function fecharPainelDevolucaoFornecedor() {
            devolucaoFornecedorCompraId = null;
            document.getElementById('painel-devolucao-fornecedor-itens').style.display = 'none';
        }

        async function confirmarDevolucaoFornecedor() {
            const itens = Array.from(document.querySelectorAll('.devolucao-fornecedor-item-check'))
                .filter(chk => chk.checked)
                .map(chk => {
                    const itemCompraId = Number(chk.dataset.itemCompraId);
                    const input = document.querySelector(`.devolucao-fornecedor-item-qtd[data-item-compra-id="${itemCompraId}"]`);
                    return { item_compra_id: itemCompraId, quantidade: Number(input.value) };
                });

            const msg = document.getElementById('msg-devolucao-fornecedor-confirmar');
            if (!itens.length) { msg.className = 'msg erro'; msg.textContent = 'Selecione ao menos um item.'; return; }

            const resp = await fetch(`${apiBase}/compras/${devolucaoFornecedorCompraId}/devolucao`, {
                method: 'POST',
                headers: headersJson,
                body: JSON.stringify({ itens }),
            });
            const dados = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = `NFe de devolução emitida: status ${dados.status}.`;
            fecharPainelDevolucaoFornecedor();
            carregarComprasElegiveisDevolucao();
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
        carregarDocumentosElegiveisDevolucao();
        carregarComprasElegiveisDevolucao();
    </script>
</body>
</html>
