<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verificar Ticket — PDV</title>
    <style>
        :root {
            --bg: #131a22;
            --bg-elev: #1c2733;
            --bg-elev-2: #232f3d;
            --border: #2e3c4b;
            --text: #e7ebf0;
            --text-dim: #8f9bab;
            --accent: #6b76d6;
            --accent-hover: #7c86e0;
            --danger: #ff8080;
            --ok: #6cd67a;
            --warn: #e0b95c;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            margin: 0;
            color: var(--text);
            min-height: 100vh;
        }
        .topo {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 20px; background: var(--bg-elev); border-bottom: 1px solid var(--border);
        }
        .topo-marca { display: flex; align-items: center; gap: 12px; }
        .topo-marca img { height: 28px; border-radius: 4px; }
        h1 { font-size: 15px; margin: 0; font-weight: 600; }
        h1 span { color: var(--text-dim); font-weight: 400; }
        a.voltar { color: var(--text-dim); font-size: 12px; text-decoration: none; }
        a.voltar:hover { color: var(--text); }

        .conteudo { max-width: 560px; margin: 0 auto; padding: 30px 20px; }
        .busca-wrap { display: flex; gap: 10px; margin-bottom: 24px; }
        input, button {
            font-family: inherit; font-size: 14px; border-radius: 8px; border: 1px solid var(--border);
            padding: 12px 14px; background: var(--bg-elev-2); color: var(--text);
        }
        input { flex: 1; }
        button { cursor: pointer; background: var(--accent); border-color: var(--accent); font-weight: 600; }
        button:hover { background: var(--accent-hover); }
        button:disabled { opacity: .5; cursor: not-allowed; }
        button.secundario { background: transparent; border-color: var(--border); font-weight: 400; }
        button.secundario:hover { background: var(--bg-elev-2); }

        .msg { font-size: 13px; margin-bottom: 16px; }
        .msg.erro { color: var(--danger); }
        .msg.ok { color: var(--ok); }

        .card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 10px; padding: 20px; }
        .status-badge {
            display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .status-pago { background: rgba(108,214,122,.15); color: var(--ok); }
        .status-pendente { background: rgba(224,185,92,.15); color: var(--warn); }
        .status-checkin { background: rgba(107,118,214,.2); color: var(--accent-hover); }

        .linha-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
        .linha-item:last-child { border-bottom: none; }
        .titulo-secao { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-dim); font-weight: 700; margin: 16px 0 6px; }
        .rodape-total { display: flex; justify-content: space-between; font-weight: 700; font-size: 16px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); }
    </style>
</head>
<body>
    <div class="topo">
        <div class="topo-marca">
            <img src="{{ asset('images/logo.jpg') }}" alt="Logo">
            <h1>Verificar Ticket <span>· {{ $empresaSlug }}</span></h1>
        </div>
        <a class="voltar" href="{{ url('/pdv/'.$empresaSlug.'/caixa') }}">← Voltar ao PDV</a>
    </div>

    <div class="conteudo">
        <div class="busca-wrap">
            <input type="text" id="busca-pedido" placeholder="Número do pedido (ex.: 1234)" inputmode="numeric" autofocus>
            <button onclick="buscar()">Buscar</button>
        </div>

        <p class="msg" id="msg"></p>

        <div id="resultado"></div>
    </div>

    <script>
        const empresa = @json($empresaSlug);
        const base = `{{ url('/pdv') }}/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        document.getElementById('busca-pedido').addEventListener('keydown', (e) => { if (e.key === 'Enter') buscar(); });

        function formatarDataHora(iso) {
            return new Date(iso).toLocaleString('pt-BR');
        }

        async function buscar() {
            const id = document.getElementById('busca-pedido').value.trim();
            const msg = document.getElementById('msg');
            const resultadoDiv = document.getElementById('resultado');
            resultadoDiv.innerHTML = '';
            msg.className = 'msg';

            if (!id) { msg.className = 'msg erro'; msg.textContent = 'Informe o número do pedido.'; return; }

            msg.textContent = 'Buscando...';

            const resp = await fetch(`${base}/verificar/${id}`);
            if (!resp.ok) {
                const resposta = await resp.json().catch(() => ({}));
                msg.className = 'msg erro';
                msg.textContent = resposta.message || 'Pedido não encontrado.';
                return;
            }

            const venda = await resp.json();
            msg.textContent = '';
            renderizar(venda);
        }

        function renderizar(venda) {
            const pago = venda.status_pagamento === 'pago';
            const jaTeveCheckin = !!venda.check_in_em;

            const itensProduto = (venda.itens || []).filter(i => i.produto);
            const itensVisita = (venda.itens || []).filter(i => i.agenda_visitacao);

            let statusHtml = pago
                ? '<span class="status-badge status-pago">Pago</span>'
                : `<span class="status-badge status-pendente">${venda.status_pagamento}</span>`;
            if (jaTeveCheckin) {
                statusHtml += ` <span class="status-badge status-checkin">Entrada confirmada</span>`;
            }

            const itensHtml = `
                ${itensProduto.length ? '<div class="titulo-secao">Produtos</div>' + itensProduto.map(i => `
                    <div class="linha-item">
                        <span>${i.quantidade}x ${i.produto?.nome ?? ''}${i.produto_variacao ? ' (' + i.produto_variacao.tamanho + ')' : ''}</span>
                        <span>R$ ${Number(i.valor_total).toFixed(2)}</span>
                    </div>
                `).join('') : ''}
                ${itensVisita.length ? '<div class="titulo-secao">Visitas agendadas</div>' + itensVisita.map(i => `
                    <div class="linha-item">
                        <span>${i.quantidade}x visita${i.agenda_visitacao ? ' - ' + formatarDataHora(i.agenda_visitacao.data_hora) : ''}</span>
                        <span>R$ ${Number(i.valor_total).toFixed(2)}</span>
                    </div>
                `).join('') : ''}
            `;

            document.getElementById('resultado').innerHTML = `
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <strong>Pedido #${venda.id}</strong>
                        ${statusHtml}
                    </div>
                    ${venda.cliente ? `<p style="font-size:13px; color:var(--text-dim); margin:0 0 6px;">Cliente: ${venda.cliente.nome}</p>` : ''}
                    ${itensHtml}
                    <div class="rodape-total">
                        <span>Total</span>
                        <span>R$ ${Number(venda.valor_total).toFixed(2)}</span>
                    </div>
                    ${jaTeveCheckin
                        ? `<p class="msg ok" style="margin-top:16px;">Entrada já confirmada em ${formatarDataHora(venda.check_in_em)}${venda.check_in_usuario ? ' por ' + venda.check_in_usuario.name : ''}.</p>`
                        : pago
                            ? `<button style="width:100%; margin-top:16px;" onclick="confirmarEntrada(${venda.id})">Confirmar entrada</button>`
                            : `<p class="msg erro" style="margin-top:16px;">Este pedido ainda não está pago - não é possível confirmar a entrada.</p>`
                    }
                </div>
            `;
        }

        async function confirmarEntrada(id) {
            const msg = document.getElementById('msg');
            const resp = await fetch(`${base}/verificar/${id}/check-in`, { method: 'POST', headers: headersJson });
            const resposta = await resp.json();

            if (!resp.ok) {
                msg.className = 'msg erro';
                msg.textContent = resposta.message || 'Não foi possível confirmar a entrada.';
                return;
            }

            msg.className = 'msg ok';
            msg.textContent = 'Entrada confirmada com sucesso!';
            renderizar(resposta);
        }
    </script>
</body>
</html>
