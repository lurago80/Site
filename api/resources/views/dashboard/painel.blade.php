<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard — {{ $empresaSlug }}</title>
    <link rel="stylesheet" href="/css/sistema.css">
    <style>
        .layout { display: flex; min-height: 100vh; }
        .sidebar .logo { padding: 0 16px 12px; }
        .sidebar .logo img { height: 28px; }
        .conteudo { flex: 1; padding: 24px; }
        .secao { display: none; }
        .secao.ativa { display: block; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat { background: var(--cor-superficie); border-radius: var(--raio); padding: 14px; box-shadow: var(--sombra); }
        .stat .label { font-size: 11px; color: var(--cor-texto-suave); }
        .stat .valor { font-size: 22px; font-weight: 700; margin-top: 4px; color: var(--cor-primaria); }
        button.acao { background: var(--cor-primaria); color: #fff; border: none; }
        button.acao:hover { background: var(--cor-primaria-escura); }
        button.secundario { color: #fff; }
    </style>
</head>
<body>
    <div class="layout">
        <div class="sidebar">
            <div class="logo"><img src="/images/logo.jpg" alt="Logo"></div>
            <div class="empresa">{{ $empresaSlug }}</div>
            <button class="ativo" onclick="mostrarSecao('dashboard', this)">Dashboard</button>
            <button onclick="mostrarSecao('agenda', this)">Agenda de Visitas</button>
            <button onclick="mostrarSecao('produtos', this)">Produtos</button>
            <button onclick="mostrarSecao('clientes', this)">Clientes</button>
            <button onclick="mostrarSecao('fornecedores', this)">Fornecedores</button>
            <button onclick="mostrarSecao('vendedores', this)">Vendedores</button>
            <button onclick="mostrarSecao('financeiro', this)">Financeiro</button>
            <button onclick="mostrarSecao('usuarios', this)">Usuários</button>
            <button onclick="mostrarSecao('config-fiscal', this)">Config. Fiscal</button>
            <button onclick="mostrarSecao('pagamentos', this)">Pagamentos</button>
            <button onclick="mostrarSecao('whatsapp', this)">WhatsApp</button>
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
                    <input type="hidden" id="pr-id">
                    <div class="linha-form">
                        <div><label>Código/SKU</label><input type="text" id="pr-codigo" style="width:100px"></div>
                        <div><label>Nome</label><input type="text" id="pr-nome"></div>
                        <div><label>Categoria</label><input type="text" id="pr-categoria" style="width:120px"></div>
                        <div><label>Tipo</label><select id="pr-tipo"><option value="fisico">Físico</option><option value="agendamento">Agendamento</option></select></div>
                        <div><label>Unidade</label><input type="text" id="pr-unidade" value="UN" style="width:60px"></div>
                    </div>
                    <div class="linha-form">
                        <div><label>Preço de venda (R$)</label><input type="number" step="0.01" id="pr-preco" style="width:110px"></div>
                        <div><label>Preço de custo (R$)</label><input type="number" step="0.01" id="pr-custo" style="width:110px"></div>
                        <div><label>Estoque</label><input type="number" id="pr-estoque" style="width:80px"></div>
                        <div><label>Fornecedor</label><select id="pr-fornecedor"><option value="">Nenhum</option></select></div>
                        <div><label>NCM</label><input type="text" id="pr-ncm" placeholder="8 dígitos" style="width:100px"></div>
                        <div><label>CFOP (interno)</label><input type="text" id="pr-cfop" placeholder="ex: 5102" style="width:90px"></div>
                    </div>
                    <div class="linha-form">
                        <div style="flex:1"><label>Descrição</label><input type="text" id="pr-descricao" style="width:100%"></div>
                        <div><button class="acao" id="pr-botao" onclick="salvarProduto()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioProduto()" style="display:none;" id="pr-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Tipo</th><th>Preço</th><th>Estoque</th><th>Fornecedor</th><th>NCM</th><th>CFOP</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-produtos"></tbody>
                    </table>
                    <p class="msg" id="msg-produtos"></p>
                </div>
            </section>

            <section id="secao-clientes" class="secao">
                <h1>Clientes</h1>
                <p style="font-size:12px; color:var(--cor-texto-suave);">
                    Endereço completo é obrigatório para emitir NFe (modelo 55) para o cliente - a loja pública e o PDV só coletam nome/CPF na hora da venda.
                </p>
                <div class="card">
                    <input type="hidden" id="cl-id">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="cl-nome"></div>
                        <div><label>CPF/CNPJ</label><input type="text" id="cl-cpf-cnpj" style="width:140px"></div>
                        <div><label>Telefone</label><input type="text" id="cl-telefone" style="width:120px"></div>
                        <div><label>E-mail</label><input type="email" id="cl-email"></div>
                        <div><label><input type="checkbox" id="cl-lgpd"> Consentimento LGPD</label></div>
                    </div>
                    <div class="linha-form">
                        <div><label>CEP</label><input type="text" id="cl-cep" style="width:90px"></div>
                        <div><label>Logradouro</label><input type="text" id="cl-logradouro"></div>
                        <div><label>Número</label><input type="text" id="cl-numero" style="width:70px"></div>
                        <div><label>Bairro</label><input type="text" id="cl-bairro"></div>
                        <div><label>Município</label><input type="text" id="cl-municipio"></div>
                        <div><label>UF</label><input type="text" id="cl-uf" style="width:50px" maxlength="2"></div>
                        <div><label>Cód. IBGE</label><input type="text" id="cl-ibge" style="width:80px"></div>
                        <div><label>IE</label><input type="text" id="cl-ie" style="width:100px"></div>
                    </div>
                    <div class="linha-form">
                        <div><button class="acao" id="cl-botao" onclick="salvarCliente()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioCliente()" style="display:none;" id="cl-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>E-mail</th><th>Telefone</th><th>Endereço</th><th>LGPD</th><th></th></tr></thead>
                        <tbody id="tbody-clientes"></tbody>
                    </table>
                    <p class="msg" id="msg-clientes"></p>
                </div>
            </section>

            <section id="secao-fornecedores" class="secao">
                <h1>Fornecedores</h1>
                <div class="card">
                    <input type="hidden" id="fo-id">
                    <div class="linha-form">
                        <div><label>Razão social</label><input type="text" id="fo-razao"></div>
                        <div><label>Nome fantasia</label><input type="text" id="fo-fantasia"></div>
                        <div><label>CNPJ</label><input type="text" id="fo-cnpj" style="width:140px"></div>
                        <div><label>IE</label><input type="text" id="fo-ie" style="width:100px"></div>
                    </div>
                    <div class="linha-form">
                        <div><label>Contato</label><input type="text" id="fo-contato"></div>
                        <div><label>Telefone</label><input type="text" id="fo-telefone" style="width:120px"></div>
                        <div><label>E-mail</label><input type="email" id="fo-email"></div>
                        <div style="flex:1"><label>Endereço</label><input type="text" id="fo-endereco" style="width:100%"></div>
                    </div>
                    <div class="linha-form">
                        <div><button class="acao" id="fo-botao" onclick="salvarFornecedor()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioFornecedor()" style="display:none;" id="fo-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Razão social</th><th>CNPJ</th><th>Contato</th><th>Telefone</th><th></th></tr></thead>
                        <tbody id="tbody-fornecedores"></tbody>
                    </table>
                    <p class="msg" id="msg-fornecedores"></p>
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

            <section id="secao-config-fiscal" class="secao">
                <h1>Configuração Fiscal</h1>

                <div class="card">
                    <h2>Emitente</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Razão social e CNPJ não são editáveis aqui - fale com o suporte para corrigi-los.
                    </p>
                    <p style="font-size:13px;"><strong id="cf-razao-social"></strong> — CNPJ <span id="cf-cnpj"></span></p>
                    <div class="linha-form">
                        <div><label>CEP</label><input type="text" id="cf-cep" style="width:90px"></div>
                        <div><label>Logradouro</label><input type="text" id="cf-logradouro"></div>
                        <div><label>Número</label><input type="text" id="cf-numero" style="width:70px"></div>
                        <div><label>Bairro</label><input type="text" id="cf-bairro"></div>
                        <div><label>Município</label><input type="text" id="cf-municipio"></div>
                        <div><label>UF</label><input type="text" id="cf-uf" style="width:50px" maxlength="2"></div>
                        <div><label>Cód. IBGE município</label><input type="text" id="cf-ibge" style="width:100px"></div>
                    </div>
                    <div class="linha-form">
                        <div><label>Regime tributário (CRT)</label>
                            <select id="cf-crt">
                                <option value="1">1 - Simples Nacional</option>
                                <option value="2">2 - Simples Nacional - excesso sublimite</option>
                                <option value="3">3 - Regime Normal</option>
                            </select>
                        </div>
                        <div><label>Inscrição Estadual</label><input type="text" id="cf-ie" style="width:120px"></div>
                        <div><label>Inscrição Municipal</label><input type="text" id="cf-im" style="width:120px"></div>
                        <div><label>Ambiente</label>
                            <select id="cf-ambiente">
                                <option value="homologacao">Homologação</option>
                                <option value="producao">Produção</option>
                            </select>
                        </div>
                    </div>
                    <div class="linha-form">
                        <div><label>CSC (NFC-e)</label><input type="text" id="cf-csc"></div>
                        <div><label>ID do token CSC</label><input type="text" id="cf-csc-id" style="width:100px"></div>
                        <div><button class="acao" onclick="salvarConfigFiscal()">Salvar</button></div>
                    </div>
                    <p class="msg" id="msg-config-fiscal"></p>
                </div>

                <div class="card">
                    <h2>Certificado Digital</h2>
                    <p id="cert-status" style="font-size:13px;">Carregando...</p>
                    <div class="linha-form">
                        <div><label>Arquivo (.pfx)</label><input type="file" id="cert-arquivo" accept=".pfx,.p12"></div>
                        <div><label>Senha</label><input type="password" id="cert-senha" style="width:160px"></div>
                        <div><label>Tipo</label>
                            <select id="cert-tipo"><option value="A1">A1</option><option value="A3">A3</option></select>
                        </div>
                        <div><button class="acao" onclick="salvarCertificado()">Enviar certificado</button></div>
                    </div>
                    <p style="font-size:11px; color:var(--cor-texto-suave);">
                        A senha é criptografada no banco (nunca fica em texto puro) e a validade é lida direto do
                        certificado - não precisa digitar. O arquivo é validado antes de salvar.
                    </p>
                    <p class="msg" id="msg-certificado"></p>
                </div>
            </section>

            <section id="secao-pagamentos" class="secao">
                <h1>Pagamentos</h1>

                <div class="card">
                    <h2>Formas de pagamento</h2>
                    <div class="linha-form">
                        <div><label>Descrição</label><input type="text" id="fp-descricao"></div>
                        <div><label>Tipo</label>
                            <select id="fp-tipo">
                                <option value="dinheiro">Dinheiro</option>
                                <option value="pix">Pix</option>
                                <option value="cartao_credito">Cartão crédito</option>
                                <option value="cartao_debito">Cartão débito</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div><label>Código tPag (NFe)</label><input type="text" id="fp-codigo" style="width:70px" maxlength="2" placeholder="ex: 17"></div>
                        <div><button class="acao" onclick="criarFormaPagamento()">Cadastrar</button></div>
                    </div>
                    <p style="font-size:11px; color:var(--cor-texto-suave); margin-top:0;">
                        Código tPag: 01=dinheiro, 03=cartão crédito, 04=cartão débito, 17=Pix, 99=outros - usado na nota fiscal.
                    </p>
                    <table>
                        <thead><tr><th>Descrição</th><th>Tipo</th><th>Código tPag</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-formas-pagamento"></tbody>
                    </table>
                    <p class="msg" id="msg-formas-pagamento"></p>
                </div>

                <div class="card">
                    <h2>Gateway de pagamento (Pix/cartão online)</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Cada empresa pode usar o gateway com a melhor taxa negociada. Sem configurar aqui, o checkout
                        da loja pública funciona em modo simulado (aprova na hora, sem cobrar de verdade).
                    </p>
                    <p id="pg-status" style="font-size:13px;">Carregando...</p>
                    <div class="linha-form">
                        <div><label>Gateway</label>
                            <select id="pg-gateway">
                                <option value="mercadopago">Mercado Pago</option>
                                <option value="pagseguro">PagSeguro</option>
                                <option value="cielo">Cielo</option>
                            </select>
                        </div>
                        <div><label>Ambiente</label>
                            <select id="pg-ambiente">
                                <option value="sandbox">Sandbox (testes)</option>
                                <option value="producao">Produção</option>
                            </select>
                        </div>
                        <div><label><input type="checkbox" id="pg-ativo"> Ativo</label></div>
                    </div>
                    <div class="linha-form">
                        <div style="flex:1"><label>Access Token / Client Secret</label><input type="password" id="pg-token" placeholder="Deixe em branco para manter o atual"></div>
                        <div><label>Public Key / Client ID</label><input type="text" id="pg-public-key"></div>
                        <div><button class="acao" onclick="salvarConfigPagamento()">Salvar</button></div>
                    </div>
                    <p class="msg" id="msg-config-pagamento"></p>
                </div>
            </section>

            <section id="secao-whatsapp" class="secao">
                <h1>WhatsApp</h1>

                <div class="card">
                    <h2>Provedor de notificação</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Confirmação de agendamento e lembrete de visita (dia anterior) via WhatsApp. Z-API é pago,
                        mas é uma API oficial simples de configurar. Baileys é gratuito, porém usa um número comum
                        via QR code e fica fora dos Termos de Uso do WhatsApp (risco de banimento do número) -
                        ainda não disponível nesta versão, exige um serviço à parte. Sem configurar aqui, as
                        notificações ficam em modo simulado (só registradas, não enviadas de verdade).
                    </p>
                    <p id="wa-status" style="font-size:13px;">Carregando...</p>
                    <div class="linha-form">
                        <div><label>Provedor</label>
                            <select id="wa-provider">
                                <option value="zapi">Z-API (pago)</option>
                                <option value="baileys">Baileys (gratuito - em breve)</option>
                            </select>
                        </div>
                        <div><label><input type="checkbox" id="wa-ativo"> Ativo</label></div>
                    </div>
                    <div class="linha-form">
                        <div><label>Instance ID (Z-API)</label><input type="text" id="wa-instance-id"></div>
                        <div style="flex:1"><label>Token</label><input type="password" id="wa-token" placeholder="Deixe em branco para manter o atual"></div>
                        <div style="flex:1"><label>Client-Token</label><input type="password" id="wa-client-token" placeholder="Deixe em branco para manter o atual"></div>
                        <div><button class="acao" onclick="salvarConfigWhatsapp()">Salvar</button></div>
                    </div>
                    <p class="msg" id="msg-config-whatsapp"></p>
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
            fornecedores: carregarFornecedores,
            vendedores: carregarVendedores,
            financeiro: () => { carregarContasPagar(); carregarContasReceber(); },
            usuarios: carregarUsuarios,
            'config-fiscal': () => { carregarConfigFiscal(); carregarCertificado(); },
            pagamentos: () => { carregarFormasPagamento(); carregarConfigPagamento(); },
            whatsapp: () => { carregarConfigWhatsapp(); },
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

        let produtosCache = [];
        let fornecedoresCache = [];

        async function carregarProdutos() {
            await carregarFornecedoresParaSelect();
            const resp = await fetch(`${base}/produtos`);
            produtosCache = await resp.json();
            document.getElementById('tbody-produtos').innerHTML = produtosCache.map(p => `
                <tr>
                    <td>${p.codigo ?? '-'}</td>
                    <td>${p.nome}</td>
                    <td>${p.categoria ?? '-'}</td>
                    <td>${p.tipo}</td>
                    <td>R$ ${Number(p.preco_venda).toFixed(2)}</td>
                    <td>${p.estoque_atual ?? '-'}</td>
                    <td>${p.fornecedor ? p.fornecedor.razao_social : '-'}</td>
                    <td>${p.ncm ?? '-'}</td>
                    <td>${p.cfop_padrao ?? '-'}</td>
                    <td>${p.ativo ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="editarProduto(${p.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="11">Nenhum produto cadastrado.</td></tr>';
        }

        async function carregarFornecedoresParaSelect() {
            const resp = await fetch(`${base}/fornecedores`);
            fornecedoresCache = await resp.json();
            document.getElementById('pr-fornecedor').innerHTML = '<option value="">Nenhum</option>' +
                fornecedoresCache.map(f => `<option value="${f.id}">${f.razao_social}</option>`).join('');
        }

        function editarProduto(id) {
            const p = produtosCache.find(x => x.id === id);
            if (!p) return;
            document.getElementById('pr-id').value = p.id;
            document.getElementById('pr-codigo').value = p.codigo ?? '';
            document.getElementById('pr-nome').value = p.nome;
            document.getElementById('pr-categoria').value = p.categoria ?? '';
            document.getElementById('pr-tipo').value = p.tipo;
            document.getElementById('pr-unidade').value = p.unidade ?? 'UN';
            document.getElementById('pr-preco').value = p.preco_venda;
            document.getElementById('pr-custo').value = p.preco_custo ?? '';
            document.getElementById('pr-estoque').value = p.estoque_atual ?? '';
            document.getElementById('pr-fornecedor').value = p.fornecedor_id ?? '';
            document.getElementById('pr-ncm').value = p.ncm ?? '';
            document.getElementById('pr-cfop').value = p.cfop_padrao ?? '';
            document.getElementById('pr-descricao').value = p.descricao ?? '';
            document.getElementById('pr-botao').textContent = 'Salvar edição';
            document.getElementById('pr-cancelar').style.display = 'inline-block';
            document.getElementById('secao-produtos').scrollIntoView({ behavior: 'smooth' });
        }

        function limparFormularioProduto() {
            document.getElementById('pr-id').value = '';
            ['pr-codigo', 'pr-nome', 'pr-categoria', 'pr-custo', 'pr-estoque', 'pr-ncm', 'pr-cfop', 'pr-descricao']
                .forEach(id => document.getElementById(id).value = '');
            document.getElementById('pr-preco').value = '';
            document.getElementById('pr-unidade').value = 'UN';
            document.getElementById('pr-fornecedor').value = '';
            document.getElementById('pr-botao').textContent = 'Cadastrar';
            document.getElementById('pr-cancelar').style.display = 'none';
        }

        async function salvarProduto() {
            const id = document.getElementById('pr-id').value;
            const dados = {
                codigo: document.getElementById('pr-codigo').value || null,
                nome: document.getElementById('pr-nome').value,
                categoria: document.getElementById('pr-categoria').value || null,
                tipo: document.getElementById('pr-tipo').value,
                unidade: document.getElementById('pr-unidade').value || 'UN',
                preco_venda: Number(document.getElementById('pr-preco').value),
                preco_custo: document.getElementById('pr-custo').value || null,
                estoque_atual: document.getElementById('pr-estoque').value || null,
                fornecedor_id: document.getElementById('pr-fornecedor').value || null,
                ncm: document.getElementById('pr-ncm').value || null,
                cfop_padrao: document.getElementById('pr-cfop').value || null,
                descricao: document.getElementById('pr-descricao').value || null,
            };
            const url = id ? `${base}/produtos/${id}` : `${base}/produtos`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-produtos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Produto atualizado.' : 'Produto cadastrado.';
            limparFormularioProduto();
            carregarProdutos();
        }

        let clientesCache = [];

        async function carregarClientes() {
            const resp = await fetch(`${base}/clientes`);
            clientesCache = await resp.json();
            document.getElementById('tbody-clientes').innerHTML = clientesCache.map(c => `
                <tr>
                    <td>${c.nome}</td>
                    <td>${c.cpf_cnpj ?? '-'}</td>
                    <td>${c.email ?? '-'}</td>
                    <td>${c.telefone ?? '-'}</td>
                    <td>${c.logradouro ? `${c.logradouro}, ${c.numero} - ${c.municipio}/${c.uf}` : '<em>incompleto</em>'}</td>
                    <td>${c.consentimento_lgpd ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="editarCliente(${c.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhum cliente cadastrado.</td></tr>';
        }

        function editarCliente(id) {
            const c = clientesCache.find(x => x.id === id);
            if (!c) return;
            document.getElementById('cl-id').value = c.id;
            document.getElementById('cl-nome').value = c.nome;
            document.getElementById('cl-cpf-cnpj').value = c.cpf_cnpj ?? '';
            document.getElementById('cl-telefone').value = c.telefone ?? '';
            document.getElementById('cl-email').value = c.email ?? '';
            document.getElementById('cl-lgpd').checked = !!c.consentimento_lgpd;
            document.getElementById('cl-cep').value = c.cep ?? '';
            document.getElementById('cl-logradouro').value = c.logradouro ?? '';
            document.getElementById('cl-numero').value = c.numero ?? '';
            document.getElementById('cl-bairro').value = c.bairro ?? '';
            document.getElementById('cl-municipio').value = c.municipio ?? '';
            document.getElementById('cl-uf').value = c.uf ?? '';
            document.getElementById('cl-ibge').value = c.codigo_ibge_municipio ?? '';
            document.getElementById('cl-ie').value = c.inscricao_estadual ?? '';
            document.getElementById('cl-botao').textContent = 'Salvar edição';
            document.getElementById('cl-cancelar').style.display = 'inline-block';
            document.getElementById('secao-clientes').scrollIntoView({ behavior: 'smooth' });
        }

        function limparFormularioCliente() {
            document.getElementById('cl-id').value = '';
            ['cl-nome', 'cl-cpf-cnpj', 'cl-telefone', 'cl-email', 'cl-cep', 'cl-logradouro',
             'cl-numero', 'cl-bairro', 'cl-municipio', 'cl-uf', 'cl-ibge', 'cl-ie']
                .forEach(id => document.getElementById(id).value = '');
            document.getElementById('cl-lgpd').checked = false;
            document.getElementById('cl-botao').textContent = 'Cadastrar';
            document.getElementById('cl-cancelar').style.display = 'none';
        }

        async function salvarCliente() {
            const id = document.getElementById('cl-id').value;
            const dados = {
                nome: document.getElementById('cl-nome').value,
                cpf_cnpj: document.getElementById('cl-cpf-cnpj').value || null,
                telefone: document.getElementById('cl-telefone').value || null,
                email: document.getElementById('cl-email').value || null,
                consentimento_lgpd: document.getElementById('cl-lgpd').checked,
                cep: document.getElementById('cl-cep').value || null,
                logradouro: document.getElementById('cl-logradouro').value || null,
                numero: document.getElementById('cl-numero').value || null,
                bairro: document.getElementById('cl-bairro').value || null,
                municipio: document.getElementById('cl-municipio').value || null,
                uf: document.getElementById('cl-uf').value || null,
                codigo_ibge_municipio: document.getElementById('cl-ibge').value || null,
                inscricao_estadual: document.getElementById('cl-ie').value || null,
            };
            const url = id ? `${base}/clientes/${id}` : `${base}/clientes`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-clientes');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Cliente atualizado.' : 'Cliente cadastrado.';
            limparFormularioCliente();
            carregarClientes();
        }

        let fornecedoresListaCache = [];

        async function carregarFornecedores() {
            const resp = await fetch(`${base}/fornecedores`);
            fornecedoresListaCache = await resp.json();
            document.getElementById('tbody-fornecedores').innerHTML = fornecedoresListaCache.map(f => `
                <tr>
                    <td>${f.razao_social}</td>
                    <td>${f.cnpj ?? '-'}</td>
                    <td>${f.contato ?? '-'}</td>
                    <td>${f.telefone ?? '-'}</td>
                    <td><button class="secundario" onclick="editarFornecedor(${f.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum fornecedor cadastrado.</td></tr>';
        }

        function editarFornecedor(id) {
            const f = fornecedoresListaCache.find(x => x.id === id);
            if (!f) return;
            document.getElementById('fo-id').value = f.id;
            document.getElementById('fo-razao').value = f.razao_social;
            document.getElementById('fo-fantasia').value = f.nome_fantasia ?? '';
            document.getElementById('fo-cnpj').value = f.cnpj ?? '';
            document.getElementById('fo-ie').value = f.inscricao_estadual ?? '';
            document.getElementById('fo-contato').value = f.contato ?? '';
            document.getElementById('fo-telefone').value = f.telefone ?? '';
            document.getElementById('fo-email').value = f.email ?? '';
            document.getElementById('fo-endereco').value = f.endereco ?? '';
            document.getElementById('fo-botao').textContent = 'Salvar edição';
            document.getElementById('fo-cancelar').style.display = 'inline-block';
            document.getElementById('secao-fornecedores').scrollIntoView({ behavior: 'smooth' });
        }

        function limparFormularioFornecedor() {
            document.getElementById('fo-id').value = '';
            ['fo-razao', 'fo-fantasia', 'fo-cnpj', 'fo-ie', 'fo-contato', 'fo-telefone', 'fo-email', 'fo-endereco']
                .forEach(id => document.getElementById(id).value = '');
            document.getElementById('fo-botao').textContent = 'Cadastrar';
            document.getElementById('fo-cancelar').style.display = 'none';
        }

        async function salvarFornecedor() {
            const id = document.getElementById('fo-id').value;
            const dados = {
                razao_social: document.getElementById('fo-razao').value,
                nome_fantasia: document.getElementById('fo-fantasia').value || null,
                cnpj: document.getElementById('fo-cnpj').value || null,
                inscricao_estadual: document.getElementById('fo-ie').value || null,
                contato: document.getElementById('fo-contato').value || null,
                telefone: document.getElementById('fo-telefone').value || null,
                email: document.getElementById('fo-email').value || null,
                endereco: document.getElementById('fo-endereco').value || null,
            };
            const url = id ? `${base}/fornecedores/${id}` : `${base}/fornecedores`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-fornecedores');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Fornecedor atualizado.' : 'Fornecedor cadastrado.';
            limparFormularioFornecedor();
            carregarFornecedores();
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

        async function carregarConfigFiscal() {
            const resp = await fetch(`${base}/config-fiscal`);
            if (resp.status === 403) { return; }
            const dados = await resp.json();
            const e = dados.empresa;
            const c = dados.config_fiscal || {};
            document.getElementById('cf-razao-social').textContent = e.razao_social;
            document.getElementById('cf-cnpj').textContent = e.cnpj;
            document.getElementById('cf-cep').value = e.cep ?? '';
            document.getElementById('cf-logradouro').value = e.logradouro ?? '';
            document.getElementById('cf-numero').value = e.numero ?? '';
            document.getElementById('cf-bairro').value = e.bairro ?? '';
            document.getElementById('cf-municipio').value = e.municipio ?? '';
            document.getElementById('cf-uf').value = e.uf ?? '';
            document.getElementById('cf-ibge').value = e.codigo_ibge_municipio ?? '';
            document.getElementById('cf-crt').value = c.crt ?? '1';
            document.getElementById('cf-ie').value = c.inscricao_estadual ?? '';
            document.getElementById('cf-im').value = c.inscricao_municipal ?? '';
            document.getElementById('cf-ambiente').value = c.ambiente_ativo ?? 'homologacao';
            document.getElementById('cf-csc').value = c.csc_nfce ?? '';
            document.getElementById('cf-csc-id').value = c.id_token_csc ?? '';
        }

        async function salvarConfigFiscal() {
            const dados = {
                cep: document.getElementById('cf-cep').value || null,
                logradouro: document.getElementById('cf-logradouro').value || null,
                numero: document.getElementById('cf-numero').value || null,
                bairro: document.getElementById('cf-bairro').value || null,
                municipio: document.getElementById('cf-municipio').value || null,
                uf: document.getElementById('cf-uf').value || null,
                codigo_ibge_municipio: document.getElementById('cf-ibge').value || null,
                crt: document.getElementById('cf-crt').value,
                inscricao_estadual: document.getElementById('cf-ie').value || null,
                inscricao_municipal: document.getElementById('cf-im').value || null,
                ambiente_ativo: document.getElementById('cf-ambiente').value,
                csc_nfce: document.getElementById('cf-csc').value || null,
                id_token_csc: document.getElementById('cf-csc-id').value || null,
            };
            const resp = await fetch(`${base}/config-fiscal`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-fiscal');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Configuração fiscal salva.';
        }

        async function carregarCertificado() {
            const resp = await fetch(`${base}/certificado`);
            if (resp.status === 403) { return; }
            const dados = await resp.json();
            const status = document.getElementById('cert-status');
            if (!dados.cadastrado) { status.textContent = 'Nenhum certificado cadastrado ainda.'; return; }
            const validade = new Date(dados.validade).toLocaleDateString('pt-BR');
            status.innerHTML = dados.expirado
                ? `<span style="color:var(--cor-perigo-texto);">Certificado ${dados.tipo} EXPIRADO em ${validade}.</span>`
                : `Certificado ${dados.tipo} válido até ${validade}.`;
        }

        async function salvarCertificado() {
            const arquivo = document.getElementById('cert-arquivo').files[0];
            const msg = document.getElementById('msg-certificado');
            if (!arquivo) { msg.className = 'msg erro'; msg.textContent = 'Selecione o arquivo .pfx.'; return; }

            const formData = new FormData();
            formData.append('arquivo', arquivo);
            formData.append('senha', document.getElementById('cert-senha').value);
            formData.append('tipo', document.getElementById('cert-tipo').value);

            const resp = await fetch(`${base}/certificado`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData,
            });
            const resposta = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Certificado salvo com sucesso.';
            document.getElementById('cert-senha').value = '';
            carregarCertificado();
        }

        async function carregarFormasPagamento() {
            const resp = await fetch(`${base}/formas-pagamento`);
            const lista = await resp.json();
            document.getElementById('tbody-formas-pagamento').innerHTML = lista.map(f => `
                <tr>
                    <td>${f.descricao}</td>
                    <td>${f.tipo}</td>
                    <td>${f.codigo_tpag}</td>
                    <td>${f.ativo ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="alternarFormaPagamento(${f.id}, ${!f.ativo})">${f.ativo ? 'Desativar' : 'Ativar'}</button></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhuma forma de pagamento cadastrada.</td></tr>';
        }

        async function criarFormaPagamento() {
            const dados = {
                descricao: document.getElementById('fp-descricao').value,
                tipo: document.getElementById('fp-tipo').value,
                codigo_tpag: document.getElementById('fp-codigo').value,
            };
            const resp = await fetch(`${base}/formas-pagamento`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-formas-pagamento');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Forma de pagamento cadastrada.';
            document.getElementById('fp-descricao').value = '';
            document.getElementById('fp-codigo').value = '';
            carregarFormasPagamento();
        }

        async function alternarFormaPagamento(id, ativo) {
            await fetch(`${base}/formas-pagamento/${id}`, { method: 'PUT', headers: headersJson, body: JSON.stringify({ ativo }) });
            carregarFormasPagamento();
        }

        async function carregarConfigPagamento() {
            const resp = await fetch(`${base}/config-pagamento`);
            if (resp.status === 403) { return; }
            const dados = await resp.json();
            const status = document.getElementById('pg-status');
            if (!dados) { status.textContent = 'Nenhum gateway configurado ainda - checkout usa modo simulado.'; return; }
            document.getElementById('pg-gateway').value = dados.gateway;
            document.getElementById('pg-ambiente').value = dados.ambiente;
            document.getElementById('pg-ativo').checked = dados.ativo;
            document.getElementById('pg-public-key').value = dados.public_key ?? dados.client_id ?? '';
            status.textContent = dados.tem_credenciais
                ? `Gateway ${dados.gateway} configurado (${dados.ambiente}) - ${dados.ativo ? 'ativo' : 'inativo'}.`
                : `Gateway ${dados.gateway} selecionado, mas sem credenciais salvas ainda.`;
        }

        async function salvarConfigPagamento() {
            const dados = {
                gateway: document.getElementById('pg-gateway').value,
                ambiente: document.getElementById('pg-ambiente').value,
                ativo: document.getElementById('pg-ativo').checked,
                public_key: document.getElementById('pg-public-key').value || null,
                client_id: document.getElementById('pg-public-key').value || null,
            };
            const token = document.getElementById('pg-token').value;
            if (token) { dados.access_token = token; dados.client_secret = token; }

            const resp = await fetch(`${base}/config-pagamento`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-pagamento');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Configuração de pagamento salva.';
            document.getElementById('pg-token').value = '';
            carregarConfigPagamento();
        }

        async function carregarConfigWhatsapp() {
            const resp = await fetch(`${base}/config-whatsapp`);
            if (resp.status === 403) { return; }
            const dados = await resp.json();
            const status = document.getElementById('wa-status');
            if (!dados) { status.textContent = 'Nenhum provedor configurado ainda - notificações em modo simulado.'; return; }
            document.getElementById('wa-provider').value = dados.provider;
            document.getElementById('wa-ativo').checked = dados.ativo;
            document.getElementById('wa-instance-id').value = dados.instance_id ?? '';
            status.textContent = dados.tem_credenciais
                ? `Provedor ${dados.provider} configurado - ${dados.ativo ? 'ativo' : 'inativo'}.`
                : `Provedor ${dados.provider} selecionado, mas sem credenciais salvas ainda.`;
        }

        async function salvarConfigWhatsapp() {
            const dados = {
                provider: document.getElementById('wa-provider').value,
                ativo: document.getElementById('wa-ativo').checked,
                instance_id: document.getElementById('wa-instance-id').value || null,
            };
            const token = document.getElementById('wa-token').value;
            const clientToken = document.getElementById('wa-client-token').value;
            if (token) { dados.token = token; }
            if (clientToken) { dados.client_token = clientToken; }

            const resp = await fetch(`${base}/config-whatsapp`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-whatsapp');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Configuração de WhatsApp salva.';
            document.getElementById('wa-token').value = '';
            document.getElementById('wa-client-token').value = '';
            carregarConfigWhatsapp();
        }

        carregarIndicadores();
    </script>
</body>
</html>
