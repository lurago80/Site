<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard — {{ $empresaSlug }}</title>
    <link rel="stylesheet" href="{{ asset('css/sistema.css') }}">
    <style>
        .layout { display: flex; min-height: 100vh; }
        .sidebar .logo { padding: 0 16px 12px; }
        .sidebar .logo img { height: 28px; }
        .sidebar .grupo-label { padding: 14px 16px 4px; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.45); }
        .sidebar .link-pdv { display: block; padding: 10px 16px; font-size: 13px; color: #fff; background: var(--cor-primaria); text-decoration: none; margin: 0 12px; border-radius: 6px; }
        .sidebar .link-pdv:hover { background: var(--cor-primaria-escura); }
        .abas-produto { display: flex; gap: 2px; border-bottom: 1px solid var(--cor-borda); margin-bottom: 24px; flex-wrap: wrap; }
        .abas-produto button { background: none; border: none; padding: 12px 16px; font-size: 12.5px; font-weight: 500; color: var(--cor-texto-suave); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color .15s, border-color .15s; }
        .abas-produto button:hover { color: var(--cor-primaria); }
        .abas-produto button.ativa { color: var(--cor-primaria); border-bottom-color: var(--cor-primaria); font-weight: 700; }
        .aba-conteudo-produto { display: none; }
        .aba-conteudo-produto.ativa { display: block; animation: aba-fade .12s ease-out; }
        @keyframes aba-fade { from { opacity: 0; } to { opacity: 1; } }
        .conteudo { flex: 1; padding: 28px; max-width: 1400px; }
        .secao { display: none; }
        .secao.ativa { display: block; }
        .secao > h1 { margin-bottom: 18px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat { background: var(--cor-superficie); border-radius: var(--raio); padding: 14px; box-shadow: var(--sombra); }
        .stat .label { font-size: 11px; color: var(--cor-texto-suave); }
        .stat .valor { font-size: 22px; font-weight: 700; margin-top: 4px; color: var(--cor-primaria); }
        button.acao { background: var(--cor-primaria); color: #fff; border: none; padding: 9px 18px; font-weight: 600; }
        button.acao:hover { background: var(--cor-primaria-escura); }
        button.secundario { color: #fff; }

        /* Ações de linha em tabelas: ícones compactos lado a lado */
        td.acoes-linha, th.acoes-linha { width: 1%; white-space: nowrap; text-align: right; }
        .acoes-linha .grupo { display: inline-flex; gap: 6px; }
        button.btn-icone { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 8px; border: 1px solid var(--cor-borda); background: var(--cor-superficie); color: var(--cor-texto-suave); transition: background .15s, color .15s, border-color .15s, box-shadow .15s; }
        button.btn-icone svg { width: 16px; height: 16px; pointer-events: none; }
        button.btn-icone:hover { background: var(--cor-primaria-clara); color: var(--cor-primaria); border-color: var(--cor-primaria); }
        button.btn-icone:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(57, 66, 133, .25); }
        button.btn-icone.perigo { background: var(--cor-superficie); color: var(--cor-perigo-texto); border-color: #f0b8b8; }
        button.btn-icone.perigo:hover { background: var(--cor-perigo-bg); border-color: var(--cor-perigo-texto); }
        button.btn-icone.perigo:focus-visible { box-shadow: 0 0 0 3px rgba(176, 37, 37, .25); }

        /* Selos de status/etiquetas */
        .selo { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 1.5; white-space: nowrap; vertical-align: middle; }
        .selo.ok { background: var(--cor-sucesso-bg); color: var(--cor-sucesso-texto); }
        .selo.off { background: var(--cor-secundaria-clara); color: var(--cor-secundaria); }
        .selo.info { background: var(--cor-primaria-clara); color: var(--cor-primaria); }
        .card > h1:first-child, .card > .abas-produto:first-child { margin-top: 0; }
        .card table { margin-top: 8px; }
    </style>
</head>
<body>
    <div class="layout">
        <div class="sidebar">
            <div class="logo"><img src="{{ asset('images/logo.jpg') }}" alt="Logo"></div>
            <div class="empresa">{{ $empresaSlug }}</div>
            <button class="ativo" onclick="mostrarSecao('dashboard', this)">Dashboard</button>

            <div class="grupo-label">PDV</div>
            <a class="link-pdv" href="{{ url("/pdv/{$empresaSlug}/caixa") }}" target="_blank">Abrir frente de caixa ↗</a>

            <div class="grupo-label">Loja virtual</div>
            <button onclick="mostrarSecao('pedidos-loja', this)">Pedidos da Loja</button>

            <div class="grupo-label">Cadastros</div>
            <button onclick="mostrarSecao('agenda', this)">Agenda de Visitas</button>
            <button onclick="mostrarSecao('produtos', this)">Produtos</button>
            <button onclick="mostrarSecao('grupos', this)">Grupos de Produto</button>
            <button onclick="mostrarSecao('clientes', this)">Clientes</button>
            <button onclick="mostrarSecao('fornecedores', this)">Fornecedores</button>
            <button onclick="mostrarSecao('compras', this)">Entrada de Notas</button>
            <button onclick="mostrarSecao('vendedores', this)">Vendedores</button>
            <button onclick="mostrarSecao('atendentes', this)">Atendentes</button>
            <button onclick="mostrarSecao('cupons', this)">Cupons de Desconto</button>
            <button onclick="mostrarSecao('descontos-pdv', this)">Descontos do PDV</button>

            <div class="grupo-label">Financeiro</div>
            <button onclick="mostrarSecao('financeiro', this)">Contas a Pagar/Receber</button>
            <button onclick="mostrarSecao('plano-contas', this)">Plano de Contas</button>
            <button onclick="mostrarSecao('bancos', this)">Bancos</button>
            <button onclick="mostrarSecao('caixa-consulta', this)">Caixa (consulta)</button>

            <div class="grupo-label">Fiscal</div>
            <button onclick="mostrarSecao('nfe', this)">Emitir NF-e</button>
            <button onclick="mostrarSecao('fiscal', this)">Emissão e Relatórios</button>
            <button onclick="mostrarSecao('config-fiscal', this)">Config. Fiscal</button>

            <div class="grupo-label">Configurações</div>
            <button onclick="mostrarSecao('parametros', this)">Parâmetros</button>
            <button onclick="mostrarSecao('usuarios', this)">Usuários</button>
            <button onclick="mostrarSecao('pagamentos', this)">Pagamentos</button>
            <button onclick="mostrarSecao('whatsapp', this)">WhatsApp</button>

            <form method="POST" action="{{ url('/logout') }}" style="padding: 16px;">
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
                    <input type="hidden" id="ag-id">
                    <div class="linha-form">
                        <div><label>Data/hora</label><input type="datetime-local" id="ag-data"></div>
                        <div><label>Vagas totais</label><input type="number" id="ag-vagas" style="width:100px"></div>
                        <div><label>Valor por visitante (R$)</label><input type="number" step="0.01" id="ag-valor" style="width:120px"></div>
                        <div><label>Vendedor</label><select id="ag-vendedor" style="min-width:160px"><option value="">Nenhum</option></select></div>
                        <div><label>Atendente</label><select id="ag-atendente" style="min-width:160px"><option value="">Nenhum</option></select></div>
                        <div><button class="acao" id="ag-botao" onclick="salvarAgenda()">Adicionar horário</button></div>
                        <div><button class="secundario" onclick="limparFormularioAgenda()" style="display:none;" id="ag-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Data/hora</th><th>Vagas</th><th>Valor</th><th>Vendedor</th><th>Atendente</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody-agenda"></tbody>
                    </table>
                    <p class="msg" id="msg-agenda"></p>
                </div>
            </section>

            <section id="secao-produtos" class="secao">
                <h1>Produtos</h1>
                <div class="card">
                    <input type="hidden" id="pr-id">

                    <div class="abas-produto">
                        <button type="button" class="ativa" onclick="mostrarAbaProduto('geral', this)">Geral</button>
                        <button type="button" onclick="mostrarAbaProduto('tamanhos', this)">Tamanhos</button>
                        <button type="button" onclick="mostrarAbaProduto('kit', this)">Kit</button>
                        <button type="button" onclick="mostrarAbaProduto('icms', this)">ICMS / PIS / COFINS / IPI</button>
                        <button type="button" onclick="mostrarAbaProduto('ibscbs', this)">IBS / CBS (novo regime)</button>
                        <button type="button" onclick="mostrarAbaProduto('is', this)">Imposto Seletivo</button>
                        <button type="button" onclick="mostrarAbaProduto('destino', this)">Destinação / Crédito</button>
                    </div>

                    <div class="aba-conteudo-produto ativa" id="aba-produto-geral">
                        <div class="grupo-campos">
                            <h3>Identificação</h3>
                            <div class="linha-form">
                                <div><label>Código/SKU</label><input type="text" id="pr-codigo" style="width:110px"></div>
                                <div><label>Código de barras</label><input type="text" id="pr-codigo-barras" style="width:140px"></div>
                                <div style="flex:2; min-width:220px"><label>Nome</label><input type="text" id="pr-nome"></div>
                                <div style="flex:1; min-width:140px"><label>Categoria</label><input type="text" id="pr-categoria"></div>
                                <div style="flex:1; min-width:140px"><label>Grupo</label><select id="pr-grupo"><option value="">Sem grupo</option></select></div>
                            </div>
                            <div class="linha-form">
                                <div style="min-width:140px"><label>Tipo</label><select id="pr-tipo"><option value="fisico">Físico</option><option value="agendamento">Agendamento</option></select></div>
                                <div style="min-width:180px"><label>Tipo de produto (fiscal)</label>
                                    <select id="pr-tipo-fiscal">
                                        <option value="produto">Produto</option>
                                        <option value="consumo">Consumo</option>
                                        <option value="materia_prima">Matéria-prima</option>
                                        <option value="servico">Serviço</option>
                                        <option value="brinde">Brinde</option>
                                    </select>
                                </div>
                                <div><label>Unidade</label><input type="text" id="pr-unidade" value="UN" style="width:70px"></div>
                                <div style="flex:1; min-width:160px"><label>Fornecedor</label><select id="pr-fornecedor"><option value="">Nenhum</option></select></div>
                                <label class="campo-check"><input type="checkbox" id="pr-pesavel"> Pesável</label>
                                <label class="campo-check"><input type="checkbox" id="pr-ativo" checked> Ativo</label>
                                <label class="campo-check"><input type="checkbox" id="pr-loja-virtual" checked> Loja virtual</label>
                                <label class="campo-check" title="Não aparece no PDV (frente de caixa); só pode ser vendido na loja virtual."><input type="checkbox" id="pr-somente-loja-virtual"> Somente loja virtual</label>
                            </div>
                        </div>

                        <div class="grupo-campos">
                            <h3>Preços e estoque</h3>
                            <div class="linha-form">
                                <div><label>Preço de venda (R$)</label><input type="number" step="0.01" id="pr-preco" style="width:120px"></div>
                                <div><label>Preço de custo (R$)</label><input type="number" step="0.01" id="pr-custo" style="width:120px"></div>
                                <div><label>Valor atacado (R$)</label><input type="number" step="0.01" id="pr-valor-atacado" style="width:120px"></div>
                                <div><label>Estoque atual</label><input type="number" id="pr-estoque" style="width:100px"></div>
                                <div><label>Estoque mínimo</label><input type="number" id="pr-estoque-minimo" style="width:100px"></div>
                                <div>
                                    <label>Venda mínima (un.)</label>
                                    <input type="number" id="pr-quantidade-minima-venda" min="1" style="width:110px" placeholder="Sem mínimo">
                                </div>
                            </div>
                            <p style="font-size:12px; color:#666; margin:4px 0 0">
                                Se o produto tiver variações (aba "Tamanhos"), a venda mínima soma todas elas - o cliente pode misturá-las
                                livremente (ex.: caixa fechada de 6 cervejas, escolhendo os sabores).
                            </p>
                        </div>

                        <div class="grupo-campos">
                            <h3>Dimensões e mídia</h3>
                            <div class="linha-form">
                                <div><label>Peso líquido (kg)</label><input type="number" step="0.001" id="pr-peso-liquido" style="width:110px"></div>
                                <div><label>Peso bruto (kg)</label><input type="number" step="0.001" id="pr-peso-bruto" style="width:110px"></div>
                            </div>
                            <div class="linha-form">
                                <div style="flex:1; min-width:280px">
                                    <label>Imagem do produto</label>
                                    <div style="display:flex; gap:12px; margin-bottom:6px">
                                        <label style="font-weight:normal"><input type="radio" name="pr-imagem-origem" id="pr-imagem-origem-url" value="url" checked> Informar URL</label>
                                        <label style="font-weight:normal"><input type="radio" name="pr-imagem-origem" id="pr-imagem-origem-arquivo" value="arquivo"> Enviar arquivo do computador</label>
                                    </div>
                                    <input type="text" id="pr-imagem" placeholder="https://...">
                                    <input type="file" id="pr-imagem-arquivo" accept="image/*" style="display:none">
                                    <img id="pr-imagem-preview" src="" alt="Pré-visualização" style="display:none; max-width:120px; max-height:120px; margin-top:8px; border-radius:4px; object-fit:cover">
                                </div>
                            </div>
                            <div class="linha-form">
                                <div style="flex:1"><label>Descrição</label><input type="text" id="pr-descricao"></div>
                            </div>
                        </div>
                    </div>

                    <div class="aba-conteudo-produto" id="aba-produto-tamanhos">
                        <div class="grupo-campos">
                            <h3>Tamanhos / variações (opcional)</h3>
                            <p style="font-size:13px; color:#666; margin:0 0 10px">
                                Use quando o produto tem variações diferentes (ex.: camisetas P, M, G, GG, ou sabores de cerveja
                                Pilsen, IPA, Weiss...), cada uma com estoque próprio.
                                Se o produto não tiver variações, deixe em branco e use o campo "Estoque atual" da aba Geral.
                            </p>
                            <p class="msg" id="msg-pr-tamanhos-aviso">Salve o produto primeiro para poder cadastrar os tamanhos.</p>
                            <div id="bloco-pr-tamanhos" style="display:none">
                                <table>
                                    <thead><tr><th>Tamanho</th><th>Estoque</th><th>Ativo</th><th></th></tr></thead>
                                    <tbody id="tbody-pr-tamanhos"></tbody>
                                </table>
                                <div class="linha-form" style="margin-top:10px">
                                    <div style="min-width:260px"><label>Vinculado a um produto (opcional)</label>
                                        <select id="pr-tam-novo-vinculo" onchange="vinculoTamanhoMudou()" style="width:100%"><option value="">Não - estoque próprio</option></select>
                                    </div>
                                    <div><label>Tamanho / sabor</label><input type="text" id="pr-tam-novo-tamanho" placeholder="P, M, G, GG ou Pilsen, IPA..." maxlength="40" style="width:170px"></div>
                                    <div><label>Estoque</label><input type="number" id="pr-tam-novo-estoque" min="0" value="0" style="width:90px"></div>
                                    <div style="align-self:flex-end"><button type="button" onclick="adicionarTamanhoProduto()">Adicionar tamanho</button></div>
                                </div>
                                <p style="font-size:12px; color:var(--cor-texto-suave); margin:6px 0 0;">
                                    Com <strong>vínculo</strong>, a opção é só a vitrine: o estoque, a baixa e o cadastro fiscal (NCM, CST) são do produto escolhido
                                    (ex.: a opção PILSEN da CERVEJA ARTESANAL baixa o estoque da CERVEJA PILSEN). O estoque digitado é ignorado.
                                </p>
                                <p class="msg" id="msg-pr-tamanhos"></p>
                            </div>
                        </div>
                    </div>

                    <div class="aba-conteudo-produto" id="aba-produto-kit">
                        <div class="grupo-campos">
                            <h3>Kit (opcional)</h3>
                            <p style="font-size:13px; color:#666; margin:0 0 10px">
                                Um kit é vendido <strong>só na loja virtual</strong>, por um preço fixo (campo "Preço de venda" da aba Geral).
                                Monte a composição abaixo: produto <em>sem variações</em> entra fixo no kit (ex.: 1 caneca); produto
                                <em>com variações</em> o cliente escolhe os sabores/tipos (ex.: 3 cervejas entre as opções, podendo repetir).
                                O estoque de cada item é baixado na venda. O kit não tem estoque próprio e não tem tamanhos.
                            </p>
                            <p class="msg" id="msg-pr-kit-aviso">Salve o produto primeiro para poder montar o kit.</p>
                            <div id="bloco-pr-kit" style="display:none">
                                <label style="font-weight:normal"><input type="checkbox" id="pr-eh-kit" onchange="alternarKitProduto()"> Este produto é um kit</label>
                                <div id="pr-kit-composicao" style="display:none; margin-top:10px;">
                                    <table>
                                        <thead><tr><th>Produto</th><th>Como entra no kit</th><th>Quantidade</th><th></th></tr></thead>
                                        <tbody id="tbody-pr-kit"></tbody>
                                    </table>
                                    <div style="margin-top:8px;"><button type="button" onclick="adicionarComponenteKit()">+ Adicionar item ao kit</button></div>
                                    <div style="margin-top:12px;">
                                        <label for="pr-kit-total-escolhas">Total de itens à escolha (opcional)</label>
                                        <input type="number" id="pr-kit-total-escolhas" min="1" max="99" style="width:80px" placeholder="-">
                                        <p style="font-size:12px; color:#666; margin:4px 0 0">
                                            Deixe em branco para que cada item com variações tenha a quantidade exata da tabela. Preenchido, o cliente monta
                                            esse total misturando os itens, e a "Quantidade" de cada item passa a ser o <strong>máximo</strong> dele
                                            (ex.: Kit Coringa = 3; Cerveja 3 + Copo 1 → 3 cervejas ou 2 cervejas + 1 copo).
                                        </p>
                                    </div>
                                </div>
                                <div style="margin-top:10px;"><button type="button" class="acao" onclick="salvarKitProduto()">Salvar kit</button></div>
                                <p class="msg" id="msg-pr-kit"></p>
                            </div>
                        </div>
                    </div>

                    <div class="aba-conteudo-produto" id="aba-produto-icms">
                        <div class="grupo-campos">
                        <h3>Classificação fiscal</h3>
                        <div class="linha-form">
                            <div><label>NCM</label><input type="text" id="pr-ncm" placeholder="8 dígitos" style="width:110px"></div>
                            <div><label>CEST</label><input type="text" id="pr-cest" style="width:110px"></div>
                            <div><label>CFOP padrão (interno)</label><input type="text" id="pr-cfop" placeholder="ex: 5102" style="width:110px"></div>
                            <div><label>CFOP interestadual</label><input type="text" id="pr-cfop-interestadual" placeholder="ex: 6102" style="width:130px"></div>
                        </div>
                        </div>
                        <div class="grupo-campos">
                        <h3>ICMS</h3>
                        <div class="linha-form">
                            <div><label>CST origem</label>
                                <select id="pr-cst-origem">
                                    <option value="">-</option>
                                    <option value="0">0 - Nacional</option>
                                    <option value="1">1 - Estrangeira (importação direta)</option>
                                    <option value="2">2 - Estrangeira (mercado interno)</option>
                                    <option value="3">3 - Nacional, conteúdo importação &gt; 40%</option>
                                    <option value="4">4 - Nacional, processo produtivo básico</option>
                                    <option value="5">5 - Nacional, conteúdo importação ≤ 40%</option>
                                    <option value="6">6 - Estrangeira, sem similar nacional (CAMEX)</option>
                                    <option value="7">7 - Estrangeira, mercado interno, sem similar (CAMEX)</option>
                                    <option value="8">8 - Nacional, conteúdo importação &gt; 70%</option>
                                </select>
                            </div>
                            <div><label>CST ICMS</label>
                                <select id="pr-cst-icms">
                                    <option value="">-</option>
                                    <option value="00">00 - Tributada integralmente</option>
                                    <option value="10">10 - Tributada com ICMS-ST</option>
                                    <option value="20">20 - Com redução de BC</option>
                                    <option value="30">30 - Isenta/não tributada com ICMS-ST</option>
                                    <option value="40">40 - Isenta</option>
                                    <option value="41">41 - Não tributada</option>
                                    <option value="50">50 - Suspensão</option>
                                    <option value="51">51 - Diferimento</option>
                                    <option value="60">60 - ICMS cobrado anteriormente por ST</option>
                                    <option value="70">70 - Redução de BC com ICMS-ST</option>
                                    <option value="90">90 - Outras</option>
                                </select>
                            </div>
                            <div><label>Alíquota ICMS (%)</label><input type="number" step="0.01" id="pr-aliquota-icms" style="width:90px"></div>
                            <div><label>Redução BC ICMS (%)</label><input type="number" step="0.01" id="pr-reducao-bc-icms" style="width:100px"></div>
                            <div><label>FCP (%)</label><input type="number" step="0.01" id="pr-fcp" style="width:80px"></div>
                            <div><label>MVA (%)</label><input type="number" step="0.01" id="pr-mva" style="width:80px"></div>
                        </div>
                        <div class="linha-form">
                            <div><label>Grupo fiscal</label><input type="text" id="pr-grupo-fiscal" style="width:150px"></div>
                            <div><label>Código benefício fiscal (cBenef)</label><input type="text" id="pr-codigo-beneficio" style="width:170px"></div>
                        </div>
                        </div>
                        <div class="grupo-campos">
                        <h3>PIS / COFINS</h3>
                        <div class="linha-form">
                            <div><label>CST PIS</label><input type="text" id="pr-cst-pis" placeholder="ex: 01" style="width:80px"></div>
                            <div><label>Alíquota PIS (%)</label><input type="number" step="0.01" id="pr-aliquota-pis" style="width:100px"></div>
                            <div><label>CST COFINS</label><input type="text" id="pr-cst-cofins" placeholder="ex: 01" style="width:90px"></div>
                            <div><label>Alíquota COFINS (%)</label><input type="number" step="0.01" id="pr-aliquota-cofins" style="width:110px"></div>
                            <div style="flex:1; min-width:200px"><label>Natureza da receita (monofásica)</label><input type="text" id="pr-natureza-receita"></div>
                        </div>
                        </div>
                        <div class="grupo-campos">
                        <h3>IPI</h3>
                        <div class="linha-form">
                            <div><label>CST IPI</label>
                                <select id="pr-cst-ipi">
                                    <option value="">-</option>
                                    <option value="50">50 - Saída tributada</option>
                                    <option value="51">51 - Saída tributável com alíquota zero</option>
                                    <option value="52">52 - Saída isenta</option>
                                    <option value="53">53 - Saída não-tributada</option>
                                    <option value="54">54 - Saída imune</option>
                                    <option value="55">55 - Saída com suspensão</option>
                                    <option value="99">99 - Outras saídas</option>
                                </select>
                            </div>
                            <div><label>Alíquota IPI (%)</label><input type="number" step="0.01" id="pr-aliquota-ipi" style="width:100px"></div>
                            <div><label>Código enquadramento IPI</label><input type="text" id="pr-enquadramento-ipi" style="width:170px"></div>
                        </div>
                        </div>
                    </div>

                    <div class="aba-conteudo-produto" id="aba-produto-ibscbs">
                        <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                            Reforma Tributária (LC 214/2025) — CST é um código único, compartilhado por IBS e CBS
                            (não existe "CST separado" para cada um).
                        </p>
                        <div class="grupo-campos">
                        <h3>Classificação</h3>
                        <div class="linha-form">
                            <div><label>Situação novo regime</label>
                                <select id="pr-situacao-novo-regime">
                                    <option value="0">0 - Não utiliza novo regime</option>
                                    <option value="1">1 - Transição (antigo + novo)</option>
                                    <option value="2">2 - Apenas novo regime</option>
                                </select>
                            </div>
                            <div><label>CST IBS/CBS</label>
                                <select id="pr-cst-ibscbs">
                                    <option value="">-</option>
                                    <option value="000">000 - Tributação integral</option>
                                    <option value="010">010 - Alíquotas uniformes</option>
                                    <option value="011">011 - Alíquotas uniformes reduzidas</option>
                                    <option value="200">200 - Alíquota reduzida</option>
                                    <option value="220">220 - Alíquota fixa</option>
                                    <option value="221">221 - Alíquota fixa proporcional</option>
                                    <option value="222">222 - Redução de base de cálculo</option>
                                    <option value="400">400 - Isenção</option>
                                    <option value="410">410 - Imunidade e não incidência</option>
                                    <option value="510">510 - Diferimento</option>
                                    <option value="515">515 - Diferimento com redução de alíquota</option>
                                    <option value="550">550 - Suspensão</option>
                                    <option value="620">620 - Tributação monofásica</option>
                                    <option value="800">800 - Transferência de crédito</option>
                                    <option value="810">810 - Ajuste de IBS na ZFM</option>
                                    <option value="811">811 - Ajustes</option>
                                    <option value="820">820 - Tributação em documento específico</option>
                                    <option value="830">830 - Exclusão da base de cálculo</option>
                                </select>
                            </div>
                            <div style="flex:1; min-width:200px"><label>cClassTrib</label><select id="pr-cclasstrib"><option value="">Selecione</option></select></div>
                        </div>
                        </div>
                        <div class="grupo-campos">
                        <h3>Alíquotas e créditos</h3>
                        <div class="linha-form">
                            <div><label>Alíquota IBS (%)</label><input type="number" step="0.01" id="pr-aliquota-ibs" style="width:100px"></div>
                            <div><label>Alíquota CBS (%)</label><input type="number" step="0.01" id="pr-aliquota-cbs" style="width:100px"></div>
                            <div><label>Redução BC IBS (%)</label><input type="number" step="0.01" id="pr-reducao-bc-ibs" style="width:110px"></div>
                            <div><label>Redução BC CBS (%)</label><input type="number" step="0.01" id="pr-reducao-bc-cbs" style="width:110px"></div>
                        </div>
                        <div class="linha-form">
                            <div><label>% Crédito IBS</label><input type="number" step="0.01" id="pr-credito-ibs" style="width:100px"></div>
                            <div><label>% Crédito CBS</label><input type="number" step="0.01" id="pr-credito-cbs" style="width:100px"></div>
                            <div style="flex:1; min-width:220px"><label>Código de Crédito Presumido (cCredPres) — opcional</label><select id="pr-ccredpres"><option value="">Nenhum</option></select></div>
                        </div>
                        </div>
                    </div>

                    <div class="aba-conteudo-produto" id="aba-produto-is">
                        <div class="grupo-campos">
                        <h3>Imposto Seletivo</h3>
                        <div class="linha-form">
                            <label class="campo-check"><input type="checkbox" id="pr-sujeito-is"> Sujeito a Imposto Seletivo</label>
                            <div><label>Tipo</label>
                                <select id="pr-tipo-is">
                                    <option value="">-</option>
                                    <option value="veiculos">Veículos</option>
                                    <option value="cigarros">Cigarros</option>
                                    <option value="bebidas_alcoolicas">Bebidas alcoólicas</option>
                                    <option value="bebidas_acucaradas">Bebidas açucaradas</option>
                                    <option value="combustiveis_fosseis">Combustíveis fósseis</option>
                                    <option value="bens_minerais">Bens minerais</option>
                                </select>
                            </div>
                            <div><label>cClassTrib IS</label><input type="text" id="pr-cclasstrib-is" style="width:120px"></div>
                            <div><label>Alíquota IS (%)</label><input type="number" step="0.01" id="pr-aliquota-is" style="width:100px"></div>
                        </div>
                        </div>
                    </div>

                    <div class="aba-conteudo-produto" id="aba-produto-destino">
                        <div class="grupo-campos">
                        <h3>Destinação / Crédito</h3>
                        <div class="linha-form">
                            <div><label>Destinação tributária</label>
                                <select id="pr-destinacao">
                                    <option value="">-</option>
                                    <option value="RV">RV - Revenda</option>
                                    <option value="UC">UC - Uso/Consumo</option>
                                    <option value="AT">AT - Ativo imobilizado</option>
                                    <option value="SV">SV - Serviço vinculado</option>
                                </select>
                            </div>
                            <div><label>Tipo de crédito</label>
                                <select id="pr-tipo-credito">
                                    <option value="">-</option>
                                    <option value="IN">IN - Integral</option>
                                    <option value="PA">PA - Parcial</option>
                                    <option value="NE">NE - Nenhum</option>
                                </select>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="form-acoes">
                        <button class="acao" id="pr-botao" onclick="salvarProduto()">Cadastrar</button>
                        <button class="secundario" onclick="limparFormularioProduto()" style="display:none;" id="pr-cancelar">Cancelar edição</button>
                    </div>

                    <h2>Produtos cadastrados</h2>
                    <table>
                        <thead><tr><th>Código</th><th>Nome</th><th>Categoria</th><th>Tipo</th><th>Preço</th><th>Estoque</th><th>Fornecedor</th><th>NCM</th><th>CFOP</th><th>Ativo</th><th class="acoes-linha">Ações</th></tr></thead>
                        <tbody id="tbody-produtos"></tbody>
                    </table>
                    <p class="msg" id="msg-produtos-lista"></p>
                    <p class="msg" id="msg-produtos"></p>
                </div>
            </section>

            <section id="secao-pedidos-loja" class="secao">
                <h1>Pedidos da Loja</h1>
                <div class="card">
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Pedidos de produtos feitos na loja pública. Acompanhe a separação, o envio (com código de rastreio)
                        e a retirada. Visitas agendadas sozinhas não aparecem aqui.
                    </p>
                    <div class="linha-form">
                        <div><label>Envio</label>
                            <select id="pl-filtro-envio" onchange="carregarPedidosLoja()">
                                <option value="">Todos</option>
                                <option value="a_separar">A separar</option>
                                <option value="enviado">Enviado / pronto p/ retirada</option>
                                <option value="entregue">Entregue / retirado</option>
                            </select>
                        </div>
                        <div><label>Pagamento</label>
                            <select id="pl-filtro-pagamento" onchange="carregarPedidosLoja()">
                                <option value="">Todos</option>
                                <option value="pago">Pago</option>
                                <option value="pendente">Pendente</option>
                            </select>
                        </div>
                        <div><button class="secundario" onclick="carregarPedidosLoja()">Atualizar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Pedido</th><th>Data</th><th>Cliente</th><th>Entrega</th><th>Total</th><th>Pagamento</th><th>Envio</th><th></th></tr></thead>
                        <tbody id="tbody-pedidos-loja"></tbody>
                    </table>
                    <p class="msg" id="msg-pedidos-loja"></p>
                </div>
            </section>

            <section id="secao-clientes" class="secao">
                <h1>Clientes</h1>
                <div class="card">
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Endereço completo é obrigatório para emitir NFe (modelo 55) para o cliente — a loja pública e o PDV só coletam nome/CPF na hora da venda.
                    </p>
                    <input type="hidden" id="cl-id">
                    <div class="grupo-campos">
                        <h3>Dados básicos</h3>
                        <div class="linha-form">
                            <div style="flex:1; min-width:200px"><label>Nome</label><input type="text" id="cl-nome"></div>
                            <div><label>CPF/CNPJ</label><input type="text" id="cl-cpf-cnpj" style="width:150px"></div>
                            <div><label>&nbsp;</label><button type="button" class="secundario" onclick="buscarCnpjCliente()">Buscar CNPJ</button></div>
                            <div><label>Telefone</label><input type="text" id="cl-telefone" style="width:130px"></div>
                            <div style="flex:1; min-width:180px"><label>E-mail</label><input type="email" id="cl-email"></div>
                            <label class="campo-check"><input type="checkbox" id="cl-lgpd"> Consentimento LGPD</label>
                        </div>
                    </div>
                    <div class="grupo-campos">
                        <h3>Endereço</h3>
                        <div class="linha-form">
                            <div><label>CEP</label><input type="text" id="cl-cep" style="width:100px" placeholder="00000-000" onblur="buscarCepCliente()"></div>
                            <div style="flex:2; min-width:200px"><label>Logradouro</label><input type="text" id="cl-logradouro"></div>
                            <div><label>Número</label><input type="text" id="cl-numero" style="width:80px"></div>
                            <div style="flex:1; min-width:150px"><label>Bairro</label><input type="text" id="cl-bairro"></div>
                        </div>
                        <div class="linha-form">
                            <div style="flex:1; min-width:180px"><label>Município</label><input type="text" id="cl-municipio"></div>
                            <div><label>UF</label><input type="text" id="cl-uf" style="width:60px" maxlength="2"></div>
                            <div><label>Cód. IBGE</label><input type="text" id="cl-ibge" style="width:90px"></div>
                            <div><label>IE</label><input type="text" id="cl-ie" style="width:120px" placeholder="preencher manualmente"></div>
                        </div>
                        <p style="font-size:11px; color:var(--cor-texto-suave); margin:0;">
                            CEP preenche o endereço automaticamente. "Buscar CNPJ" traz razão social/endereço da Receita
                            Federal — Inscrição Estadual não vem dessa consulta (é cadastrada por estado, não existe API
                            nacional gratuita), preencha à mão.
                        </p>
                    </div>
                    <div class="form-acoes">
                        <button class="acao" id="cl-botao" onclick="salvarCliente()">Cadastrar</button>
                        <button class="secundario" onclick="limparFormularioCliente()" style="display:none;" id="cl-cancelar">Cancelar edição</button>
                    </div>
                    <h2>Clientes cadastrados</h2>
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
                    <div class="grupo-campos">
                        <h3>Identificação</h3>
                        <div class="linha-form">
                            <div style="flex:1; min-width:180px"><label>Razão social</label><input type="text" id="fo-razao"></div>
                            <div style="flex:1; min-width:160px"><label>Nome fantasia</label><input type="text" id="fo-fantasia"></div>
                            <div><label>CNPJ</label><input type="text" id="fo-cnpj" style="width:150px"></div>
                            <div><label>&nbsp;</label><button type="button" class="secundario" onclick="buscarCnpjFornecedor()">Buscar CNPJ</button></div>
                            <div><label>IE</label><input type="text" id="fo-ie" style="width:120px" placeholder="preencher manualmente"></div>
                        </div>
                    </div>
                    <div class="grupo-campos">
                        <h3>Contato</h3>
                        <div class="linha-form">
                            <div style="flex:1; min-width:160px"><label>Contato</label><input type="text" id="fo-contato"></div>
                            <div><label>Telefone</label><input type="text" id="fo-telefone" style="width:130px"></div>
                            <div style="flex:1; min-width:180px"><label>E-mail</label><input type="email" id="fo-email"></div>
                        </div>
                    </div>
                    <div class="grupo-campos">
                        <h3>Endereço</h3>
                        <div class="linha-form">
                            <div><label>CEP</label><input type="text" id="fo-cep" style="width:100px" placeholder="00000-000" onblur="buscarCepFornecedor()"></div>
                            <div style="flex:1; min-width:220px"><label>Endereço</label><input type="text" id="fo-endereco"></div>
                        </div>
                        <p style="font-size:11px; color:var(--cor-texto-suave); margin:0;">
                            CEP preenche o endereço automaticamente. "Buscar CNPJ" traz razão social/endereço da Receita
                            Federal — Inscrição Estadual não vem dessa consulta, preencha à mão.
                        </p>
                    </div>
                    <div class="form-acoes">
                        <button class="acao" id="fo-botao" onclick="salvarFornecedor()">Cadastrar</button>
                        <button class="secundario" onclick="limparFormularioFornecedor()" style="display:none;" id="fo-cancelar">Cancelar edição</button>
                    </div>
                    <h2>Fornecedores cadastrados</h2>
                    <table>
                        <thead><tr><th>Razão social</th><th>CNPJ</th><th>Contato</th><th>Telefone</th><th></th></tr></thead>
                        <tbody id="tbody-fornecedores"></tbody>
                    </table>
                    <p class="msg" id="msg-fornecedores"></p>
                </div>
            </section>

            <section id="secao-compras" class="secao">
                <h1>Entrada de Notas</h1>
                <div class="card">
                    <h2>Importar XML de NFe</h2>
                    <div class="linha-form">
                        <div><label>Arquivo XML</label><input type="file" id="co-xml-arquivo" accept=".xml"></div>
                        <div><label>&nbsp;</label><button type="button" class="acao" onclick="importarXmlCompra()">Importar</button></div>
                    </div>
                    <p class="msg" id="msg-compra-xml"></p>
                </div>

                <div class="card">
                    <h2>Entrada manual</h2>
                    <div class="grupo-campos">
                        <div class="linha-form">
                            <div style="flex:1; min-width:200px">
                                <label>Fornecedor</label>
                                <select id="co-fornecedor"></select>
                            </div>
                            <div><label>Nº nota</label><input type="text" id="co-numero" style="width:120px"></div>
                            <div><label>Série</label><input type="text" id="co-serie" style="width:80px"></div>
                            <div><label>Data entrada</label><input type="date" id="co-data-entrada"></div>
                            <div><label>Frete</label><input type="number" step="0.01" id="co-frete" style="width:100px" value="0"></div>
                            <div><label>Desconto</label><input type="number" step="0.01" id="co-desconto" style="width:100px" value="0"></div>
                        </div>
                    </div>

                    <div class="grupo-campos">
                        <h3>Itens</h3>
                        <div class="linha-form">
                            <div style="flex:1; min-width:200px">
                                <label>Produto</label>
                                <select id="co-item-produto"></select>
                            </div>
                            <div><label>Quantidade</label><input type="number" step="0.001" id="co-item-quantidade" style="width:100px"></div>
                            <div><label>Valor unitário</label><input type="number" step="0.0001" id="co-item-valor" style="width:110px"></div>
                            <div><label>&nbsp;</label><button type="button" class="secundario" onclick="adicionarItemCompra()">Adicionar item</button></div>
                        </div>
                        <table>
                            <thead><tr><th>Produto</th><th>Qtd.</th><th>Valor unit.</th><th>Total</th><th></th></tr></thead>
                            <tbody id="tbody-compra-itens"></tbody>
                        </table>
                    </div>

                    <div class="form-acoes">
                        <button class="acao" onclick="salvarCompraManual()">Registrar entrada</button>
                    </div>
                    <p class="msg" id="msg-compra-manual"></p>
                </div>

                <div class="card">
                    <h2>Notas de entrada</h2>
                    <table>
                        <thead><tr><th>Nº nota</th><th>Fornecedor</th><th>Data entrada</th><th>Valor total</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody-compras"></tbody>
                    </table>
                    <p class="msg" id="msg-compras"></p>
                </div>

                <div class="card" id="compra-conferencia-card" style="display:none;">
                    <h2>Conferência de itens importados (Nota #<span id="compra-conf-id"></span>)</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave);">
                        Itens sem produto localizado por código de barras precisam ser vinculados a um produto
                        existente ou cadastrados como novo produto antes de confirmar a entrada.
                    </p>
                    <table>
                        <thead><tr><th>Descrição (XML)</th><th>Qtd.</th><th>Valor unit.</th><th>Produto</th><th></th></tr></thead>
                        <tbody id="tbody-compra-conferencia"></tbody>
                    </table>
                    <div class="form-acoes">
                        <button class="acao" onclick="confirmarCompra()">Confirmar entrada</button>
                        <button class="secundario" onclick="document.getElementById('compra-conferencia-card').style.display='none';">Fechar</button>
                    </div>
                    <p class="msg" id="msg-compra-conferencia"></p>
                </div>
            </section>

            <section id="secao-vendedores" class="secao">
                <h1>Vendedores</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="ve-nome"></div>
                        <div><label>Telefone</label><input type="text" id="ve-telefone" style="width:140px"></div>
                        <div><label>Chave PIX</label><input type="text" id="ve-pix" maxlength="77" style="width:240px" placeholder="CPF/CNPJ, e-mail, telefone ou chave aleatória"></div>
                        <div><label>Comissão (%)</label><input type="number" step="0.01" id="ve-comissao" value="5" style="width:100px"></div>
                        <div><button class="acao" id="ve-botao" onclick="salvarVendedor()">Cadastrar</button></div>
                        <div><button class="secundario" id="ve-cancelar" onclick="limparFormularioVendedor()" style="display:none;">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Telefone</th><th>Chave PIX</th><th>Comissão</th><th></th></tr></thead>
                        <tbody id="tbody-vendedores"></tbody>
                    </table>
                    <p class="msg" id="msg-vendedores"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Relatório de vendas por vendedor</h2>
                    <div class="linha-form">
                        <div><label>De</label><input type="date" id="ver-inicio"></div>
                        <div><label>Até</label><input type="date" id="ver-fim"></div>
                        <div><label>Tipo</label>
                            <select id="ver-tipo">
                                <option value="todos">Todos (produtos + visitações)</option>
                                <option value="produtos">Só produtos</option>
                                <option value="visitacoes">Só visitações</option>
                            </select>
                        </div>
                        <div><button class="secundario" onclick="carregarRelatorioVendedores()">Consultar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Vendedor</th><th>Chave PIX</th><th>Vendas</th><th>Itens</th><th>Valor total</th></tr></thead>
                        <tbody id="tbody-relatorio-vendedores"></tbody>
                    </table>
                </div>
            </section>

            <section id="secao-atendentes" class="secao">
                <h1>Atendentes</h1>
                <p style="font-size:12px; color:var(--cor-texto-suave);">
                    Quem opera a venda no PDV - diferente do vendedor/guia, que recebe comissão pela visita.
                    Uma venda pode ter os dois preenchidos ao mesmo tempo.
                </p>
                <div class="card">
                    <input type="hidden" id="at-id">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="at-nome"></div>
                        <div><label>Telefone</label><input type="text" id="at-telefone" style="width:140px"></div>
                        <div><label>Comissão (%)</label><input type="number" step="0.01" id="at-comissao" value="3" style="width:100px"></div>
                        <div><button class="acao" id="at-botao" onclick="salvarAtendente()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioAtendente()" style="display:none;" id="at-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Telefone</th><th>Comissão</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-atendentes"></tbody>
                    </table>
                    <p class="msg" id="msg-atendentes"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Relatório de vendas por atendente</h2>
                    <div class="linha-form">
                        <div><label>De</label><input type="date" id="atr-inicio"></div>
                        <div><label>Até</label><input type="date" id="atr-fim"></div>
                        <div><label>Tipo</label>
                            <select id="atr-tipo">
                                <option value="todos">Todos (produtos + visitações)</option>
                                <option value="produtos">Só produtos</option>
                                <option value="visitacoes">Só visitações</option>
                            </select>
                        </div>
                        <div><button class="secundario" onclick="carregarRelatorioAtendentes()">Consultar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Atendente</th><th>Vendas</th><th>Itens</th><th>Valor total</th></tr></thead>
                        <tbody id="tbody-relatorio-atendentes"></tbody>
                    </table>
                </div>
            </section>

            <section id="secao-cupons" class="secao">
                <h1>Cupons de Desconto</h1>
                <p style="font-size:12px; color:var(--cor-texto-suave);">
                    Cliente informa o código na loja pública, na hora do checkout. Cupom desativado ou
                    expirado deixa de valer sem precisar apagar (mantém o histórico de quem já usou).
                    O cupom desconta <strong>só o valor da visitação</strong>, nunca produtos. O "teto de
                    desconto" limita o valor em R$ quando há 2 ou mais tickets de visitação no carrinho;
                    com exatamente 1 ticket, o teto aplicado é a metade desse valor.
                </p>
                <div class="card">
                    <input type="hidden" id="cp-id">
                    <div class="linha-form">
                        <div><label>Código</label><input type="text" id="cp-codigo" style="width:140px; text-transform:uppercase;"></div>
                        <div><label>Tipo</label>
                            <select id="cp-tipo">
                                <option value="percentual">% percentual</option>
                                <option value="valor_fixo">R$ valor fixo</option>
                            </select>
                        </div>
                        <div><label>Valor</label><input type="number" step="0.01" min="0.01" id="cp-valor" style="width:110px"></div>
                        <div><label>Teto de desconto p/ visitação (R$, opcional)</label><input type="number" step="0.01" min="0" id="cp-teto" style="width:140px"></div>
                        <div><label>Válido até (opcional)</label><input type="date" id="cp-valido-ate"></div>
                        <div><label>Limite de usos (opcional)</label><input type="number" min="1" id="cp-limite" style="width:120px"></div>
                        <div class="campo-check"><label><input type="checkbox" id="cp-ativo" checked> Ativo</label></div>
                        <div><button class="acao" id="cp-botao" onclick="salvarCupom()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioCupom()" style="display:none;" id="cp-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Código</th><th>Desconto</th><th>Válido até</th><th>Usos</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-cupons"></tbody>
                    </table>
                    <p class="msg" id="msg-cupons"></p>
                </div>

                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Lote de cupons importados</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:-6px;">
                        Códigos gerados em massa (ex.: planilha de campanha), cada um de uso único.
                        <span id="cpl-resumo"></span>
                    </p>
                    <div class="linha-form">
                        <div><label>Buscar código</label><input type="text" id="cpl-busca" style="width:160px; text-transform:uppercase;" onkeydown="if(event.key==='Enter') carregarCuponsLote(1)"></div>
                        <div><label>Status</label>
                            <select id="cpl-status" onchange="carregarCuponsLote(1)">
                                <option value="todos">Todos</option>
                                <option value="disponiveis">Disponíveis</option>
                                <option value="usados">Usados</option>
                            </select>
                        </div>
                        <div><button class="secundario" onclick="carregarCuponsLote(1)">Buscar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Código</th><th>Desconto</th><th>Status</th><th>Usado por</th><th>Data/hora do uso</th></tr></thead>
                        <tbody id="tbody-cupons-lote"></tbody>
                    </table>
                    <div class="linha-form" style="justify-content:flex-end;">
                        <button class="secundario" id="cpl-anterior" onclick="carregarCuponsLote(cuponsLotePagina - 1)">« Anterior</button>
                        <span id="cpl-paginacao" style="align-self:center; font-size:12px;"></span>
                        <button class="secundario" id="cpl-proxima" onclick="carregarCuponsLote(cuponsLotePagina + 1)">Próxima »</button>
                    </div>
                </div>
            </section>

            <section id="secao-descontos-pdv" class="secao">
                <h1>Descontos do PDV</h1>
                <p style="font-size:12px; color:var(--cor-texto-suave);">
                    Opções de desconto que o operador escolhe na tela do PDV, ao lado da forma de pagamento.
                    <strong>Não valem na loja virtual.</strong> Cada desconto incide só em
                    <strong>produtos</strong> ou só em <strong>visitas</strong> (nas visitas, no máximo 2 tickets por vez).
                    O desconto em visitas não pode ser usado junto com cupom.
                </p>
                <div class="card">
                    <input type="hidden" id="dp-id">
                    <div class="linha-form">
                        <div><label>Descrição</label><input type="text" id="dp-descricao" placeholder="ex: 10% Produtos" style="width:200px"></div>
                        <div><label>Percentual (%)</label><input type="number" step="0.01" min="0.01" max="100" id="dp-percentual" style="width:110px"></div>
                        <div><label>Incide em</label>
                            <select id="dp-aplica-em">
                                <option value="produtos">Somente produtos</option>
                                <option value="visitas">Somente visitas</option>
                            </select>
                        </div>
                        <div><button class="acao" id="dp-botao" onclick="salvarDescontoPdv()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioDescontoPdv()" style="display:none;" id="dp-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Descrição</th><th>Percentual</th><th>Incide em</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-descontos-pdv"></tbody>
                    </table>
                    <p class="msg" id="msg-descontos-pdv"></p>
                </div>
            </section>

            <section id="secao-caixa-consulta" class="secao">
                <h1>Caixa (consulta)</h1>
                <p style="font-size:12px; color:var(--cor-texto-suave);">
                    Abertura, fechamento, sangria e suprimento lançados no PDV - aqui é só consulta,
                    quem opera o caixa físico é a tela do PDV.
                </p>
                <div class="card">
                    <p id="cx-status" style="font-size:14px; font-weight:600;">Carregando...</p>
                    <table>
                        <thead><tr><th>Data/hora</th><th>Tipo</th><th>Valor</th><th>Operador</th><th>Observação</th></tr></thead>
                        <tbody id="tbody-caixa-consulta"></tbody>
                    </table>
                </div>
            </section>

            <section id="secao-financeiro" class="secao">
                <h1>Financeiro</h1>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Contas a pagar</h2>
                    <div class="linha-form">
                        <div><label>Fornecedor</label><select id="cp-fornecedor"><option value="">Nenhum</option></select></div>
                        <div style="flex:1"><label>Histórico</label><input type="text" id="cp-historico" placeholder="Ex: Compra de insumos - NF 1234"></div>
                        <div><label>Valor (R$)</label><input type="number" step="0.01" id="cp-valor" style="width:100px"></div>
                        <div><label>Vencimento</label><input type="date" id="cp-vencimento"></div>
                        <div><label>Categoria (plano de contas)</label><select id="cp-plano-conta"><option value="">Sem categoria</option></select></div>
                        <div><button class="acao" onclick="criarContaPagar()">Lançar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Fornecedor</th><th>Histórico</th><th>Valor</th><th>Vencimento</th><th>Categoria</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody-contas-pagar"></tbody>
                    </table>
                    <p class="msg" id="msg-contas-pagar"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Contas a receber</h2>
                    <div class="linha-form">
                        <div><label>Cliente</label><select id="cr-cliente"><option value="">Nenhum</option></select></div>
                        <div style="flex:1"><label>Histórico</label><input type="text" id="cr-historico" placeholder="Ex: Venda avulsa - pedido 5678"></div>
                        <div><label>Valor (R$)</label><input type="number" step="0.01" id="cr-valor" style="width:100px"></div>
                        <div><label>Vencimento</label><input type="date" id="cr-vencimento"></div>
                        <div><label>Categoria (plano de contas)</label><select id="cr-plano-conta"><option value="">Sem categoria</option></select></div>
                        <div><button class="acao" onclick="criarContaReceber()">Lançar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Cliente</th><th>Histórico</th><th>Valor</th><th>Vencimento</th><th>Categoria</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody-contas-receber"></tbody>
                    </table>
                    <p class="msg" id="msg-contas-receber"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Banco usado ao marcar como pago</h2>
                    <p style="font-size:11px; color:var(--cor-texto-suave); margin-top:0;">
                        Opcional - se escolher um banco aqui antes de clicar em "Marcar pago" em qualquer conta acima,
                        o movimento bancário correspondente é lançado automaticamente no extrato desse banco.
                    </p>
                    <select id="fin-banco-pagamento"><option value="">Nenhum (não lança no banco)</option></select>
                </div>
            </section>

            <section id="secao-grupos" class="secao">
                <h1>Grupos de produto</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="gr-nome"></div>
                        <div><label>Descrição</label><input type="text" id="gr-descricao"></div>
                        <div><button class="acao" onclick="criarGrupo()">Cadastrar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Descrição</th><th>Produtos</th><th>Valor em estoque</th></tr></thead>
                        <tbody id="tbody-grupos"></tbody>
                    </table>
                    <p class="msg" id="msg-grupos"></p>
                </div>
            </section>

            <section id="secao-plano-contas" class="secao">
                <h1>Plano de Contas</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Código</label><input type="text" id="pc-codigo" style="width:90px"></div>
                        <div><label>Nome</label><input type="text" id="pc-nome"></div>
                        <div><label>Tipo</label>
                            <select id="pc-tipo">
                                <option value="despesa">Despesa (contas a pagar)</option>
                                <option value="receita">Receita (contas a receber)</option>
                            </select>
                        </div>
                        <div><button class="acao" onclick="criarPlanoContas()">Cadastrar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Código</th><th>Nome</th><th>Tipo</th></tr></thead>
                        <tbody id="tbody-plano-contas"></tbody>
                    </table>
                    <p class="msg" id="msg-plano-contas"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Relatório por categoria</h2>
                    <div class="linha-form">
                        <div><label>De</label><input type="date" id="pcr-inicio"></div>
                        <div><label>Até</label><input type="date" id="pcr-fim"></div>
                        <div><button class="secundario" onclick="carregarRelatorioPlanoContas()">Consultar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Categoria</th><th>Tipo</th><th>Total</th><th>Pago</th><th>Em aberto</th></tr></thead>
                        <tbody id="tbody-relatorio-plano-contas"></tbody>
                    </table>
                </div>
            </section>

            <section id="secao-bancos" class="secao">
                <h1>Bancos</h1>
                <div class="card">
                    <div class="linha-form">
                        <div><label>Nome</label><input type="text" id="bc-nome"></div>
                        <div><label>Agência</label><input type="text" id="bc-agencia" style="width:90px"></div>
                        <div><label>Conta</label><input type="text" id="bc-conta" style="width:110px"></div>
                        <div><label>Tipo</label>
                            <select id="bc-tipo">
                                <option value="corrente">Corrente</option>
                                <option value="poupanca">Poupança</option>
                            </select>
                        </div>
                        <div><label>Saldo inicial (R$)</label><input type="number" step="0.01" id="bc-saldo" style="width:110px"></div>
                        <div><button class="acao" onclick="criarBanco()">Cadastrar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Agência/Conta</th><th>Tipo</th><th></th></tr></thead>
                        <tbody id="tbody-bancos"></tbody>
                    </table>
                    <p class="msg" id="msg-bancos"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Extrato</h2>
                    <div class="linha-form">
                        <div><label>Banco</label><select id="ex-banco"></select></div>
                        <div><label>De</label><input type="date" id="ex-inicio"></div>
                        <div><label>Até</label><input type="date" id="ex-fim"></div>
                        <div><button class="secundario" onclick="carregarExtratoBanco()">Consultar</button></div>
                    </div>
                    <p style="font-size:13px;" id="ex-saldo"></p>
                    <table>
                        <thead><tr><th>Data</th><th>Tipo</th><th>Valor</th><th>Descrição</th><th>Origem</th><th>Saldo após</th></tr></thead>
                        <tbody id="tbody-extrato-banco"></tbody>
                    </table>
                    <h3 style="font-size:13px;">Lançamento manual</h3>
                    <div class="linha-form">
                        <div><label>Data</label><input type="date" id="mv-data"></div>
                        <div><label>Tipo</label>
                            <select id="mv-tipo"><option value="credito">Crédito</option><option value="debito">Débito</option></select>
                        </div>
                        <div><label>Valor (R$)</label><input type="number" step="0.01" id="mv-valor" style="width:110px"></div>
                        <div><label>Descrição</label><input type="text" id="mv-descricao"></div>
                        <div><button class="acao" onclick="lancarMovimentoBancario()">Lançar</button></div>
                    </div>
                    <p class="msg" id="msg-extrato-banco"></p>
                </div>
            </section>

            <section id="secao-parametros" class="secao">
                <h1>Parâmetros</h1>

                <div class="card">
                    <h2>Estoque</h2>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:400;">
                        <input type="checkbox" id="pm-estoque-negativo" style="width:auto;">
                        Permitir venda com estoque negativo
                    </label>
                    <p style="font-size:11px; color:var(--cor-texto-suave); margin:4px 0 0;">
                        Se desativado, o PDV bloqueia a venda quando não há saldo suficiente do produto em estoque.
                    </p>
                </div>

                <div class="card">
                    <h2>PDV — Impressão de cupom</h2>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:400;">
                        <input type="checkbox" id="pm-impressao-direta" style="width:auto;">
                        Imprimir cupom automaticamente ao finalizar a venda
                    </label>
                    <p style="font-size:11px; color:var(--cor-texto-suave); margin:4px 0 0;">
                        Se desativado, o cupom abre em uma janela de pré-visualização e o operador escolhe quando imprimir.
                    </p>
                </div>

                <div class="card">
                    <div class="linha-form">
                        <div><button class="acao" onclick="salvarConfigOperacional()">Salvar parâmetros</button></div>
                    </div>
                    <p class="msg" id="msg-config-operacional"></p>
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

            <section id="secao-nfe" class="secao">
                <h1>Emitir NF-e</h1>
                <div id="nfe-ambiente" class="card" style="margin-bottom:14px; font-size:13px;">Carregando...</div>

                <div class="card">
                    <div class="abas-produto" id="nfe-abas">
                        <button type="button" class="ativa" data-aba="venda" onclick="nfeMostrarAba('venda', this)">Venda</button>
                        <button type="button" data-aba="remessa" onclick="nfeMostrarAba('remessa', this)">Remessa</button>
                        <button type="button" data-aba="transferencia" onclick="nfeMostrarAba('transferencia', this)">Transferência</button>
                        <button type="button" data-aba="bonificacao" onclick="nfeMostrarAba('bonificacao', this)">Bonificação</button>
                        <button type="button" data-aba="dev-venda" onclick="nfeMostrarAba('dev-venda', this)">Devolução de venda</button>
                        <button type="button" data-aba="dev-fornecedor" onclick="nfeMostrarAba('dev-fornecedor', this)">Devolução a fornecedor</button>
                    </div>

                    <div id="nfe-painel-emitir">
                        <p id="nfe-descricao" style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;"></p>
                        <div class="linha-form">
                            <div style="flex:1; min-width:240px;"><label>Destinatário (cliente cadastrado)</label>
                                <select id="nfe-cliente" style="width:100%"></select>
                            </div>
                            <div style="flex:1; min-width:240px;"><label>Natureza da operação</label>
                                <input type="text" id="nfe-natureza" maxlength="60" style="width:100%">
                            </div>
                            <div><label>CFOP (opcional)</label>
                                <input type="text" id="nfe-cfop" maxlength="4" style="width:90px">
                            </div>
                            <div id="nfe-forma-pagamento-bloco"><label>Forma de pagamento</label>
                                <select id="nfe-forma-pagamento"></select>
                            </div>
                        </div>
                        <p style="font-size:11.5px; color:var(--cor-texto-suave); margin:4px 0 12px;">
                            O CFOP sai do padrão da operação (5xxx no mesmo estado, 6xxx em outro). Informe um CFOP só se precisar de outro código de saída.
                        </p>

                        <h3 style="font-size:13px; margin:14px 0 4px;">Itens</h3>
                        <table>
                            <thead><tr><th>Produto</th><th>Tipo/tamanho</th><th>Quantidade</th><th>Valor unitário</th><th>Total</th><th></th></tr></thead>
                            <tbody id="tbody-nfe-itens"></tbody>
                        </table>
                        <div style="margin:8px 0;"><button type="button" class="secundario" onclick="nfeAdicionarItem()">+ Adicionar item</button></div>

                        <h3 style="font-size:13px; margin:14px 0 4px;">Frete e transporte</h3>
                        <div class="linha-form">
                            <div><label>Frete (R$)</label><input type="number" step="0.01" min="0" id="nfe-frete" style="width:110px" oninput="nfeAtualizarTotais()"></div>
                            <div><label>Quem paga o transporte</label><select id="nfe-modalidade" style="min-width:230px"></select></div>
                        </div>
                        <details style="margin:8px 0;">
                            <summary style="cursor:pointer; font-size:12.5px;">Transportadora (opcional)</summary>
                            <div class="linha-form" style="margin-top:8px;">
                                <div><label>Nome</label><input type="text" id="nfe-transp-nome" maxlength="60"></div>
                                <div><label>CNPJ/CPF</label><input type="text" id="nfe-transp-doc" maxlength="18" style="width:150px"></div>
                                <div><label>IE</label><input type="text" id="nfe-transp-ie" maxlength="14" style="width:120px"></div>
                                <div><label>Endereço</label><input type="text" id="nfe-transp-endereco" maxlength="60"></div>
                                <div><label>Município</label><input type="text" id="nfe-transp-municipio" maxlength="60"></div>
                                <div><label>UF</label><input type="text" id="nfe-transp-uf" maxlength="2" style="width:50px; text-transform:uppercase"></div>
                            </div>
                        </details>

                        <div style="margin-top:10px;">
                            <label>Informações complementares</label>
                            <textarea id="nfe-informacoes" rows="2" maxlength="2000" style="width:100%"></textarea>
                        </div>
                        <div style="margin-top:8px;">
                            <label style="font-weight:normal"><input type="checkbox" id="nfe-baixar-estoque"> Baixar estoque desta operação</label>
                        </div>

                        <p style="font-size:16px; font-weight:700; text-align:right; margin:14px 0 4px;">
                            Total da nota: <span id="nfe-total">R$ 0,00</span>
                        </p>
                        <div style="text-align:right;"><button class="acao" id="nfe-botao-emitir" onclick="nfeEmitir()">Emitir NF-e</button></div>
                        <p class="msg" id="msg-nfe"></p>
                    </div>

                    <div id="nfe-painel-dev-venda" style="display:none;">
                        <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                            Cliente devolvendo mercadoria comprada. Gera NF-e com CFOP 1202 (mesmo estado) ou 2202 (fora do estado),
                            referenciando a nota original quando possível.
                        </p>
                        <table>
                            <thead><tr><th>Documento</th><th>Cliente</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
                            <tbody id="tbody-dv-documentos"><tr><td colspan="5">Carregando...</td></tr></tbody>
                        </table>
                        <div id="dv-itens-bloco" style="display:none; margin-top:16px;">
                            <h3 style="font-size:14px;">Itens disponíveis para devolução - documento #<span id="dv-documento-numero"></span></h3>
                            <table>
                                <thead><tr><th>Devolver?</th><th>Produto</th><th>Vendido</th><th>Já devolvido</th><th>Disponível</th><th>Quantidade a devolver</th></tr></thead>
                                <tbody id="tbody-dv-itens"></tbody>
                            </table>
                            <div class="linha-form" style="margin-top:8px;">
                                <div><button class="acao" onclick="dvConfirmar()">Confirmar devolução</button></div>
                                <div><button class="secundario" onclick="dvFechar()">Cancelar</button></div>
                            </div>
                        </div>
                        <p class="msg" id="msg-dv"></p>
                    </div>

                    <div id="nfe-painel-dev-fornecedor" style="display:none;">
                        <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                            Empresa devolvendo mercadoria comprada. Gera NF-e com CFOP 5202 (mesmo estado) ou 6202 (fora do estado).
                            Exige compra confirmada e fornecedor com endereço completo cadastrado.
                        </p>
                        <table>
                            <thead><tr><th>Compra</th><th>Fornecedor</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
                            <tbody id="tbody-dvf-compras"><tr><td colspan="5">Carregando...</td></tr></tbody>
                        </table>
                        <div id="dvf-itens-bloco" style="display:none; margin-top:16px;">
                            <h3 style="font-size:14px;">Itens disponíveis para devolução - compra #<span id="dvf-compra-numero"></span></h3>
                            <table>
                                <thead><tr><th>Devolver?</th><th>Produto</th><th>Comprado</th><th>Já devolvido</th><th>Disponível</th><th>Quantidade a devolver</th></tr></thead>
                                <tbody id="tbody-dvf-itens"></tbody>
                            </table>
                            <div class="linha-form" style="margin-top:8px;">
                                <div><button class="acao" onclick="dvfConfirmar()">Confirmar devolução</button></div>
                                <div><button class="secundario" onclick="dvfFechar()">Cancelar</button></div>
                            </div>
                        </div>
                        <p class="msg" id="msg-dvf"></p>
                    </div>
                </div>
            </section>

            <section id="secao-fiscal" class="secao">
                <h1>Fiscal — Emissão e Relatórios</h1>

                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Documentos fiscais</h2>
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
                        <div><button class="acao" onclick="carregarRelatorioFiscal()">Filtrar</button></div>
                        <div><button class="secundario" onclick="exportarFiscal('xmls')">Exportar XMLs (.zip)</button></div>
                        <div><button class="secundario" onclick="exportarFiscal('relatorio-contador')">Relatório contador (.csv)</button></div>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Nº</th><th>Série</th><th>Modelo</th><th>Status</th><th>Total</th><th>Emitido em</th><th>Ações</th></tr>
                        </thead>
                        <tbody id="tbody-documentos-fiscais"><tr><td colspan="7">Carregando...</td></tr></tbody>
                    </table>
                    <p class="msg" id="msg-documentos-fiscais"></p>
                </div>

                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Importar venda não fiscal → emitir documento</h2>
                    <div class="linha-form">
                        <div><label>Emitir como</label>
                            <select id="imp-modelo"><option value="65">NFC-e (65)</option><option value="55">NFe (55)</option></select>
                        </div>
                    </div>
                    <table>
                        <thead><tr><th>Venda</th><th>Cliente</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
                        <tbody id="tbody-vendas-nao-fiscais"><tr><td colspan="5">Carregando...</td></tr></tbody>
                    </table>
                    <p class="msg" id="msg-importar-fiscal"></p>
                </div>

                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Importar NFC-e → NFe (regularização)</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Gera uma NFe formal referenciando uma NFC-e já autorizada, com CFOP 5929 (mesmo estado)
                        ou 6929 (fora do estado) - útil quando o cliente pessoa jurídica precisa de NFe para a
                        contabilidade dele. Exige que o cliente da venda tenha endereço completo cadastrado.
                    </p>
                    <table>
                        <thead><tr><th>NFC-e</th><th>Cliente</th><th>Total</th><th>Data</th><th>Ação</th></tr></thead>
                        <tbody id="tbody-nfces-disponiveis"><tr><td colspan="5">Carregando...</td></tr></tbody>
                    </table>
                    <p class="msg" id="msg-importar-nfe"></p>
                </div>

                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Inutilizar numeração</h2>
                    <div class="linha-form">
                        <div><label>Modelo</label>
                            <select id="inut-modelo"><option value="65">NFC-e (65)</option><option value="55">NFe (55)</option></select>
                        </div>
                        <div><label>Série</label><input type="text" id="inut-serie" value="1" style="width:60px"></div>
                        <div><label>Nº inicial</label><input type="number" id="inut-inicial" style="width:100px"></div>
                        <div><label>Nº final</label><input type="number" id="inut-final" style="width:100px"></div>
                        <div style="flex:1"><label>Justificativa (mín. 15 caracteres)</label><input type="text" id="inut-justificativa" style="width:100%"></div>
                        <div><button class="perigo" onclick="inutilizarFiscal()">Inutilizar</button></div>
                    </div>
                    <p class="msg" id="msg-inutilizar-fiscal"></p>
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
                        <div><label>CEP</label><input type="text" id="cf-cep" style="width:90px" placeholder="00000-000" onblur="buscarCepEmitente()"></div>
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
                        <div><label style="font-weight:normal"><input type="checkbox" id="cf-pis-cofins-exclui-icms"> Excluir o ICMS da base de PIS/COFINS (regime normal)</label>
                            <div style="font-size:11.5px; color:var(--cor-texto-suave);">Tese do STF; confirme com o contador. Só vale para empresas do regime normal.</div>
                        </div>
                    </div>
                    <div class="linha-form">
                        <div><label>Série NF-e</label><input type="number" id="cf-serie-nfe" min="0" max="889" style="width:80px"></div>
                        <div><label>Último nº NF-e emitido</label><input type="number" id="cf-numero-nfe" min="0" style="width:130px"></div>
                        <div><label>Série NFC-e</label><input type="number" id="cf-serie-nfce" min="0" max="889" style="width:80px"></div>
                        <div><label>Último nº NFC-e emitido</label><input type="number" id="cf-numero-nfce" min="0" style="width:130px"></div>
                    </div>
                    <p style="font-size:11.5px; color:var(--cor-texto-suave); margin:0 0 8px;">
                        A próxima nota usa o número seguinte ao "último emitido" (ex.: último 10, próxima 11). Se a empresa já emitiu
                        notas em outro sistema na mesma série, informe aqui o último número usado; ao trocar de série, informe 0 para recomeçar do 1.
                        Número já usado na SEFAZ é recusado (rejeição 539). Confirme com o contador antes de alterar.
                    </p>
                    <div class="linha-form">
                        <div><label>CSC (NFC-e)</label><input type="text" id="cf-csc"></div>
                        <div><label>ID do token CSC</label><input type="text" id="cf-csc-id" style="width:100px"></div>
                        <div><button class="acao" onclick="salvarConfigFiscal()">Salvar</button></div>
                    </div>
                    <p class="msg" id="msg-config-fiscal"></p>
                </div>

                <div class="card">
                    <h2>Identidade visual da loja pública</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Logo e cor usados na loja pública desta empresa (a página que o consumidor final vê para
                        comprar/agendar) - diferente da identidade da plataforma, é a marca da sua empresa.
                    </p>
                    <div class="linha-form">
                        <div><label>Segmento</label><input type="text" id="lj-segmento" placeholder="ex: cervejaria, vinícola"></div>
                        <div>
                            <label>Logo</label>
                            <input type="file" id="lj-logo-arquivo" accept="image/*">
                            <div style="margin-top:6px;">
                                <label style="font-weight:normal"><input type="checkbox" id="lj-logo-usar-url"> Usar uma URL em vez de enviar arquivo</label>
                            </div>
                            <input type="text" id="lj-logo" placeholder="https://..." style="width:100%; display:none; margin-top:4px;">
                            <img id="lj-logo-preview" src="" alt="Pré-visualização" style="display:none; max-width:100px; max-height:100px; margin-top:8px; border-radius:4px; object-fit:cover">
                        </div>
                        <div><label>Cor primária</label><input type="color" id="lj-cor" style="width:60px; padding:2px;"></div>
                        <div><button class="acao" onclick="salvarConfigLoja()">Salvar</button></div>
                    </div>
                    <p class="msg" id="msg-config-loja"></p>
                </div>

                <div class="card">
                    <h2>Frete da loja pública</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Valor do frete por estado (UF) para os produtos vendidos na loja pública. A linha "Demais estados"
                        vale para os estados sem regra própria; se não houver regra para o estado do cliente, a entrega
                        não é oferecida. Na venda só de visitas não há frete.
                    </p>
                    <table>
                        <thead><tr><th>UF</th><th>Valor do frete (R$)</th><th>Prazo (dias)</th><th></th></tr></thead>
                        <tbody id="tbody-frete-regras"></tbody>
                    </table>
                    <div style="margin:8px 0;"><button type="button" class="secundario" onclick="adicionarRegraFrete()">+ Adicionar estado</button></div>
                    <div class="linha-form">
                        <div><label>Frete grátis a partir de (R$)</label><input type="number" step="0.01" min="0" id="fr-gratis" style="width:140px" placeholder="vazio = não tem"></div>
                        <div><label style="font-weight:normal"><input type="checkbox" id="fr-retirada" onchange="alternarRetirada()"> Permitir retirada na loja</label></div>
                    </div>
                    <div id="fr-retirada-box" style="display:none; margin-top:8px;">
                        <label>Instruções de retirada (endereço, horário...)</label>
                        <input type="text" id="fr-retirada-instrucoes" maxlength="500" style="width:100%">
                    </div>
                    <div style="margin-top:10px;"><button class="acao" onclick="salvarConfigFrete()">Salvar frete</button></div>
                    <p class="msg" id="msg-config-frete"></p>
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
                    <input type="hidden" id="fp-id">
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
                        <div><button class="acao" id="fp-botao" onclick="salvarFormaPagamento()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioFormaPagamento()" style="display:none;" id="fp-cancelar">Cancelar edição</button></div>
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
                                <option value="stone">Stone</option>
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
                        pareado por QR code e fica fora dos Termos de Uso do WhatsApp (risco de banimento do
                        número) - a escolha é sua. Sem configurar aqui, as notificações ficam em modo simulado
                        (só registradas, não enviadas de verdade).
                    </p>
                    <p id="wa-status" style="font-size:13px;">Carregando...</p>
                    <div class="linha-form">
                        <div><label>Provedor</label>
                            <select id="wa-provider" onchange="alternarCamposProvedor()">
                                <option value="zapi">Z-API (pago)</option>
                                <option value="baileys">Baileys (gratuito, via QR code)</option>
                            </select>
                        </div>
                        <div><label><input type="checkbox" id="wa-ativo"> Ativo</label></div>
                    </div>
                    <div class="linha-form" id="wa-campos-zapi">
                        <div><label>Instance ID (Z-API)</label><input type="text" id="wa-instance-id"></div>
                        <div style="flex:1"><label>Token</label><input type="password" id="wa-token" placeholder="Deixe em branco para manter o atual"></div>
                        <div style="flex:1"><label>Client-Token</label><input type="password" id="wa-client-token" placeholder="Deixe em branco para manter o atual"></div>
                    </div>
                    <div class="linha-form"><div><button class="acao" onclick="salvarConfigWhatsapp()">Salvar</button></div></div>
                    <p class="msg" id="msg-config-whatsapp"></p>
                </div>

                <div class="card" id="card-baileys" style="display:none;">
                    <h2>Parear número (Baileys)</h2>
                    <p style="font-size:12px; color:var(--cor-texto-suave); margin-top:0;">
                        Escaneie o QR code abaixo com o WhatsApp do celular que vai enviar as mensagens
                        (Configurações → Aparelhos conectados → Conectar aparelho). A sessão fica salva no
                        servidor - não precisa escanear de novo, a menos que desconecte.
                    </p>
                    <p id="baileys-status" style="font-size:13px; font-weight:600;">Carregando...</p>
                    <div id="baileys-qr-wrap" style="margin:12px 0;"></div>
                    <div class="linha-form">
                        <div><button class="acao" onclick="baileysIniciar()">Gerar QR code / Reconectar</button></div>
                        <div><button class="secundario" onclick="baileysDesconectar()">Desconectar</button></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        const empresa = @json($empresaSlug);
        const base = `{{ url('/dashboard') }}/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        const carregadores = {
            dashboard: carregarIndicadores,
            agenda: carregarAgenda,
            produtos: () => { carregarProdutos(); carregarGrupos(); },
            'pedidos-loja': carregarPedidosLoja,
            nfe: carregarNfe,
            clientes: carregarClientes,
            fornecedores: carregarFornecedores,
            compras: carregarCompras,
            vendedores: () => { carregarVendedores(); carregarRelatorioVendedores(); },
            atendentes: carregarAtendentes,
            cupons: () => { carregarCupons(); carregarCuponsLote(1); },
            'descontos-pdv': carregarDescontosPdv,
            grupos: carregarGrupos,
            financeiro: () => { carregarSelectsFinanceiro().then(() => { carregarContasPagar(); carregarContasReceber(); }); },
            'plano-contas': () => { carregarPlanoContas(); },
            bancos: carregarBancos,
            usuarios: carregarUsuarios,
            parametros: carregarConfigOperacional,
            'config-fiscal': () => { carregarConfigFiscal(); carregarCertificado(); carregarConfigLoja(); carregarConfigFrete(); },
            fiscal: () => { carregarRelatorioFiscal(); carregarVendasNaoFiscaisFiscal(); carregarNfcesDisponiveisFiscal(); },
            'caixa-consulta': carregarCaixaConsulta,
            pagamentos: () => { carregarFormasPagamento(); carregarConfigPagamento(); },
            whatsapp: () => { carregarConfigWhatsapp(); alternarCamposProvedor(); baileysAtualizarStatus(); },
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

        let agendaCache = [];

        function preencherSelectVendedorAtendente() {
            const selVendedor = document.getElementById('ag-vendedor');
            const selAtendente = document.getElementById('ag-atendente');
            selVendedor.innerHTML = '<option value="">Nenhum</option>' +
                vendedoresCache.map(v => `<option value="${v.id}">${v.nome}</option>`).join('');
            selAtendente.innerHTML = '<option value="">Nenhum</option>' +
                atendentesCache.map(a => `<option value="${a.id}">${a.nome}</option>`).join('');
        }

        async function carregarAgenda() {
            await Promise.all([carregarVendedores(), carregarAtendentes()]);
            const resp = await fetch(`${base}/visitas`);
            agendaCache = await resp.json();
            preencherSelectVendedorAtendente();
            document.getElementById('tbody-agenda').innerHTML = agendaCache.map(a => `
                <tr>
                    <td>${new Date(a.data_hora).toLocaleString('pt-BR')}</td>
                    <td>${a.vagas_reservadas}/${a.vagas_total}</td>
                    <td>R$ ${Number(a.valor_visita).toFixed(2)}</td>
                    <td>${a.vendedor ? a.vendedor.nome : '-'}</td>
                    <td>${a.atendente ? a.atendente.nome : '-'}</td>
                    <td><span class="status status-${a.status}">${a.status}</span></td>
                    <td>
                        <button class="secundario" onclick="editarAgenda(${a.id})">Editar</button>
                        <button class="secundario" onclick="excluirAgenda(${a.id})">Excluir</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhum horário cadastrado.</td></tr>';
        }

        function dataHoraParaInput(dataHoraIso) {
            const d = new Date(dataHoraIso);
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function editarAgenda(id) {
            const a = agendaCache.find(x => x.id === id);
            if (!a) return;
            preencherSelectVendedorAtendente();
            document.getElementById('ag-id').value = a.id;
            document.getElementById('ag-data').value = dataHoraParaInput(a.data_hora);
            document.getElementById('ag-vagas').value = a.vagas_total;
            document.getElementById('ag-valor').value = a.valor_visita;
            document.getElementById('ag-vendedor').value = a.vendedor_id ?? '';
            document.getElementById('ag-atendente').value = a.atendente_id ?? '';
            document.getElementById('ag-botao').textContent = 'Salvar edição';
            document.getElementById('ag-cancelar').style.display = 'inline-block';
        }

        function limparFormularioAgenda() {
            document.getElementById('ag-id').value = '';
            document.getElementById('ag-data').value = '';
            document.getElementById('ag-vagas').value = '';
            document.getElementById('ag-valor').value = '';
            document.getElementById('ag-vendedor').value = '';
            document.getElementById('ag-atendente').value = '';
            document.getElementById('ag-botao').textContent = 'Adicionar horário';
            document.getElementById('ag-cancelar').style.display = 'none';
        }

        async function excluirAgenda(id) {
            if (!confirm('Excluir este horário da agenda?')) return;
            const resp = await fetch(`${base}/visitas/${id}`, { method: 'DELETE', headers: headersJson });
            const msg = document.getElementById('msg-agenda');
            if (!resp.ok) {
                const resposta = await resp.json().catch(() => ({}));
                msg.className = 'msg erro'; msg.textContent = resposta.message || 'Não foi possível excluir.';
                return;
            }
            msg.className = 'msg ok'; msg.textContent = 'Horário excluído.';
            carregarAgenda();
        }

        async function salvarAgenda() {
            const id = document.getElementById('ag-id').value;
            const dados = {
                // O input datetime-local não carrega fuso horário - o navegador
                // trata o valor como hora local, mas o servidor guarda tudo em
                // UTC (config('app.timezone') = UTC). Sem essa conversão, o
                // texto ia direto pro backend e era interpretado como se já
                // fosse UTC, deslocando o horário salvo (ex.: 10:00 virava
                // 07:00 na exibição, diferença de 3h do fuso de Brasília).
                data_hora: new Date(document.getElementById('ag-data').value).toISOString(),
                vagas_total: Number(document.getElementById('ag-vagas').value),
                valor_visita: Number(document.getElementById('ag-valor').value),
                vendedor_id: document.getElementById('ag-vendedor').value || null,
                atendente_id: document.getElementById('ag-atendente').value || null,
            };
            const url = id ? `${base}/visitas/${id}` : `${base}/visitas`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-agenda');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Horário atualizado.' : 'Horário adicionado.';
            limparFormularioAgenda();
            carregarAgenda();
        }

        let produtosCache = [];
        let fornecedoresCache = [];

        const ICONE_EDITAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
        const ICONE_EXCLUIR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';

        async function carregarProdutos() {
            await Promise.all([carregarFornecedoresParaSelect(), carregarTabelasFiscaisProduto()]);
            const resp = await fetch(`${base}/produtos`);
            produtosCache = await resp.json();
            document.getElementById('tbody-produtos').innerHTML = produtosCache.map(p => `
                <tr>
                    <td>${p.codigo ?? '-'}</td>
                    <td>${esc(p.nome)}${p.eh_kit ? ' <span class="selo info">kit</span>' : ''}${p.somente_loja_virtual ? ' <span class="selo info">só loja virtual</span>' : ''}</td>
                    <td>${p.categoria ?? '-'}</td>
                    <td>${p.tipo}</td>
                    <td>R$ ${Number(p.preco_venda).toFixed(2)}</td>
                    <td>${p.estoque_atual ?? '-'}</td>
                    <td>${p.fornecedor ? p.fornecedor.razao_social : '-'}</td>
                    <td>${p.ncm ?? '-'}</td>
                    <td>${p.cfop_padrao ?? '-'}</td>
                    <td>${p.ativo ? '<span class="selo ok">Sim</span>' : '<span class="selo off">Não</span>'}</td>
                    <td class="acoes-linha">
                        <span class="grupo">
                            <button type="button" class="btn-icone" title="Editar produto" aria-label="Editar ${esc(p.nome)}" onclick="editarProduto(${p.id})">${ICONE_EDITAR}</button>
                            <button type="button" class="btn-icone perigo" title="Excluir produto (se já teve movimento, apenas desativa)" aria-label="Excluir ${esc(p.nome)}" onclick="excluirProduto(${p.id})">${ICONE_EXCLUIR}</button>
                        </span>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="11">Nenhum produto cadastrado.</td></tr>';
        }

        // Sem movimento: exclui. Com movimento (vendas, compras, notas, agenda, kit): desativa.
        async function excluirProduto(id) {
            const p = produtosCache.find(x => x.id === id);
            if (!p) return;
            if (!confirm(`Excluir "${p.nome}"?\n\nSe o produto já tiver movimento (vendas, compras, notas fiscais, agenda ou uso em kit), ele não é apagado: fica apenas desativado.`)) return;

            const resp = await fetch(`${base}/produtos/${id}`, { method: 'DELETE', headers: headersJson });
            const dados = await resp.json();
            const msg = document.getElementById('msg-produtos-lista');
            msg.className = resp.ok ? (dados.acao === 'excluido' ? 'msg ok' : 'msg') : 'msg erro';
            msg.textContent = dados.message || JSON.stringify(dados.errors);
            if (resp.ok) {
                if (document.getElementById('pr-id').value == id) limparFormularioProduto();
                carregarProdutos();
            }
        }

        async function carregarFornecedoresParaSelect() {
            const resp = await fetch(`${base}/fornecedores`);
            fornecedoresCache = await resp.json();
            document.getElementById('pr-fornecedor').innerHTML = '<option value="">Nenhum</option>' +
                fornecedoresCache.map(f => `<option value="${f.id}">${f.razao_social}</option>`).join('');
        }

        async function carregarTabelasFiscaisProduto() {
            const [classTrib, credPres] = await Promise.all([
                fetch(`${base}/tab-cclasstrib`).then(r => r.json()),
                fetch(`${base}/tab-ccredpres`).then(r => r.json()),
            ]);
            document.getElementById('pr-cclasstrib').innerHTML = '<option value="">Selecione</option>' +
                classTrib.map(c => `<option value="${c.id}">${c.codigo} - ${c.descricao}</option>`).join('');
            document.getElementById('pr-ccredpres').innerHTML = '<option value="">Nenhum</option>' +
                credPres.map(c => `<option value="${c.id}">${c.codigo} - ${c.descricao}</option>`).join('');
        }

        function mostrarAbaProduto(nome, botao) {
            document.querySelectorAll('.aba-conteudo-produto').forEach(el => el.classList.remove('ativa'));
            document.getElementById(`aba-produto-${nome}`).classList.add('ativa');
            document.querySelectorAll('.abas-produto button').forEach(b => b.classList.remove('ativa'));
            botao.classList.add('ativa');
        }

        function editarProduto(id) {
            const p = produtosCache.find(x => x.id === id);
            if (!p) return;
            document.getElementById('pr-id').value = p.id;
            carregarTamanhosProduto(p.id);
            carregarKitProduto(p.id);
            // Geral
            document.getElementById('pr-codigo').value = p.codigo ?? '';
            document.getElementById('pr-codigo-barras').value = p.codigo_barras ?? '';
            document.getElementById('pr-nome').value = p.nome;
            document.getElementById('pr-categoria').value = p.categoria ?? '';
            document.getElementById('pr-grupo').value = p.grupo_id ?? '';
            document.getElementById('pr-tipo').value = p.tipo;
            document.getElementById('pr-tipo-fiscal').value = p.tipo_produto_fiscal ?? 'produto';
            document.getElementById('pr-unidade').value = p.unidade ?? 'UN';
            document.getElementById('pr-fornecedor').value = p.fornecedor_id ?? '';
            document.getElementById('pr-pesavel').checked = !!p.pesavel;
            document.getElementById('pr-ativo').checked = !!p.ativo;
            document.getElementById('pr-loja-virtual').checked = !!p.loja_virtual;
            document.getElementById('pr-somente-loja-virtual').checked = !!p.somente_loja_virtual;
            document.getElementById('pr-preco').value = p.preco_venda;
            document.getElementById('pr-custo').value = p.preco_custo ?? '';
            document.getElementById('pr-valor-atacado').value = p.valor_atacado ?? '';
            document.getElementById('pr-estoque').value = p.estoque_atual ?? '';
            document.getElementById('pr-estoque-minimo').value = p.estoque_minimo ?? '';
            document.getElementById('pr-quantidade-minima-venda').value = p.quantidade_minima_venda ?? '';
            document.getElementById('pr-peso-liquido').value = p.peso_liquido ?? '';
            document.getElementById('pr-peso-bruto').value = p.peso_bruto ?? '';
            document.getElementById('pr-imagem').value = p.imagem_url ?? '';
            document.getElementById('pr-imagem-arquivo').value = '';
            document.getElementById('pr-imagem-origem-url').checked = true;
            document.getElementById('pr-imagem-origem-arquivo').checked = false;
            alternarOrigemImagemProduto();
            atualizarPreviewImagemProduto();
            document.getElementById('pr-descricao').value = p.descricao ?? '';
            // ICMS/PIS/COFINS/IPI
            document.getElementById('pr-ncm').value = p.ncm ?? '';
            document.getElementById('pr-cest').value = p.cest ?? '';
            document.getElementById('pr-cfop').value = p.cfop_padrao ?? '';
            document.getElementById('pr-cfop-interestadual').value = p.cfop_interestadual ?? '';
            document.getElementById('pr-cst-origem').value = p.cst_origem ?? '';
            document.getElementById('pr-cst-icms').value = p.cst_icms ?? '';
            document.getElementById('pr-aliquota-icms').value = p.aliquota_icms ?? '';
            document.getElementById('pr-reducao-bc-icms').value = p.reducao_base_calculo_icms ?? '';
            document.getElementById('pr-fcp').value = p.fcp_percentual ?? '';
            document.getElementById('pr-mva').value = p.mva_percentual ?? '';
            document.getElementById('pr-grupo-fiscal').value = p.grupo_fiscal ?? '';
            document.getElementById('pr-codigo-beneficio').value = p.codigo_beneficio_fiscal ?? '';
            document.getElementById('pr-cst-pis').value = p.cst_pis ?? '';
            document.getElementById('pr-aliquota-pis').value = p.aliquota_pis ?? '';
            document.getElementById('pr-cst-cofins').value = p.cst_cofins ?? '';
            document.getElementById('pr-aliquota-cofins').value = p.aliquota_cofins ?? '';
            document.getElementById('pr-natureza-receita').value = p.natureza_receita_pis_cofins ?? '';
            document.getElementById('pr-cst-ipi').value = p.cst_ipi ?? '';
            document.getElementById('pr-aliquota-ipi').value = p.aliquota_ipi ?? '';
            document.getElementById('pr-enquadramento-ipi').value = p.codigo_enquadramento_ipi ?? '';
            // IBS/CBS
            document.getElementById('pr-situacao-novo-regime').value = p.situacao_novo_regime ?? '0';
            document.getElementById('pr-cst-ibscbs').value = p.cst_ibs_cbs ?? '';
            document.getElementById('pr-cclasstrib').value = p.cclasstrib_id ?? '';
            document.getElementById('pr-aliquota-ibs').value = p.aliquota_ibs ?? '';
            document.getElementById('pr-aliquota-cbs').value = p.aliquota_cbs ?? '';
            document.getElementById('pr-reducao-bc-ibs').value = p.reducao_base_calculo_ibs ?? '';
            document.getElementById('pr-reducao-bc-cbs').value = p.reducao_base_calculo_cbs ?? '';
            document.getElementById('pr-credito-ibs').value = p.percentual_credito_ibs ?? '';
            document.getElementById('pr-credito-cbs').value = p.percentual_credito_cbs ?? '';
            document.getElementById('pr-ccredpres').value = p.ccredpres_id ?? '';
            // Imposto Seletivo
            document.getElementById('pr-sujeito-is').checked = !!p.sujeito_imposto_seletivo;
            document.getElementById('pr-tipo-is').value = p.tipo_imposto_seletivo ?? '';
            document.getElementById('pr-cclasstrib-is').value = p.cclasstrib_is ?? '';
            document.getElementById('pr-aliquota-is').value = p.aliquota_is ?? '';
            // Destinação
            document.getElementById('pr-destinacao').value = p.destinacao_tributaria ?? '';
            document.getElementById('pr-tipo-credito').value = p.tipo_credito ?? '';

            document.getElementById('pr-botao').textContent = 'Salvar edição';
            document.getElementById('pr-cancelar').style.display = 'inline-block';
            document.getElementById('secao-produtos').scrollIntoView({ behavior: 'smooth' });
        }

        function alternarOrigemImagemProduto() {
            const porArquivo = document.getElementById('pr-imagem-origem-arquivo').checked;
            document.getElementById('pr-imagem').style.display = porArquivo ? 'none' : '';
            document.getElementById('pr-imagem-arquivo').style.display = porArquivo ? '' : 'none';
        }

        function atualizarPreviewImagemProduto() {
            const preview = document.getElementById('pr-imagem-preview');
            const arquivo = document.getElementById('pr-imagem-arquivo').files[0];
            if (arquivo) {
                preview.src = URL.createObjectURL(arquivo);
                preview.style.display = '';
                return;
            }
            const url = document.getElementById('pr-imagem').value;
            if (url) {
                preview.src = url;
                preview.style.display = '';
            } else {
                preview.style.display = 'none';
                preview.src = '';
            }
        }

        document.getElementById('pr-imagem-origem-url').addEventListener('change', () => { alternarOrigemImagemProduto(); atualizarPreviewImagemProduto(); });
        document.getElementById('pr-imagem-origem-arquivo').addEventListener('change', () => { alternarOrigemImagemProduto(); atualizarPreviewImagemProduto(); });
        document.getElementById('pr-imagem').addEventListener('input', atualizarPreviewImagemProduto);
        document.getElementById('pr-imagem-arquivo').addEventListener('change', atualizarPreviewImagemProduto);

        function limparFormularioProduto() {
            document.getElementById('pr-id').value = '';
            [
                'pr-codigo', 'pr-codigo-barras', 'pr-nome', 'pr-categoria', 'pr-custo', 'pr-valor-atacado',
                'pr-estoque', 'pr-estoque-minimo', 'pr-quantidade-minima-venda', 'pr-peso-liquido', 'pr-peso-bruto', 'pr-imagem', 'pr-descricao',
                'pr-ncm', 'pr-cest', 'pr-cfop', 'pr-cfop-interestadual', 'pr-cst-origem', 'pr-cst-icms',
                'pr-aliquota-icms', 'pr-reducao-bc-icms', 'pr-fcp', 'pr-mva', 'pr-grupo-fiscal', 'pr-codigo-beneficio',
                'pr-cst-pis', 'pr-aliquota-pis', 'pr-cst-cofins', 'pr-aliquota-cofins', 'pr-natureza-receita',
                'pr-cst-ipi', 'pr-aliquota-ipi', 'pr-enquadramento-ipi', 'pr-cst-ibscbs', 'pr-cclasstrib',
                'pr-aliquota-ibs', 'pr-aliquota-cbs', 'pr-reducao-bc-ibs', 'pr-reducao-bc-cbs', 'pr-credito-ibs',
                'pr-credito-cbs', 'pr-ccredpres', 'pr-tipo-is', 'pr-cclasstrib-is', 'pr-aliquota-is',
                'pr-destinacao', 'pr-tipo-credito',
            ].forEach(id => document.getElementById(id).value = '');
            document.getElementById('pr-preco').value = '';
            document.getElementById('pr-unidade').value = 'UN';
            document.getElementById('pr-fornecedor').value = '';
            document.getElementById('pr-grupo').value = '';
            document.getElementById('pr-tipo-fiscal').value = 'produto';
            document.getElementById('pr-situacao-novo-regime').value = '0';
            document.getElementById('pr-pesavel').checked = false;
            document.getElementById('pr-ativo').checked = true;
            document.getElementById('pr-loja-virtual').checked = true;
            document.getElementById('pr-somente-loja-virtual').checked = false;
            document.getElementById('pr-sujeito-is').checked = false;
            document.getElementById('pr-imagem-arquivo').value = '';
            document.getElementById('pr-imagem-origem-url').checked = true;
            document.getElementById('pr-imagem-origem-arquivo').checked = false;
            alternarOrigemImagemProduto();
            atualizarPreviewImagemProduto();
            document.getElementById('pr-botao').textContent = 'Cadastrar';
            document.getElementById('pr-cancelar').style.display = 'none';
            carregarTamanhosProduto(null);
            carregarKitProduto(null);
        }

        // ---- Kit (caneca + cervejas à escolha) ----

        // Produtos que podem entrar num kit: qualquer um que não seja kit nem o próprio produto.
        function opcoesComponenteKit(produtoAtualId, selecionadoId) {
            return produtosCache
                .filter(x => !x.eh_kit && x.id !== Number(produtoAtualId))
                .map(x => {
                    const ativas = (x.variacoes || []).filter(v => v.ativo).length;
                    return `<option value="${x.id}" data-variacoes="${ativas}" ${x.id === selecionadoId ? 'selected' : ''}>${esc(x.nome)}${ativas ? ` (${ativas} variações)` : ''}</option>`;
                }).join('');
        }

        function linhaComponenteKit(produtoAtualId, comp) {
            const ativas = comp ? (produtosCache.find(x => x.id === comp.produto_id)?.variacoes || []).filter(v => v.ativo).length : 0;
            return `
                <tr>
                    <td><select class="kit-produto" onchange="atualizarLinhaKit(this)">
                        <option value="">Selecione...</option>${opcoesComponenteKit(produtoAtualId, comp?.produto_id)}
                    </select></td>
                    <td class="kit-modo">${comp ? (ativas ? 'Cliente escolhe entre as variações' : 'Fixo (sempre vem)') : '-'}</td>
                    <td><input type="number" class="kit-quantidade" min="1" max="99" value="${comp?.quantidade ?? 1}" style="width:80px"></td>
                    <td><button type="button" class="secundario" onclick="this.closest('tr').remove()">Remover</button></td>
                </tr>`;
        }

        function atualizarLinhaKit(select) {
            const ativas = Number(select.selectedOptions[0]?.dataset.variacoes || 0);
            select.closest('tr').querySelector('.kit-modo').textContent = !select.value ? '-' : ativas ? 'Cliente escolhe entre as variações' : 'Fixo (sempre vem)';
        }

        function adicionarComponenteKit() {
            const produtoId = document.getElementById('bloco-pr-kit').dataset.produtoId;
            document.getElementById('tbody-pr-kit').insertAdjacentHTML('beforeend', linhaComponenteKit(produtoId, null));
        }

        function alternarKitProduto() {
            document.getElementById('pr-kit-composicao').style.display = document.getElementById('pr-eh-kit').checked ? '' : 'none';
        }

        async function carregarKitProduto(produtoId) {
            const aviso = document.getElementById('msg-pr-kit-aviso');
            const bloco = document.getElementById('bloco-pr-kit');
            document.getElementById('msg-pr-kit').textContent = '';
            if (!produtoId) {
                aviso.style.display = 'block';
                bloco.style.display = 'none';
                document.getElementById('tbody-pr-kit').innerHTML = '';
                return;
            }
            aviso.style.display = 'none';
            bloco.style.display = 'block';
            bloco.dataset.produtoId = produtoId;
            const resp = await fetch(`${base}/produtos/${produtoId}/kit`, { headers: { 'Accept': 'application/json' } });
            const dados = await resp.json();
            document.getElementById('pr-eh-kit').checked = !!dados.eh_kit;
            document.getElementById('pr-kit-total-escolhas').value = dados.kit_total_escolhas ?? '';
            document.getElementById('tbody-pr-kit').innerHTML = dados.componentes.map(c => linhaComponenteKit(produtoId, c)).join('');
            alternarKitProduto();
        }

        async function salvarKitProduto() {
            const bloco = document.getElementById('bloco-pr-kit');
            const produtoId = bloco.dataset.produtoId;
            const ehKit = document.getElementById('pr-eh-kit').checked;
            const componentes = Array.from(document.querySelectorAll('#tbody-pr-kit tr'))
                .map(tr => {
                    const sel = tr.querySelector('.kit-produto');
                    const ativas = Number(sel.selectedOptions[0]?.dataset.variacoes || 0);
                    return {
                        produto_id: Number(sel.value),
                        tipo: ativas ? 'escolha' : 'fixo',
                        quantidade: Number(tr.querySelector('.kit-quantidade').value || 1),
                    };
                })
                .filter(c => c.produto_id);

            const resp = await fetch(`${base}/produtos/${produtoId}/kit`, {
                method: 'PUT', headers: headersJson, body: JSON.stringify({ eh_kit: ehKit, kit_total_escolhas: Number(document.getElementById('pr-kit-total-escolhas').value) || null, componentes }),
            });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-pr-kit');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = ehKit ? 'Kit salvo.' : 'Kit desativado.';
            await carregarProdutos();
            carregarKitProduto(produtoId);
        }

        async function carregarTamanhosProduto(produtoId) {
            const aviso = document.getElementById('msg-pr-tamanhos-aviso');
            const bloco = document.getElementById('bloco-pr-tamanhos');
            if (!produtoId) {
                aviso.style.display = 'block';
                bloco.style.display = 'none';
                document.getElementById('tbody-pr-tamanhos').innerHTML = '';
                return;
            }
            aviso.style.display = 'none';
            bloco.style.display = 'block';
            bloco.dataset.produtoId = produtoId;
            preencherOpcoesVinculo(produtoId);
            const resp = await fetch(`${base}/produtos/${produtoId}/variacoes`, { headers: headersJson });
            const lista = await resp.json();
            renderizarTamanhosProduto(lista);
        }

        function renderizarTamanhosProduto(lista) {
            const tbody = document.getElementById('tbody-pr-tamanhos');
            tbody.innerHTML = '';
            lista.forEach((v) => {
                const tr = document.createElement('tr');
                const estoqueCelula = v.produto_vinculado
                    ? `<span title="O estoque vem do produto vinculado">usa o estoque de <strong>${esc(v.produto_vinculado.nome)}</strong>: ${v.produto_vinculado.estoque_atual ?? 'ilimitado'}</span>`
                    : `<input type="number" min="0" value="${v.estoque_atual}" style="width:90px" onchange="atualizarTamanhoProduto(${v.id}, { estoque_atual: Number(this.value) })">`;
                tr.innerHTML = `
                    <td>${esc(v.tamanho)}</td>
                    <td>${estoqueCelula}</td>
                    <td><input type="checkbox" ${v.ativo ? 'checked' : ''} onchange="atualizarTamanhoProduto(${v.id}, { ativo: this.checked })"></td>
                    <td><button type="button" onclick="removerTamanhoProduto(${v.id})">Remover</button></td>
                `;
                tbody.appendChild(tr);
            });
        }

        async function adicionarTamanhoProduto() {
            const produtoId = document.getElementById('bloco-pr-tamanhos').dataset.produtoId;
            const tamanho = document.getElementById('pr-tam-novo-tamanho').value.trim();
            const estoque = Number(document.getElementById('pr-tam-novo-estoque').value || 0);
            const vinculo = document.getElementById('pr-tam-novo-vinculo').value;
            const msg = document.getElementById('msg-pr-tamanhos');
            if (!tamanho) { msg.className = 'msg erro'; msg.textContent = 'Informe o tamanho.'; return; }
            const resp = await fetch(`${base}/produtos/${produtoId}/variacoes`, {
                method: 'POST', headers: headersJson,
                body: JSON.stringify({ tamanho, estoque_atual: estoque, produto_vinculado_id: vinculo ? Number(vinculo) : null }),
            });
            const resposta = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Tamanho adicionado.';
            document.getElementById('pr-tam-novo-tamanho').value = '';
            document.getElementById('pr-tam-novo-estoque').value = '0';
            document.getElementById('pr-tam-novo-vinculo').value = '';
            vinculoTamanhoMudou();
            carregarTamanhosProduto(produtoId);
        }

        // Produtos que podem ser vinculados: ativos, sem variações próprias, que não sejam kit nem o próprio produto.
        function preencherOpcoesVinculo(produtoId) {
            document.getElementById('pr-tam-novo-vinculo').innerHTML = '<option value="">Não - estoque próprio</option>' +
                produtosCache
                    .filter(x => x.ativo && !x.eh_kit && x.tipo === 'fisico' && x.id !== Number(produtoId) && (x.variacoes || []).length === 0)
                    .map(x => `<option value="${x.id}">${esc(x.nome)}</option>`).join('');
        }

        function vinculoTamanhoMudou() {
            const select = document.getElementById('pr-tam-novo-vinculo');
            const produto = produtosCache.find(x => x.id === Number(select.value));
            const estoque = document.getElementById('pr-tam-novo-estoque');
            estoque.disabled = !!produto;
            if (!produto) return;

            // sugere o nome do tipo: o do produto, sem a palavra inicial que ele divide com o produto pai (ex.: "CERVEJA ")
            const primeira = (document.getElementById('pr-nome').value || '').trim().split(/\s+/)[0];
            const nome = produto.nome.trim();
            document.getElementById('pr-tam-novo-tamanho').value =
                (primeira && nome.toUpperCase().startsWith(primeira.toUpperCase() + ' ') ? nome.slice(primeira.length + 1) : nome).slice(0, 40);
        }

        async function atualizarTamanhoProduto(variacaoId, campos) {
            const produtoId = document.getElementById('bloco-pr-tamanhos').dataset.produtoId;
            await fetch(`${base}/produtos/${produtoId}/variacoes/${variacaoId}`, {
                method: 'PUT', headers: headersJson, body: JSON.stringify(campos),
            });
        }

        async function removerTamanhoProduto(variacaoId) {
            if (!confirm('Remover este tamanho? O estoque dele será perdido.')) return;
            const produtoId = document.getElementById('bloco-pr-tamanhos').dataset.produtoId;
            await fetch(`${base}/produtos/${produtoId}/variacoes/${variacaoId}`, { method: 'DELETE', headers: headersJson });
            carregarTamanhosProduto(produtoId);
        }

        async function salvarProduto() {
            const id = document.getElementById('pr-id').value;
            const dados = {
                codigo: document.getElementById('pr-codigo').value || null,
                codigo_barras: document.getElementById('pr-codigo-barras').value || null,
                nome: document.getElementById('pr-nome').value,
                categoria: document.getElementById('pr-categoria').value || null,
                grupo_id: document.getElementById('pr-grupo').value || null,
                tipo: document.getElementById('pr-tipo').value,
                tipo_produto_fiscal: document.getElementById('pr-tipo-fiscal').value,
                unidade: document.getElementById('pr-unidade').value || 'UN',
                fornecedor_id: document.getElementById('pr-fornecedor').value || null,
                pesavel: document.getElementById('pr-pesavel').checked,
                ativo: document.getElementById('pr-ativo').checked,
                loja_virtual: document.getElementById('pr-loja-virtual').checked || document.getElementById('pr-somente-loja-virtual').checked,
                somente_loja_virtual: document.getElementById('pr-somente-loja-virtual').checked,
                preco_venda: Number(document.getElementById('pr-preco').value),
                preco_custo: document.getElementById('pr-custo').value || null,
                valor_atacado: document.getElementById('pr-valor-atacado').value || null,
                estoque_atual: document.getElementById('pr-estoque').value || null,
                estoque_minimo: document.getElementById('pr-estoque-minimo').value || null,
                quantidade_minima_venda: document.getElementById('pr-quantidade-minima-venda').value || null,
                peso_liquido: document.getElementById('pr-peso-liquido').value || null,
                peso_bruto: document.getElementById('pr-peso-bruto').value || null,
                imagem_url: document.getElementById('pr-imagem').value || null,
                descricao: document.getElementById('pr-descricao').value || null,

                ncm: document.getElementById('pr-ncm').value || null,
                cest: document.getElementById('pr-cest').value || null,
                cfop_padrao: document.getElementById('pr-cfop').value || null,
                cfop_interestadual: document.getElementById('pr-cfop-interestadual').value || null,
                cst_origem: document.getElementById('pr-cst-origem').value || null,
                cst_icms: document.getElementById('pr-cst-icms').value || null,
                aliquota_icms: document.getElementById('pr-aliquota-icms').value || null,
                reducao_base_calculo_icms: document.getElementById('pr-reducao-bc-icms').value || null,
                fcp_percentual: document.getElementById('pr-fcp').value || null,
                mva_percentual: document.getElementById('pr-mva').value || null,
                grupo_fiscal: document.getElementById('pr-grupo-fiscal').value || null,
                codigo_beneficio_fiscal: document.getElementById('pr-codigo-beneficio').value || null,
                cst_pis: document.getElementById('pr-cst-pis').value || null,
                aliquota_pis: document.getElementById('pr-aliquota-pis').value || null,
                cst_cofins: document.getElementById('pr-cst-cofins').value || null,
                aliquota_cofins: document.getElementById('pr-aliquota-cofins').value || null,
                natureza_receita_pis_cofins: document.getElementById('pr-natureza-receita').value || null,
                cst_ipi: document.getElementById('pr-cst-ipi').value || null,
                aliquota_ipi: document.getElementById('pr-aliquota-ipi').value || null,
                codigo_enquadramento_ipi: document.getElementById('pr-enquadramento-ipi').value || null,

                situacao_novo_regime: document.getElementById('pr-situacao-novo-regime').value,
                cst_ibs_cbs: document.getElementById('pr-cst-ibscbs').value || null,
                cclasstrib_id: document.getElementById('pr-cclasstrib').value || null,
                aliquota_ibs: document.getElementById('pr-aliquota-ibs').value || null,
                aliquota_cbs: document.getElementById('pr-aliquota-cbs').value || null,
                reducao_base_calculo_ibs: document.getElementById('pr-reducao-bc-ibs').value || null,
                reducao_base_calculo_cbs: document.getElementById('pr-reducao-bc-cbs').value || null,
                percentual_credito_ibs: document.getElementById('pr-credito-ibs').value || null,
                percentual_credito_cbs: document.getElementById('pr-credito-cbs').value || null,
                ccredpres_id: document.getElementById('pr-ccredpres').value || null,

                sujeito_imposto_seletivo: document.getElementById('pr-sujeito-is').checked,
                tipo_imposto_seletivo: document.getElementById('pr-tipo-is').value || null,
                cclasstrib_is: document.getElementById('pr-cclasstrib-is').value || null,
                aliquota_is: document.getElementById('pr-aliquota-is').value || null,

                destinacao_tributaria: document.getElementById('pr-destinacao').value || null,
                tipo_credito: document.getElementById('pr-tipo-credito').value || null,
            };
            const url = id ? `${base}/produtos/${id}` : `${base}/produtos`;
            const arquivoImagem = document.getElementById('pr-imagem-origem-arquivo').checked
                ? document.getElementById('pr-imagem-arquivo').files[0]
                : null;

            let resp;
            if (arquivoImagem) {
                delete dados.imagem_url;
                const formData = new FormData();
                Object.entries(dados).forEach(([chave, valor]) => {
                    if (valor === null || valor === undefined) return;
                    formData.append(chave, typeof valor === 'boolean' ? (valor ? '1' : '0') : valor);
                });
                formData.append('imagem', arquivoImagem);
                if (id) formData.append('_method', 'PUT');
                resp = await fetch(id ? `${base}/produtos/${id}` : url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });
            } else {
                resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            }
            const resposta = await resp.json();
            const msg = document.getElementById('msg-produtos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Produto atualizado.' : 'Produto cadastrado.';
            limparFormularioProduto();
            carregarProdutos();
        }

        // Consulta pública de CNPJ (BrasilAPI, dados da Receita Federal - sem
        // chave) e de CEP (ViaCEP) - usadas nos cadastros de cliente e
        // fornecedor para reduzir digitação manual. Nenhuma das duas
        // devolve Inscrição Estadual (é cadastrada por estado, não existe
        // fonte nacional gratuita) - esse campo continua manual.
        async function consultarCnpjEmBrasilApi(cnpj) {
            const digitos = (cnpj || '').replace(/\D/g, '');
            if (digitos.length !== 14) { throw new Error('Informe um CNPJ válido (14 dígitos) para buscar.'); }
            const resp = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${digitos}`);
            if (!resp.ok) { throw new Error('CNPJ não encontrado ou serviço de consulta indisponível no momento.'); }
            return resp.json();
        }

        async function consultarCepEmViaCep(cep) {
            const digitos = (cep || '').replace(/\D/g, '');
            if (digitos.length !== 8) { return null; }
            const resp = await fetch(`https://viacep.com.br/ws/${digitos}/json/`);
            const dados = await resp.json();
            if (dados.erro) { throw new Error('CEP não encontrado.'); }
            return dados;
        }

        async function buscarCnpjCliente() {
            const msg = document.getElementById('msg-clientes');
            try {
                const d = await consultarCnpjEmBrasilApi(document.getElementById('cl-cpf-cnpj').value);
                if (d.razao_social) document.getElementById('cl-nome').value = d.razao_social;
                document.getElementById('cl-cep').value = d.cep ?? '';
                document.getElementById('cl-logradouro').value = d.logradouro ?? '';
                document.getElementById('cl-numero').value = d.numero ?? '';
                document.getElementById('cl-bairro').value = d.bairro ?? '';
                document.getElementById('cl-municipio').value = d.municipio ?? '';
                document.getElementById('cl-uf').value = d.uf ?? '';
                document.getElementById('cl-ibge').value = d.codigo_municipio_ibge ?? '';
                if (d.ddd_telefone_1) document.getElementById('cl-telefone').value = d.ddd_telefone_1;
                if (d.email) document.getElementById('cl-email').value = d.email;
                msg.className = 'msg ok';
                msg.textContent = 'Dados do CNPJ preenchidos - confira a Inscrição Estadual manualmente.';
            } catch (e) {
                msg.className = 'msg erro'; msg.textContent = e.message;
            }
        }

        async function buscarCepCliente() {
            try {
                const d = await consultarCepEmViaCep(document.getElementById('cl-cep').value);
                if (!d) return;
                document.getElementById('cl-logradouro').value = d.logradouro || document.getElementById('cl-logradouro').value;
                document.getElementById('cl-bairro').value = d.bairro || document.getElementById('cl-bairro').value;
                document.getElementById('cl-municipio').value = d.localidade || document.getElementById('cl-municipio').value;
                document.getElementById('cl-uf').value = d.uf || document.getElementById('cl-uf').value;
                document.getElementById('cl-ibge').value = d.ibge || document.getElementById('cl-ibge').value;
            } catch (e) {
                document.getElementById('msg-clientes').className = 'msg erro';
                document.getElementById('msg-clientes').textContent = e.message;
            }
        }

        async function buscarCnpjFornecedor() {
            const msg = document.getElementById('msg-fornecedores');
            try {
                const d = await consultarCnpjEmBrasilApi(document.getElementById('fo-cnpj').value);
                if (d.razao_social) document.getElementById('fo-razao').value = d.razao_social;
                if (d.nome_fantasia) document.getElementById('fo-fantasia').value = d.nome_fantasia;
                if (d.ddd_telefone_1) document.getElementById('fo-telefone').value = d.ddd_telefone_1;
                if (d.email) document.getElementById('fo-email').value = d.email;
                document.getElementById('fo-cep').value = d.cep ?? '';
                document.getElementById('fo-endereco').value = [d.logradouro, d.numero, d.bairro, d.municipio, d.uf, d.cep]
                    .filter(Boolean).join(', ');
                msg.className = 'msg ok';
                msg.textContent = 'Dados do CNPJ preenchidos - confira a Inscrição Estadual manualmente.';
            } catch (e) {
                msg.className = 'msg erro'; msg.textContent = e.message;
            }
        }

        async function buscarCepFornecedor() {
            try {
                const d = await consultarCepEmViaCep(document.getElementById('fo-cep').value);
                if (!d) return;
                document.getElementById('fo-endereco').value = [d.logradouro, d.bairro, d.localidade, d.uf, document.getElementById('fo-cep').value]
                    .filter(Boolean).join(', ');
            } catch (e) {
                document.getElementById('msg-fornecedores').className = 'msg erro';
                document.getElementById('msg-fornecedores').textContent = e.message;
            }
        }

        let comprasCache = [];
        let compraItensRascunho = [];
        let compraConferenciaAtual = null;

        async function carregarCompras() {
            await Promise.all([carregarFornecedoresParaSelect(), carregarProdutosParaSelectCompra()]);

            document.getElementById('co-fornecedor').innerHTML = fornecedoresCache
                .map(f => `<option value="${f.id}">${f.razao_social}</option>`).join('');

            if (!document.getElementById('co-data-entrada').value) {
                document.getElementById('co-data-entrada').value = new Date().toISOString().slice(0, 10);
            }

            const resp = await fetch(`${base}/compras`);
            comprasCache = await resp.json();
            document.getElementById('tbody-compras').innerHTML = comprasCache.map(c => `
                <tr>
                    <td>${c.numero_nota ?? '-'}</td>
                    <td>${c.fornecedor ? c.fornecedor.razao_social : '-'}</td>
                    <td>${c.data_entrada}</td>
                    <td>R$ ${Number(c.valor_total).toFixed(2)}</td>
                    <td>${c.status}</td>
                    <td>
                        ${c.status === 'pendente' ? `<button class="secundario" onclick="abrirConferenciaCompra(${c.id})">Conferir/Confirmar</button>` : ''}
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhuma nota de entrada registrada.</td></tr>';
        }

        async function carregarProdutosParaSelectCompra() {
            if (produtosCache.length) {
                preencherSelectProdutoCompra();
                return;
            }
            const resp = await fetch(`${base}/produtos`);
            produtosCache = await resp.json();
            preencherSelectProdutoCompra();
        }

        function preencherSelectProdutoCompra() {
            document.getElementById('co-item-produto').innerHTML = produtosCache
                .map(p => `<option value="${p.id}">${p.nome}</option>`).join('');
        }

        function adicionarItemCompra() {
            const produtoId = Number(document.getElementById('co-item-produto').value);
            const quantidade = Number(document.getElementById('co-item-quantidade').value);
            const valorUnitario = Number(document.getElementById('co-item-valor').value);
            const produto = produtosCache.find(p => p.id === produtoId);

            if (!produto || !quantidade || !valorUnitario) {
                return;
            }

            compraItensRascunho.push({ produto_id: produtoId, nome: produto.nome, quantidade, valor_unitario: valorUnitario });
            document.getElementById('co-item-quantidade').value = '';
            document.getElementById('co-item-valor').value = '';
            renderizarItensCompraRascunho();
        }

        function removerItemCompraRascunho(index) {
            compraItensRascunho.splice(index, 1);
            renderizarItensCompraRascunho();
        }

        function renderizarItensCompraRascunho() {
            document.getElementById('tbody-compra-itens').innerHTML = compraItensRascunho.map((item, index) => `
                <tr>
                    <td>${item.nome}</td>
                    <td>${item.quantidade}</td>
                    <td>R$ ${item.valor_unitario.toFixed(4)}</td>
                    <td>R$ ${(item.quantidade * item.valor_unitario).toFixed(2)}</td>
                    <td><button class="secundario" onclick="removerItemCompraRascunho(${index})">Remover</button></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum item adicionado.</td></tr>';
        }

        async function salvarCompraManual() {
            const msg = document.getElementById('msg-compra-manual');
            if (!compraItensRascunho.length) {
                msg.className = 'msg erro';
                msg.textContent = 'Adicione ao menos um item.';
                return;
            }

            const dados = {
                fornecedor_id: Number(document.getElementById('co-fornecedor').value),
                numero_nota: document.getElementById('co-numero').value || null,
                serie_nota: document.getElementById('co-serie').value || null,
                data_entrada: document.getElementById('co-data-entrada').value || null,
                valor_frete: Number(document.getElementById('co-frete').value) || 0,
                valor_desconto: Number(document.getElementById('co-desconto').value) || 0,
                itens: compraItensRascunho.map(i => ({ produto_id: i.produto_id, quantidade: i.quantidade, valor_unitario: i.valor_unitario })),
            };

            const resp = await fetch(`${base}/compras`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            if (resp.ok) {
                msg.className = 'msg sucesso';
                msg.textContent = 'Entrada registrada. Confirme na lista de notas abaixo para dar baixa em estoque e gerar contas a pagar.';
                compraItensRascunho = [];
                renderizarItensCompraRascunho();
                document.getElementById('co-numero').value = '';
                document.getElementById('co-serie').value = '';
                carregarCompras();
            } else {
                const erro = await resp.json();
                msg.className = 'msg erro';
                msg.textContent = erro.message ?? 'Erro ao registrar entrada.';
            }
        }

        async function importarXmlCompra() {
            const msg = document.getElementById('msg-compra-xml');
            const arquivo = document.getElementById('co-xml-arquivo').files[0];
            if (!arquivo) {
                msg.className = 'msg erro';
                msg.textContent = 'Selecione um arquivo XML.';
                return;
            }

            const formData = new FormData();
            formData.append('xml', arquivo);

            const resp = await fetch(`${base}/compras/importar-xml`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });

            if (resp.ok) {
                const compra = await resp.json();
                msg.className = 'msg sucesso';
                msg.textContent = 'XML importado. Confira o vínculo dos itens abaixo antes de confirmar.';
                document.getElementById('co-xml-arquivo').value = '';
                await carregarCompras();
                abrirConferenciaCompra(compra.id);
            } else {
                const erro = await resp.json();
                msg.className = 'msg erro';
                msg.textContent = erro.message ?? 'Erro ao importar XML.';
            }
        }

        async function abrirConferenciaCompra(compraId) {
            const resp = await fetch(`${base}/compras/${compraId}`);
            compraConferenciaAtual = await resp.json();
            document.getElementById('compra-conf-id').textContent = compraConferenciaAtual.id;

            document.getElementById('tbody-compra-conferencia').innerHTML = compraConferenciaAtual.itens.map(item => `
                <tr>
                    <td>${item.descricao_xml ?? (item.produto ? item.produto.nome : '-')}</td>
                    <td>${item.quantidade}</td>
                    <td>R$ ${Number(item.valor_unitario).toFixed(4)}</td>
                    <td>
                        ${item.produto
                            ? item.produto.nome
                            : `<select id="conf-produto-${item.id}">
                                <option value="">Selecionar produto...</option>
                                ${produtosCache.map(p => `<option value="${p.id}">${p.nome}</option>`).join('')}
                                <option value="novo">+ Cadastrar novo produto</option>
                               </select>`
                        }
                    </td>
                    <td>
                        ${item.produto ? '' : `<button class="secundario" onclick="vincularItemCompra(${item.id})">Vincular</button>`}
                    </td>
                </tr>
            `).join('');

            document.getElementById('compra-conferencia-card').style.display = 'block';
            document.getElementById('compra-conferencia-card').scrollIntoView({ behavior: 'smooth' });
        }

        async function vincularItemCompra(itemId) {
            const select = document.getElementById(`conf-produto-${itemId}`);
            const valor = select.value;
            const msg = document.getElementById('msg-compra-conferencia');

            let dados;
            if (valor === 'novo') {
                const item = compraConferenciaAtual.itens.find(i => i.id === itemId);
                dados = { novo_produto: { nome: item.descricao_xml, preco_venda: item.valor_unitario } };
            } else if (valor) {
                dados = { produto_id: Number(valor) };
            } else {
                msg.className = 'msg erro';
                msg.textContent = 'Selecione um produto ou a opção de cadastrar novo.';
                return;
            }

            const resp = await fetch(`${base}/compras/${compraConferenciaAtual.id}/itens/${itemId}/vincular`, {
                method: 'PUT', headers: headersJson, body: JSON.stringify(dados),
            });

            if (resp.ok) {
                await abrirConferenciaCompra(compraConferenciaAtual.id);
            } else {
                const erro = await resp.json();
                msg.className = 'msg erro';
                msg.textContent = erro.message ?? 'Erro ao vincular item.';
            }
        }

        async function confirmarCompra() {
            const msg = document.getElementById('msg-compra-conferencia');
            const resp = await fetch(`${base}/compras/${compraConferenciaAtual.id}/confirmar`, {
                method: 'PUT', headers: headersJson,
            });

            if (resp.ok) {
                msg.className = 'msg sucesso';
                msg.textContent = 'Entrada confirmada: estoque atualizado e conta a pagar gerada.';
                document.getElementById('compra-conferencia-card').style.display = 'none';
                carregarCompras();
            } else {
                const erro = await resp.json();
                msg.className = 'msg erro';
                msg.textContent = erro.message ?? 'Erro ao confirmar entrada.';
            }
        }

        let clientesCache = [];

        async function carregarClientes() {
            const resp = await fetch(`${base}/clientes`);
            clientesCache = await resp.json();
            document.getElementById('tbody-clientes').innerHTML = clientesCache.map(c => `
                <tr>
                    <td>${esc(c.nome)}</td>
                    <td>${esc(c.cpf_cnpj ?? '-')}</td>
                    <td>${esc(c.email ?? '-')}</td>
                    <td>${esc(c.telefone ?? '-')}</td>
                    <td>${c.logradouro ? esc(`${c.logradouro}, ${c.numero} - ${c.municipio}/${c.uf}`) : '<em>incompleto</em>'}</td>
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
            ['fo-razao', 'fo-fantasia', 'fo-cnpj', 'fo-ie', 'fo-contato', 'fo-telefone', 'fo-email', 'fo-cep', 'fo-endereco']
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

        let vendedoresCache = [];

        async function carregarVendedores() {
            const resp = await fetch(`${base}/vendedores`);
            vendedoresCache = await resp.json();
            document.getElementById('tbody-vendedores').innerHTML = vendedoresCache.map(v => `
                <tr>
                    <td>${esc(v.nome)}</td>
                    <td>${esc(v.telefone || '-')}</td>
                    <td>${esc(v.chave_pix || '-')}</td>
                    <td>${v.percentual_comissao}%</td>
                    <td><button class="secundario" onclick="editarVendedor(${v.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum vendedor cadastrado.</td></tr>';
        }

        let vendedorEditandoId = null;

        function limparFormularioVendedor() {
            vendedorEditandoId = null;
            document.getElementById('ve-nome').value = '';
            document.getElementById('ve-telefone').value = '';
            document.getElementById('ve-pix').value = '';
            document.getElementById('ve-comissao').value = '5';
            document.getElementById('ve-botao').textContent = 'Cadastrar';
            document.getElementById('ve-cancelar').style.display = 'none';
        }

        function editarVendedor(id) {
            const v = vendedoresCache.find(x => x.id === id);
            if (!v) return;
            vendedorEditandoId = id;
            document.getElementById('ve-nome').value = v.nome;
            document.getElementById('ve-telefone').value = v.telefone ?? '';
            document.getElementById('ve-pix').value = v.chave_pix ?? '';
            document.getElementById('ve-comissao').value = v.percentual_comissao;
            document.getElementById('ve-botao').textContent = 'Salvar alterações';
            document.getElementById('ve-cancelar').style.display = '';
        }

        async function salvarVendedor() {
            const dados = {
                nome: document.getElementById('ve-nome').value,
                telefone: document.getElementById('ve-telefone').value || null,
                chave_pix: document.getElementById('ve-pix').value || null,
                percentual_comissao: document.getElementById('ve-comissao').value ? Number(document.getElementById('ve-comissao').value) : 5,
            };
            const editando = vendedorEditandoId !== null;
            const resp = await fetch(editando ? `${base}/vendedores/${vendedorEditandoId}` : `${base}/vendedores`, {
                method: editando ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados),
            });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-vendedores');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = editando ? 'Vendedor atualizado.' : 'Vendedor cadastrado.';
            limparFormularioVendedor();
            carregarVendedores();
        }

        let atendentesCache = [];

        async function carregarAtendentes() {
            const resp = await fetch(`${base}/atendentes`);
            atendentesCache = await resp.json();
            document.getElementById('tbody-atendentes').innerHTML = atendentesCache.map(a => `
                <tr>
                    <td>${a.nome}</td>
                    <td>${a.telefone || '-'}</td>
                    <td>${a.percentual_comissao}%</td>
                    <td>${a.ativo ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="editarAtendente(${a.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum atendente cadastrado.</td></tr>';
        }

        function editarAtendente(id) {
            const a = atendentesCache.find(x => x.id === id);
            if (!a) return;
            document.getElementById('at-id').value = a.id;
            document.getElementById('at-nome').value = a.nome;
            document.getElementById('at-telefone').value = a.telefone || '';
            document.getElementById('at-comissao').value = a.percentual_comissao;
            document.getElementById('at-botao').textContent = 'Salvar edição';
            document.getElementById('at-cancelar').style.display = 'inline-block';
        }

        function limparFormularioAtendente() {
            document.getElementById('at-id').value = '';
            document.getElementById('at-nome').value = '';
            document.getElementById('at-telefone').value = '';
            document.getElementById('at-comissao').value = '3';
            document.getElementById('at-botao').textContent = 'Cadastrar';
            document.getElementById('at-cancelar').style.display = 'none';
        }

        async function salvarAtendente() {
            const id = document.getElementById('at-id').value;
            const dados = {
                nome: document.getElementById('at-nome').value,
                telefone: document.getElementById('at-telefone').value || null,
                percentual_comissao: document.getElementById('at-comissao').value ? Number(document.getElementById('at-comissao').value) : 3,
            };
            const url = id ? `${base}/atendentes/${id}` : `${base}/atendentes`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-atendentes');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Atendente atualizado.' : 'Atendente cadastrado.';
            limparFormularioAtendente();
            carregarAtendentes();
        }

        async function carregarRelatorioAtendentes() {
            const params = new URLSearchParams();
            const inicio = document.getElementById('atr-inicio').value;
            const fim = document.getElementById('atr-fim').value;
            if (inicio) params.set('data_inicio', inicio);
            if (fim) params.set('data_fim', fim);
            params.set('tipo', document.getElementById('atr-tipo').value);

            const resp = await fetch(`${base}/atendentes-relatorio?${params}`);
            const lista = await resp.json();
            document.getElementById('tbody-relatorio-atendentes').innerHTML = lista.map(r => `
                <tr><td>${r.nome}</td><td>${r.vendas_count}</td><td>${r.itens_count}</td><td>R$ ${Number(r.valor_total).toFixed(2)}</td></tr>
            `).join('') || '<tr><td colspan="4">Nenhum atendente cadastrado.</td></tr>';
        }

        async function carregarRelatorioVendedores() {
            const params = new URLSearchParams();
            const inicio = document.getElementById('ver-inicio').value;
            const fim = document.getElementById('ver-fim').value;
            if (inicio) params.set('data_inicio', inicio);
            if (fim) params.set('data_fim', fim);
            params.set('tipo', document.getElementById('ver-tipo').value);

            const resp = await fetch(`${base}/vendedores-relatorio?${params}`);
            const lista = await resp.json();
            document.getElementById('tbody-relatorio-vendedores').innerHTML = lista.map(r => `
                <tr><td>${esc(r.nome)}</td><td>${esc(r.chave_pix || '-')}</td><td>${r.vendas_count}</td><td>${r.itens_count}</td><td>R$ ${Number(r.valor_total).toFixed(2)}</td></tr>
            `).join('') || '<tr><td colspan="5">Nenhum vendedor cadastrado.</td></tr>';
        }

        let descontosPdvCache = [];

        async function carregarDescontosPdv() {
            const resp = await fetch(`${base}/descontos-pdv`);
            descontosPdvCache = await resp.json();
            document.getElementById('tbody-descontos-pdv').innerHTML = descontosPdvCache.map(d => `
                <tr>
                    <td>${d.descricao}</td>
                    <td>${Number(d.percentual)}%</td>
                    <td>${d.aplica_em === 'visitas' ? 'Visitas (máx. 2 tickets)' : 'Produtos'}</td>
                    <td>${d.ativo ? 'Sim' : 'Não'}</td>
                    <td>
                        <button class="secundario" onclick="editarDescontoPdv(${d.id})">Editar</button>
                        <button class="secundario" onclick="alternarDescontoPdv(${d.id}, ${!d.ativo})">${d.ativo ? 'Desativar' : 'Ativar'}</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum desconto cadastrado.</td></tr>';
        }

        function editarDescontoPdv(id) {
            const d = descontosPdvCache.find(x => x.id === id);
            if (!d) return;
            document.getElementById('dp-id').value = d.id;
            document.getElementById('dp-descricao').value = d.descricao;
            document.getElementById('dp-percentual').value = Number(d.percentual);
            document.getElementById('dp-aplica-em').value = d.aplica_em;
            document.getElementById('dp-botao').textContent = 'Salvar edição';
            document.getElementById('dp-cancelar').style.display = 'inline-block';
        }

        function limparFormularioDescontoPdv() {
            document.getElementById('dp-id').value = '';
            document.getElementById('dp-descricao').value = '';
            document.getElementById('dp-percentual').value = '';
            document.getElementById('dp-aplica-em').value = 'produtos';
            document.getElementById('dp-botao').textContent = 'Cadastrar';
            document.getElementById('dp-cancelar').style.display = 'none';
        }

        async function salvarDescontoPdv() {
            const id = document.getElementById('dp-id').value;
            const dados = {
                descricao: document.getElementById('dp-descricao').value,
                percentual: Number(document.getElementById('dp-percentual').value),
                aplica_em: document.getElementById('dp-aplica-em').value,
            };
            const url = id ? `${base}/descontos-pdv/${id}` : `${base}/descontos-pdv`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-descontos-pdv');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Desconto atualizado.' : 'Desconto cadastrado.';
            limparFormularioDescontoPdv();
            carregarDescontosPdv();
        }

        async function alternarDescontoPdv(id, ativo) {
            await fetch(`${base}/descontos-pdv/${id}`, { method: 'PUT', headers: headersJson, body: JSON.stringify({ ativo }) });
            carregarDescontosPdv();
        }

        let cuponsCache = [];

        function formatarDescontoCupom(c) {
            return c.tipo === 'percentual' ? `${Number(c.valor)}%` : `R$ ${Number(c.valor).toFixed(2)}`;
        }

        async function carregarCupons() {
            const resp = await fetch(`${base}/cupons`);
            cuponsCache = await resp.json();
            document.getElementById('tbody-cupons').innerHTML = cuponsCache.map(c => `
                <tr>
                    <td>${c.codigo}</td>
                    <td>${formatarDescontoCupom(c)}</td>
                    <td>${c.valido_ate ?? '-'}</td>
                    <td>${c.usos_realizados}${c.limite_uso ? ' / ' + c.limite_uso : ''}</td>
                    <td>${c.ativo ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="editarCupom(${c.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhum cupom cadastrado.</td></tr>';
        }

        function editarCupom(id) {
            const c = cuponsCache.find(x => x.id === id);
            if (!c) return;
            document.getElementById('cp-id').value = c.id;
            document.getElementById('cp-codigo').value = c.codigo;
            document.getElementById('cp-tipo').value = c.tipo;
            document.getElementById('cp-valor').value = c.valor;
            document.getElementById('cp-teto').value = c.valor_maximo_desconto ?? '';
            document.getElementById('cp-valido-ate').value = c.valido_ate ?? '';
            document.getElementById('cp-limite').value = c.limite_uso ?? '';
            document.getElementById('cp-ativo').checked = c.ativo;
            document.getElementById('cp-botao').textContent = 'Salvar edição';
            document.getElementById('cp-cancelar').style.display = 'inline-block';
        }

        function limparFormularioCupom() {
            document.getElementById('cp-id').value = '';
            document.getElementById('cp-codigo').value = '';
            document.getElementById('cp-tipo').value = 'percentual';
            document.getElementById('cp-valor').value = '';
            document.getElementById('cp-teto').value = '';
            document.getElementById('cp-valido-ate').value = '';
            document.getElementById('cp-limite').value = '';
            document.getElementById('cp-ativo').checked = true;
            document.getElementById('cp-botao').textContent = 'Cadastrar';
            document.getElementById('cp-cancelar').style.display = 'none';
        }

        async function salvarCupom() {
            const id = document.getElementById('cp-id').value;
            const dados = {
                codigo: document.getElementById('cp-codigo').value,
                tipo: document.getElementById('cp-tipo').value,
                valor: Number(document.getElementById('cp-valor').value),
                valor_maximo_desconto: document.getElementById('cp-teto').value ? Number(document.getElementById('cp-teto').value) : null,
                valido_ate: document.getElementById('cp-valido-ate').value || null,
                limite_uso: document.getElementById('cp-limite').value ? Number(document.getElementById('cp-limite').value) : null,
                ativo: document.getElementById('cp-ativo').checked,
            };
            const url = id ? `${base}/cupons/${id}` : `${base}/cupons`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-cupons');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Cupom atualizado.' : 'Cupom cadastrado.';
            limparFormularioCupom();
            carregarCupons();
        }

        let cuponsLotePagina = 1;

        async function carregarCuponsLote(pagina) {
            cuponsLotePagina = Math.max(1, pagina || 1);
            const params = new URLSearchParams({ pagina: cuponsLotePagina, status: document.getElementById('cpl-status').value });
            const busca = document.getElementById('cpl-busca').value.trim();
            if (busca) params.set('busca', busca);

            const resp = await fetch(`${base}/cupons-lote?${params}`);
            const resposta = await resp.json();

            document.getElementById('tbody-cupons-lote').innerHTML = resposta.dados.map(c => `
                <tr>
                    <td>${c.codigo}</td>
                    <td>${formatarDescontoCupom(c)}</td>
                    <td>${c.usado_em ? 'Usado' : 'Disponível'}</td>
                    <td>${c.usado_por ? c.usado_por.nome : '-'}</td>
                    <td>${c.usado_em ? new Date(c.usado_em).toLocaleString('pt-BR') : '-'}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum cupom encontrado.</td></tr>';

            document.getElementById('cpl-resumo').textContent = ` ${resposta.resumo.usados} de ${resposta.resumo.total} já usados.`;
            document.getElementById('cpl-paginacao').textContent = `Página ${resposta.pagina} de ${resposta.ultima_pagina || 1}`;
            document.getElementById('cpl-anterior').disabled = resposta.pagina <= 1;
            document.getElementById('cpl-proxima').disabled = resposta.pagina >= resposta.ultima_pagina;
        }

        async function carregarContasPagar() {
            const resp = await fetch(`${base}/contas-pagar`);
            const lista = await resp.json();
            document.getElementById('tbody-contas-pagar').innerHTML = lista.map(c => `
                <tr>
                    <td>${c.fornecedor ? c.fornecedor.razao_social : '-'}</td>
                    <td>${c.historico ?? '-'}</td>
                    <td>R$ ${Number(c.valor).toFixed(2)}</td>
                    <td>${c.vencimento}</td>
                    <td>${c.plano_contas ? c.plano_contas.nome : '-'}</td>
                    <td><span class="status status-${c.status}">${c.status}</span></td>
                    <td>${c.status !== 'pago' ? `<button class="secundario" onclick="pagarContaPagar(${c.id})">Marcar pago</button>` : ''}</td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhuma conta a pagar.</td></tr>';
        }

        async function criarContaPagar() {
            const dados = {
                fornecedor_id: document.getElementById('cp-fornecedor').value || null,
                historico: document.getElementById('cp-historico').value || null,
                valor: Number(document.getElementById('cp-valor').value),
                vencimento: document.getElementById('cp-vencimento').value,
                plano_conta_id: document.getElementById('cp-plano-conta').value || null,
            };
            const resp = await fetch(`${base}/contas-pagar`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-contas-pagar');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Conta lançada.';
            document.getElementById('cp-fornecedor').value = '';
            document.getElementById('cp-historico').value = '';
            document.getElementById('cp-valor').value = '';
            document.getElementById('cp-vencimento').value = '';
            carregarContasPagar();
        }

        async function pagarContaPagar(id) {
            const bancoId = document.getElementById('fin-banco-pagamento').value || null;
            await fetch(`${base}/contas-pagar/${id}/pagar`, { method: 'PUT', headers: headersJson, body: JSON.stringify({ banco_id: bancoId }) });
            carregarContasPagar();
        }

        async function carregarContasReceber() {
            const resp = await fetch(`${base}/contas-receber`);
            const lista = await resp.json();
            document.getElementById('tbody-contas-receber').innerHTML = lista.map(c => `
                <tr>
                    <td>${c.cliente ? esc(c.cliente.nome) : '-'}</td>
                    <td>${c.historico ?? '-'}</td>
                    <td>R$ ${Number(c.valor).toFixed(2)}</td>
                    <td>${c.vencimento}</td>
                    <td>${c.plano_contas ? c.plano_contas.nome : '-'}</td>
                    <td><span class="status status-${c.status}">${c.status}</span></td>
                    <td>${c.status !== 'pago' ? `<button class="secundario" onclick="pagarContaReceber(${c.id})">Marcar pago</button>` : ''}</td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhuma conta a receber.</td></tr>';
        }

        async function criarContaReceber() {
            const dados = {
                cliente_id: document.getElementById('cr-cliente').value || null,
                historico: document.getElementById('cr-historico').value || null,
                valor: Number(document.getElementById('cr-valor').value),
                vencimento: document.getElementById('cr-vencimento').value,
                plano_conta_id: document.getElementById('cr-plano-conta').value || null,
            };
            const resp = await fetch(`${base}/contas-receber`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-contas-receber');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Conta lançada.';
            document.getElementById('cr-cliente').value = '';
            document.getElementById('cr-historico').value = '';
            document.getElementById('cr-valor').value = '';
            document.getElementById('cr-vencimento').value = '';
            carregarContasReceber();
        }

        async function pagarContaReceber(id) {
            const bancoId = document.getElementById('fin-banco-pagamento').value || null;
            await fetch(`${base}/contas-receber/${id}/pagar`, { method: 'PUT', headers: headersJson, body: JSON.stringify({ banco_id: bancoId }) });
            carregarContasReceber();
        }

        async function carregarSelectsFinanceiro() {
            const [planos, bancosLista, fornecedoresLista, clientesLista] = await Promise.all([
                fetch(`${base}/plano-contas`).then(r => r.json()),
                fetch(`${base}/bancos`).then(r => r.json()),
                fetch(`${base}/fornecedores`).then(r => r.json()),
                fetch(`${base}/clientes`).then(r => r.json()),
            ]);
            const opcoesPlanos = '<option value="">Sem categoria</option>' + planos.map(p => `<option value="${p.id}">${p.codigo ? p.codigo + ' - ' : ''}${p.nome}</option>`).join('');
            document.getElementById('cp-plano-conta').innerHTML = opcoesPlanos;
            document.getElementById('cr-plano-conta').innerHTML = opcoesPlanos;
            document.getElementById('fin-banco-pagamento').innerHTML = '<option value="">Nenhum (não lança no banco)</option>' +
                bancosLista.map(b => `<option value="${b.id}">${b.nome}</option>`).join('');
            document.getElementById('cp-fornecedor').innerHTML = '<option value="">Nenhum</option>' +
                fornecedoresLista.map(f => `<option value="${f.id}">${f.razao_social}</option>`).join('');
            document.getElementById('cr-cliente').innerHTML = '<option value="">Nenhum</option>' +
                clientesLista.map(c => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
        }

        // ---- Grupos de produto ----

        let gruposCache = [];

        async function carregarGrupos() {
            const [grupos, relatorio] = await Promise.all([
                fetch(`${base}/grupos`).then(r => r.json()),
                fetch(`${base}/grupos-relatorio`).then(r => r.json()),
            ]);
            gruposCache = grupos;
            const porId = Object.fromEntries(relatorio.map(r => [r.id, r]));
            document.getElementById('tbody-grupos').innerHTML = grupos.map(g => `
                <tr>
                    <td>${g.nome}</td>
                    <td>${g.descricao ?? '-'}</td>
                    <td>${porId[g.id]?.produtos_count ?? 0}</td>
                    <td>R$ ${Number(porId[g.id]?.valor_estoque ?? 0).toFixed(2)}</td>
                </tr>
            `).join('') || '<tr><td colspan="4">Nenhum grupo cadastrado.</td></tr>';

            const opcoes = '<option value="">Sem grupo</option>' + grupos.map(g => `<option value="${g.id}">${g.nome}</option>`).join('');
            const selectGrupoProduto = document.getElementById('pr-grupo');
            if (selectGrupoProduto) selectGrupoProduto.innerHTML = opcoes;
        }

        async function criarGrupo() {
            const dados = {
                nome: document.getElementById('gr-nome').value,
                descricao: document.getElementById('gr-descricao').value || null,
            };
            const resp = await fetch(`${base}/grupos`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-grupos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Grupo cadastrado.';
            document.getElementById('gr-nome').value = '';
            document.getElementById('gr-descricao').value = '';
            carregarGrupos();
        }

        // ---- Plano de contas ----

        async function carregarPlanoContas() {
            const resp = await fetch(`${base}/plano-contas`);
            const lista = await resp.json();
            document.getElementById('tbody-plano-contas').innerHTML = lista.map(p => `
                <tr><td>${p.codigo ?? '-'}</td><td>${p.nome}</td><td>${p.tipo}</td></tr>
            `).join('') || '<tr><td colspan="3">Nenhuma categoria cadastrada.</td></tr>';
        }

        async function criarPlanoContas() {
            const dados = {
                codigo: document.getElementById('pc-codigo').value || null,
                nome: document.getElementById('pc-nome').value,
                tipo: document.getElementById('pc-tipo').value,
            };
            const resp = await fetch(`${base}/plano-contas`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-plano-contas');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Categoria cadastrada.';
            document.getElementById('pc-codigo').value = '';
            document.getElementById('pc-nome').value = '';
            carregarPlanoContas();
            carregarSelectsFinanceiro();
        }

        async function carregarRelatorioPlanoContas() {
            const params = new URLSearchParams();
            const inicio = document.getElementById('pcr-inicio').value;
            const fim = document.getElementById('pcr-fim').value;
            if (inicio) params.set('data_inicio', inicio);
            if (fim) params.set('data_fim', fim);

            const resp = await fetch(`${base}/plano-contas-relatorio?${params}`);
            const lista = await resp.json();
            document.getElementById('tbody-relatorio-plano-contas').innerHTML = lista.map(r => `
                <tr>
                    <td>${r.codigo ? r.codigo + ' - ' : ''}${r.nome}</td>
                    <td>${r.tipo}</td>
                    <td>R$ ${Number(r.total).toFixed(2)}</td>
                    <td>R$ ${Number(r.total_pago).toFixed(2)}</td>
                    <td>R$ ${Number(r.total_em_aberto).toFixed(2)}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhuma categoria cadastrada.</td></tr>';
        }

        // ---- Bancos ----

        async function carregarBancos() {
            const resp = await fetch(`${base}/bancos`);
            const lista = await resp.json();
            document.getElementById('tbody-bancos').innerHTML = lista.map(b => `
                <tr>
                    <td>${b.nome}</td>
                    <td>${b.agencia ?? '-'} / ${b.numero_conta ?? '-'}</td>
                    <td>${b.tipo_conta}</td>
                    <td><button class="secundario" onclick="selecionarBancoExtrato(${b.id})">Ver extrato</button></td>
                </tr>
            `).join('') || '<tr><td colspan="4">Nenhum banco cadastrado.</td></tr>';

            document.getElementById('ex-banco').innerHTML = lista.map(b => `<option value="${b.id}">${b.nome}</option>`).join('');
            carregarSelectsFinanceiro();
        }

        async function criarBanco() {
            const dados = {
                nome: document.getElementById('bc-nome').value,
                agencia: document.getElementById('bc-agencia').value || null,
                numero_conta: document.getElementById('bc-conta').value || null,
                tipo_conta: document.getElementById('bc-tipo').value,
                saldo_inicial: Number(document.getElementById('bc-saldo').value || 0),
            };
            const resp = await fetch(`${base}/bancos`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-bancos');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Banco cadastrado.';
            document.getElementById('bc-nome').value = '';
            document.getElementById('bc-agencia').value = '';
            document.getElementById('bc-conta').value = '';
            document.getElementById('bc-saldo').value = '';
            carregarBancos();
        }

        function selecionarBancoExtrato(bancoId) {
            document.getElementById('ex-banco').value = bancoId;
            carregarExtratoBanco();
        }

        async function carregarExtratoBanco() {
            const bancoId = document.getElementById('ex-banco').value;
            if (!bancoId) return;

            const params = new URLSearchParams();
            const inicio = document.getElementById('ex-inicio').value;
            const fim = document.getElementById('ex-fim').value;
            if (inicio) params.set('data_inicio', inicio);
            if (fim) params.set('data_fim', fim);

            const resp = await fetch(`${base}/bancos/${bancoId}/extrato?${params}`);
            const dados = await resp.json();

            document.getElementById('ex-saldo').textContent =
                `Saldo anterior: R$ ${Number(dados.saldo_anterior).toFixed(2)} — Saldo atual: R$ ${Number(dados.saldo_atual).toFixed(2)}`;

            document.getElementById('tbody-extrato-banco').innerHTML = dados.movimentos.map(m => `
                <tr>
                    <td>${m.data_movimento}</td>
                    <td>${m.tipo}</td>
                    <td>R$ ${Number(m.valor).toFixed(2)}</td>
                    <td>${m.descricao ?? '-'}</td>
                    <td>${m.origem}</td>
                    <td>R$ ${Number(m.saldo_apos).toFixed(2)}</td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhum movimento no período.</td></tr>';
        }

        async function lancarMovimentoBancario() {
            const bancoId = document.getElementById('ex-banco').value;
            const msg = document.getElementById('msg-extrato-banco');
            if (!bancoId) { msg.className = 'msg erro'; msg.textContent = 'Selecione um banco.'; return; }

            const dados = {
                data_movimento: document.getElementById('mv-data').value,
                tipo: document.getElementById('mv-tipo').value,
                valor: Number(document.getElementById('mv-valor').value),
                descricao: document.getElementById('mv-descricao').value || null,
            };
            const resp = await fetch(`${base}/bancos/${bancoId}/movimentos`, { method: 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Movimento lançado.';
            document.getElementById('mv-valor').value = '';
            document.getElementById('mv-descricao').value = '';
            carregarExtratoBanco();
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

        async function buscarCepEmitente() {
            try {
                const d = await consultarCepEmViaCep(document.getElementById('cf-cep').value);
                if (!d) return;
                document.getElementById('cf-logradouro').value = d.logradouro || document.getElementById('cf-logradouro').value;
                document.getElementById('cf-bairro').value = d.bairro || document.getElementById('cf-bairro').value;
                document.getElementById('cf-municipio').value = d.localidade || document.getElementById('cf-municipio').value;
                document.getElementById('cf-uf').value = d.uf || document.getElementById('cf-uf').value;
                document.getElementById('cf-ibge').value = d.ibge || document.getElementById('cf-ibge').value;
            } catch (e) {
                document.getElementById('msg-config-fiscal').className = 'msg erro';
                document.getElementById('msg-config-fiscal').textContent = e.message;
            }
        }

        async function carregarConfigLoja() {
            const resp = await fetch(`${base}/config-loja`);
            const dados = await resp.json();
            document.getElementById('lj-segmento').value = dados.segmento ?? '';
            document.getElementById('lj-logo').value = dados.logo_url ?? '';
            document.getElementById('lj-logo-arquivo').value = '';
            document.getElementById('lj-logo-usar-url').checked = false;
            document.getElementById('lj-cor').value = dados.cor_primaria ?? '#394285';
            alternarOrigemLogo();
            atualizarPreviewLogo();
        }

        function alternarOrigemLogo() {
            const porUrl = document.getElementById('lj-logo-usar-url').checked;
            document.getElementById('lj-logo').style.display = porUrl ? '' : 'none';
            document.getElementById('lj-logo-arquivo').style.display = porUrl ? 'none' : '';
        }

        function atualizarPreviewLogo() {
            const preview = document.getElementById('lj-logo-preview');
            const arquivo = document.getElementById('lj-logo-arquivo').files[0];
            if (arquivo) {
                preview.src = URL.createObjectURL(arquivo);
                preview.style.display = '';
                return;
            }
            const url = document.getElementById('lj-logo').value;
            if (url) {
                preview.src = url;
                preview.style.display = '';
            } else {
                preview.style.display = 'none';
                preview.src = '';
            }
        }

        document.getElementById('lj-logo-usar-url').addEventListener('change', () => { alternarOrigemLogo(); atualizarPreviewLogo(); });
        document.getElementById('lj-logo').addEventListener('input', atualizarPreviewLogo);
        document.getElementById('lj-logo-arquivo').addEventListener('change', atualizarPreviewLogo);

        async function salvarConfigLoja() {
            const dados = {
                segmento: document.getElementById('lj-segmento').value || null,
                logo_url: document.getElementById('lj-logo').value || null,
                cor_primaria: document.getElementById('lj-cor').value || null,
            };
            const arquivoLogo = !document.getElementById('lj-logo-usar-url').checked
                ? document.getElementById('lj-logo-arquivo').files[0]
                : null;

            let resp;
            if (arquivoLogo) {
                delete dados.logo_url;
                const formData = new FormData();
                Object.entries(dados).forEach(([chave, valor]) => {
                    if (valor === null || valor === undefined) return;
                    formData.append(chave, valor);
                });
                formData.append('logo', arquivoLogo);
                formData.append('_method', 'PUT');
                resp = await fetch(`${base}/config-loja`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });
            } else {
                resp = await fetch(`${base}/config-loja`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            }
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-loja');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Identidade visual salva.';
            carregarConfigLoja();
        }

        // ---- Emitir NF-e (venda avulsa, remessa, transferência, bonificação e devoluções) ----

        const nfeBase = `{{ url('/fiscal') }}/${empresa}`;
        let nfeOpcoes = null;
        let nfeProdutos = [];
        let nfeAbaAtual = 'venda';

        const nfeDescricoes = {
            venda: 'Venda de mercadoria para um cliente cadastrado, com frete, transportadora e forma de pagamento. Baixa o estoque.',
            remessa: 'Remessa de mercadoria sem venda (ex.: para conserto ou demonstração). CFOP padrão 5949; informe outro (ex.: 5915) se precisar. Não baixa estoque por padrão.',
            transferencia: 'Transferência de mercadoria para outro estabelecimento (cadastre-o como cliente). CFOP padrão 5152. Não baixa estoque por padrão.',
            bonificacao: 'Bonificação, doação ou brinde. CFOP padrão 5910. Baixa o estoque por padrão.',
        };

        async function nfeObterOpcoes() {
            const resp = await fetch(`${nfeBase}/nfe/opcoes`, { headers: { 'Accept': 'application/json' } });
            nfeOpcoes = await resp.json();
            return nfeOpcoes;
        }

        function nfeMoeda(valor) {
            return `R$ ${Number(valor || 0).toFixed(2).replace('.', ',')}`;
        }

        async function carregarNfe() {
            const op = await nfeObterOpcoes();
            const banner = document.getElementById('nfe-ambiente');

            if (!op.configurado) {
                banner.innerHTML = '<strong style="color:#c81e1e;">Configuração fiscal não cadastrada.</strong> Preencha em Config. Fiscal antes de emitir.';
            } else if (op.ambiente === 'producao') {
                banner.innerHTML = '<strong style="color:#c81e1e;">AMBIENTE DE PRODUÇÃO</strong> - as notas emitidas aqui têm valor fiscal.';
            } else {
                banner.innerHTML = '<strong style="color:#b7791f;">AMBIENTE DE HOMOLOGAÇÃO</strong> - notas de teste, sem valor fiscal.';
            }
            if (op.configurado && !op.regime_suportado) {
                banner.innerHTML += '<br><strong style="color:#c81e1e;">Atenção:</strong> o regime tributário configurado não é suportado por esta tela (Simples Nacional ou Regime Normal).';
            }

            const [clientes, produtos, formas] = await Promise.all([
                fetch(`${base}/clientes`).then(r => r.json()),
                fetch(`${base}/produtos`).then(r => r.json()),
                fetch(`${base}/formas-pagamento`).then(r => r.json()),
            ]);

            nfeProdutos = produtos.filter(p => p.ativo && !p.eh_kit);
            document.getElementById('nfe-cliente').innerHTML = '<option value="">Selecione...</option>' +
                clientes.map(c => `<option value="${c.id}">${esc(c.nome)} - ${esc(c.cpf_cnpj ?? 'sem documento')}</option>`).join('');
            document.getElementById('nfe-forma-pagamento').innerHTML = '<option value="">Não informada</option>' +
                formas.filter(f => f.ativo !== false).map(f => `<option value="${f.id}">${esc(f.descricao)}</option>`).join('');
            document.getElementById('nfe-modalidade').innerHTML = op.modalidades_frete
                .map(m => `<option value="${m.valor}" ${m.valor === 9 ? 'selected' : ''}>${esc(m.rotulo)}</option>`).join('');

            document.getElementById('tbody-nfe-itens').innerHTML = '';
            nfeAdicionarItem();
            nfeAplicarTipo(nfeAbaAtual in nfeDescricoes ? nfeAbaAtual : 'venda');
        }

        function nfeAplicarTipo(tipo) {
            const t = nfeOpcoes.tipos.find(x => x.valor === tipo);
            document.getElementById('nfe-descricao').textContent = nfeDescricoes[tipo];
            document.getElementById('nfe-natureza').value = t.natureza;
            document.getElementById('nfe-cfop').value = '';
            document.getElementById('nfe-cfop').placeholder = t.cfop;
            document.getElementById('nfe-baixar-estoque').checked = !!t.baixa_estoque;
            document.getElementById('nfe-forma-pagamento-bloco').style.display = t.sem_pagamento ? 'none' : '';
            document.getElementById('msg-nfe').textContent = '';
        }

        function nfeMostrarAba(aba, botao) {
            nfeAbaAtual = aba;
            document.querySelectorAll('#nfe-abas button').forEach(b => b.classList.remove('ativa'));
            botao.classList.add('ativa');

            const emitir = aba in nfeDescricoes;
            document.getElementById('nfe-painel-emitir').style.display = emitir ? '' : 'none';
            document.getElementById('nfe-painel-dev-venda').style.display = aba === 'dev-venda' ? '' : 'none';
            document.getElementById('nfe-painel-dev-fornecedor').style.display = aba === 'dev-fornecedor' ? '' : 'none';

            if (emitir && nfeOpcoes) nfeAplicarTipo(aba);
            if (aba === 'dev-venda') dvCarregar();
            if (aba === 'dev-fornecedor') dvfCarregar();
        }

        function nfeLinhaItem() {
            const opcoes = '<option value="">Selecione...</option>' +
                nfeProdutos.map(p => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
            return `
                <tr>
                    <td><select class="nfe-item-produto" onchange="nfeProdutoMudou(this)" style="min-width:240px">${opcoes}</select></td>
                    <td><select class="nfe-item-variacao" style="display:none; min-width:130px" onchange="nfeAtualizarTotais()"></select></td>
                    <td><input type="number" class="nfe-item-qtd" step="0.001" min="0.001" value="1" style="width:90px" oninput="nfeAtualizarTotais()"></td>
                    <td><input type="number" class="nfe-item-valor" step="0.01" min="0" style="width:100px" oninput="nfeAtualizarTotais()"></td>
                    <td class="nfe-item-total">R$ 0,00</td>
                    <td><button type="button" class="secundario" onclick="this.closest('tr').remove(); nfeAtualizarTotais()">Remover</button></td>
                </tr>`;
        }

        function nfeAdicionarItem() {
            document.getElementById('tbody-nfe-itens').insertAdjacentHTML('beforeend', nfeLinhaItem());
        }

        function nfeProdutoMudou(select) {
            const tr = select.closest('tr');
            const produto = nfeProdutos.find(p => p.id === Number(select.value));
            const variacoes = (produto?.variacoes || []).filter(v => v.ativo);
            const selVar = tr.querySelector('.nfe-item-variacao');

            selVar.style.display = variacoes.length ? '' : 'none';
            selVar.innerHTML = variacoes.length
                ? '<option value="">Escolha...</option>' + variacoes.map(v => `<option value="${v.id}">${esc(v.tamanho)} (estoque ${v.produto_vinculado ? (v.produto_vinculado.estoque_atual ?? 'ilimitado') : v.estoque_atual})</option>`).join('')
                : '';
            tr.querySelector('.nfe-item-valor').value = produto ? Number(produto.preco_venda).toFixed(2) : '';
            nfeAtualizarTotais();
        }

        function nfeAtualizarTotais() {
            let produtos = 0;
            document.querySelectorAll('#tbody-nfe-itens tr').forEach(tr => {
                const total = Number(tr.querySelector('.nfe-item-qtd').value || 0) * Number(tr.querySelector('.nfe-item-valor').value || 0);
                tr.querySelector('.nfe-item-total').textContent = nfeMoeda(total);
                produtos += Math.round(total * 100) / 100;
            });
            const frete = Number(document.getElementById('nfe-frete').value || 0);
            document.getElementById('nfe-total').textContent = nfeMoeda(produtos + frete);
        }

        function nfeResultado(msg, documento) {
            const link = `${nfeBase}/documentos/${documento.id}/reimprimir`;
            if (documento.status === 'autorizada') {
                msg.className = 'msg ok';
                msg.innerHTML = `NF-e nº ${esc(documento.numero)} autorizada${documento.ambiente === 'homologacao' ? ' (homologação)' : ''}. <a href="${link}" target="_blank">Ver NF-e</a>`;
            } else {
                msg.className = 'msg erro';
                msg.innerHTML = `NF-e nº ${esc(documento.numero)} ${esc(documento.status)}: ${esc(documento.motivo_cancelamento ?? 'sem detalhe')}. <a href="${link}" target="_blank">Ver</a>`;
            }
        }

        async function nfeConfirmarProducao() {
            const op = nfeOpcoes ?? await nfeObterOpcoes();
            return op.ambiente !== 'producao' || confirm('Você está emitindo em PRODUÇÃO. Esta nota terá valor fiscal. Confirmar a emissão?');
        }

        async function nfeEmitir() {
            const msg = document.getElementById('msg-nfe');
            const itens = Array.from(document.querySelectorAll('#tbody-nfe-itens tr')).map(tr => ({
                produto_id: Number(tr.querySelector('.nfe-item-produto').value),
                variacao_id: tr.querySelector('.nfe-item-variacao').value ? Number(tr.querySelector('.nfe-item-variacao').value) : null,
                quantidade: Number(tr.querySelector('.nfe-item-qtd').value),
                valor_unitario: tr.querySelector('.nfe-item-valor').value === '' ? null : Number(tr.querySelector('.nfe-item-valor').value),
            })).filter(i => i.produto_id);

            if (!document.getElementById('nfe-cliente').value) { msg.className = 'msg erro'; msg.textContent = 'Selecione o destinatário.'; return; }
            if (!itens.length) { msg.className = 'msg erro'; msg.textContent = 'Adicione ao menos um item.'; return; }

            const frete = Number(document.getElementById('nfe-frete').value || 0);
            const transportadora = {
                nome: document.getElementById('nfe-transp-nome').value.trim(),
                documento: document.getElementById('nfe-transp-doc').value.trim(),
                ie: document.getElementById('nfe-transp-ie').value.trim(),
                endereco: document.getElementById('nfe-transp-endereco').value.trim(),
                municipio: document.getElementById('nfe-transp-municipio').value.trim(),
                uf: document.getElementById('nfe-transp-uf').value.trim().toUpperCase(),
            };

            if (!(await nfeConfirmarProducao())) return;

            const botao = document.getElementById('nfe-botao-emitir');
            botao.disabled = true;
            msg.className = 'msg'; msg.textContent = 'Emitindo e aguardando a SEFAZ...';

            try {
                const resp = await fetch(`${nfeBase}/nfe`, {
                    method: 'POST',
                    headers: headersJson,
                    body: JSON.stringify({
                        tipo: nfeAbaAtual,
                        cliente_id: Number(document.getElementById('nfe-cliente').value),
                        natureza_operacao: document.getElementById('nfe-natureza').value || null,
                        cfop: document.getElementById('nfe-cfop').value.trim() || null,
                        itens,
                        frete: frete > 0 ? frete : null,
                        modalidade_frete: Number(document.getElementById('nfe-modalidade').value),
                        transportadora: transportadora.nome ? transportadora : null,
                        informacoes_adicionais: document.getElementById('nfe-informacoes').value || null,
                        baixar_estoque: document.getElementById('nfe-baixar-estoque').checked,
                        forma_pagamento_id: document.getElementById('nfe-forma-pagamento').value ? Number(document.getElementById('nfe-forma-pagamento').value) : null,
                    }),
                });
                const dados = await resp.json();
                if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message || JSON.stringify(dados.errors); return; }
                nfeResultado(msg, dados);
                if (dados.status === 'autorizada') {
                    document.getElementById('tbody-nfe-itens').innerHTML = '';
                    nfeAdicionarItem();
                    document.getElementById('nfe-frete').value = '';
                    nfeAtualizarTotais();
                }
            } finally {
                botao.disabled = false;
            }
        }

        // NF-e a partir de um pedido pago da loja virtual (botão na tela Pedidos da Loja).
        async function nfeEmitirPedidoLoja(vendaId) {
            if (!(await nfeConfirmarProducao())) return;
            const msg = document.getElementById('msg-pedidos-loja');
            msg.className = 'msg'; msg.textContent = `Emitindo a NF-e do pedido #${vendaId}...`;

            const resp = await fetch(`${nfeBase}/vendas/${vendaId}/nfe-pedido-loja`, { method: 'POST', headers: headersJson });
            const dados = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message || JSON.stringify(dados.errors); return; }
            nfeResultado(msg, dados);
            await carregarPedidosLoja();
            alternarDetalhePedidoLoja(vendaId);
        }

        // ---- Devolução de venda ----
        let dvDocumentoId = null;

        async function dvCarregar() {
            const resp = await fetch(`${nfeBase}/documentos-elegiveis-devolucao`);
            const lista = await resp.json();
            document.getElementById('tbody-dv-documentos').innerHTML = lista.map(d => `
                <tr>
                    <td>#${esc(d.numero)} (${d.modelo === 55 ? 'NFe' : 'NFC-e'})</td>
                    <td>${esc(d.cliente || 'Não identificado')}</td>
                    <td>R$ ${Number(d.total).toFixed(2)}</td>
                    <td>${new Date(d.created_at).toLocaleString('pt-BR')}</td>
                    <td><button class="secundario" onclick="dvAbrir(${d.id}, ${Number(d.numero)})">Devolver</button></td>
                </tr>`).join('') || '<tr><td colspan="5">Nenhum documento disponível para devolução.</td></tr>';
        }

        async function dvAbrir(documentoId, numero) {
            dvDocumentoId = documentoId;
            document.getElementById('dv-documento-numero').textContent = numero;
            document.getElementById('dv-itens-bloco').style.display = 'block';
            document.getElementById('msg-dv').textContent = '';

            const resp = await fetch(`${nfeBase}/documentos/${documentoId}/itens-disponiveis-devolucao`);
            const itens = await resp.json();
            document.getElementById('tbody-dv-itens').innerHTML = itens.map(i => `
                <tr>
                    <td><input type="checkbox" class="dv-check" data-id="${i.item_venda_id}"></td>
                    <td>${esc(i.produto || '-')}</td>
                    <td>${i.quantidade_vendida}</td>
                    <td>${i.quantidade_ja_devolvida}</td>
                    <td>${i.quantidade_disponivel}</td>
                    <td><input type="number" step="0.001" min="0" max="${i.quantidade_disponivel}" class="dv-qtd" data-id="${i.item_venda_id}" style="width:90px" ${i.quantidade_disponivel <= 0 ? 'disabled' : ''}></td>
                </tr>`).join('') || '<tr><td colspan="6">Nenhum item disponível.</td></tr>';
        }

        function dvFechar() {
            dvDocumentoId = null;
            document.getElementById('dv-itens-bloco').style.display = 'none';
        }

        async function dvConfirmar() {
            const msg = document.getElementById('msg-dv');
            const itens = Array.from(document.querySelectorAll('.dv-check')).filter(c => c.checked).map(c => ({
                item_venda_id: Number(c.dataset.id),
                quantidade: Number(document.querySelector(`.dv-qtd[data-id="${c.dataset.id}"]`).value),
            }));
            if (!itens.length) { msg.className = 'msg erro'; msg.textContent = 'Selecione ao menos um item.'; return; }
            if (!(await nfeConfirmarProducao())) return;

            const resp = await fetch(`${nfeBase}/documentos/${dvDocumentoId}/devolucao`, { method: 'POST', headers: headersJson, body: JSON.stringify({ itens }) });
            const dados = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message || JSON.stringify(dados.errors); return; }
            nfeResultado(msg, dados);
            dvFechar();
            dvCarregar();
        }

        // ---- Devolução a fornecedor ----
        let dvfCompraId = null;

        async function dvfCarregar() {
            const resp = await fetch(`${nfeBase}/compras-elegiveis-devolucao`);
            const lista = await resp.json();
            document.getElementById('tbody-dvf-compras').innerHTML = lista.map(c => `
                <tr>
                    <td>#${c.id}${c.numero_nota ? ' (NF ' + esc(c.numero_nota) + ')' : ''}</td>
                    <td>${esc(c.fornecedor || 'Não identificado')}</td>
                    <td>R$ ${Number(c.total).toFixed(2)}</td>
                    <td>${new Date(c.data_entrada).toLocaleDateString('pt-BR')}</td>
                    <td><button class="secundario" onclick="dvfAbrir(${c.id})">Devolver</button></td>
                </tr>`).join('') || '<tr><td colspan="5">Nenhuma compra confirmada disponível para devolução.</td></tr>';
        }

        async function dvfAbrir(compraId) {
            dvfCompraId = compraId;
            document.getElementById('dvf-compra-numero').textContent = compraId;
            document.getElementById('dvf-itens-bloco').style.display = 'block';
            document.getElementById('msg-dvf').textContent = '';

            const resp = await fetch(`${nfeBase}/compras/${compraId}/itens-disponiveis-devolucao`);
            const itens = await resp.json();
            document.getElementById('tbody-dvf-itens').innerHTML = itens.map(i => `
                <tr>
                    <td><input type="checkbox" class="dvf-check" data-id="${i.item_compra_id}"></td>
                    <td>${esc(i.produto || '-')}</td>
                    <td>${i.quantidade_comprada}</td>
                    <td>${i.quantidade_ja_devolvida}</td>
                    <td>${i.quantidade_disponivel}</td>
                    <td><input type="number" step="0.001" min="0" max="${i.quantidade_disponivel}" class="dvf-qtd" data-id="${i.item_compra_id}" style="width:90px" ${i.quantidade_disponivel <= 0 ? 'disabled' : ''}></td>
                </tr>`).join('') || '<tr><td colspan="6">Nenhum item disponível.</td></tr>';
        }

        function dvfFechar() {
            dvfCompraId = null;
            document.getElementById('dvf-itens-bloco').style.display = 'none';
        }

        async function dvfConfirmar() {
            const msg = document.getElementById('msg-dvf');
            const itens = Array.from(document.querySelectorAll('.dvf-check')).filter(c => c.checked).map(c => ({
                item_compra_id: Number(c.dataset.id),
                quantidade: Number(document.querySelector(`.dvf-qtd[data-id="${c.dataset.id}"]`).value),
            }));
            if (!itens.length) { msg.className = 'msg erro'; msg.textContent = 'Selecione ao menos um item.'; return; }
            if (!(await nfeConfirmarProducao())) return;

            const resp = await fetch(`${nfeBase}/compras/${dvfCompraId}/devolucao`, { method: 'POST', headers: headersJson, body: JSON.stringify({ itens }) });
            const dados = await resp.json();
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message || JSON.stringify(dados.errors); return; }
            nfeResultado(msg, dados);
            dvfFechar();
            dvfCarregar();
        }

        // ---- Pedidos da loja virtual ----

        // Dados vindos da loja pública são digitados por qualquer pessoa -
        // escapa antes de colocar em innerHTML.
        function esc(valor) {
            return String(valor ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        let pedidosLojaCache = [];

        function rotuloEnvio(pedido) {
            const retirada = pedido.tipo_entrega === 'retirada';
            return {
                a_separar: 'A separar',
                enviado: retirada ? 'Pronto p/ retirada' : 'Enviado',
                entregue: retirada ? 'Retirado' : 'Entregue',
            }[pedido.status_envio] ?? '-';
        }

        async function carregarPedidosLoja() {
            const params = new URLSearchParams();
            const envio = document.getElementById('pl-filtro-envio').value;
            const pagamento = document.getElementById('pl-filtro-pagamento').value;
            if (envio) params.set('status_envio', envio);
            if (pagamento) params.set('status_pagamento', pagamento);

            const resp = await fetch(`${base}/pedidos-loja?${params}`, { headers: { 'Accept': 'application/json' } });
            pedidosLojaCache = await resp.json();

            document.getElementById('tbody-pedidos-loja').innerHTML = pedidosLojaCache.map(p => `
                <tr>
                    <td>#${p.id}</td>
                    <td>${new Date(p.data_venda).toLocaleString('pt-BR')}</td>
                    <td>${esc(p.cliente?.nome ?? '-')}</td>
                    <td>${p.tipo_entrega === 'retirada' ? 'Retirada na loja' : p.tipo_entrega === 'entrega' ? `Entrega (frete R$ ${Number(p.valor_frete).toFixed(2)})` : '-'}</td>
                    <td>R$ ${Number(p.valor_total).toFixed(2)}</td>
                    <td>${esc(p.status_pagamento)}</td>
                    <td>${esc(rotuloEnvio(p))}</td>
                    <td><button class="secundario" onclick="alternarDetalhePedidoLoja(${p.id})">Detalhes</button></td>
                </tr>
                <tr id="pl-detalhe-${p.id}" style="display:none;"><td colspan="8">${detalhePedidoLoja(p)}</td></tr>
            `).join('') || '<tr><td colspan="8">Nenhum pedido encontrado.</td></tr>';
        }

        function detalhePedidoLoja(p) {
            const e = p.endereco_entrega;
            const retirada = p.tipo_entrega === 'retirada';
            const itens = p.itens.map(i => `<li>${i.quantidade}x ${esc(i.nome)}${i.tamanho ? ` (${esc(i.tamanho)})` : ''} - R$ ${Number(i.valor_total).toFixed(2)}${i.composicao ? `<ul style="margin:2px 0; padding-left:16px; color:var(--cor-texto-suave);">${i.composicao.map(c => `<li>${c.quantidade}x ${esc(c.nome)}${c.tamanho ? ` - ${esc(c.tamanho)}` : ''}</li>`).join('')}</ul>` : ''}</li>`).join('');
            const endereco = retirada
                ? '<em>Cliente retira na loja.</em>'
                : e ? `${esc(e.logradouro)}, ${esc(e.numero)} - ${esc(e.bairro)}<br>${esc(e.municipio)}/${esc(e.uf)} - CEP ${esc(e.cep)}`
                    : '<em>Endereço não informado.</em>';
            const opcoesEnvio = [
                ['a_separar', 'A separar'],
                ['enviado', retirada ? 'Pronto para retirada' : 'Enviado'],
                ['entregue', retirada ? 'Retirado' : 'Entregue'],
            ].map(([v, r]) => `<option value="${v}" ${p.status_envio === v ? 'selected' : ''}>${r}</option>`).join('');

            return `
                <div style="display:flex; gap:32px; flex-wrap:wrap; font-size:13px;">
                    <div><strong>Cliente</strong><br>${esc(p.cliente?.nome)}<br>${esc(p.cliente?.telefone)}<br>${esc(p.cliente?.email)}<br>${esc(p.cliente?.cpf_cnpj)}</div>
                    <div><strong>${retirada ? 'Retirada' : 'Endereço de entrega'}</strong><br>${endereco}</div>
                    <div><strong>Itens</strong><ul style="margin:4px 0; padding-left:18px;">${itens}</ul></div>
                </div>
                <div style="margin-top:10px; font-size:13px;">
                    ${p.nfe && p.nfe.status === 'autorizada'
                        ? `<strong>NF-e nº ${esc(p.nfe.numero)}</strong> autorizada${p.nfe.ambiente === 'homologacao' ? ' (homologação)' : ''} - <a href="${nfeBase}/documentos/${p.nfe.id}/reimprimir" target="_blank">Ver NF-e</a>`
                        : p.status_pagamento === 'pago'
                            ? `<button type="button" class="secundario" onclick="nfeEmitirPedidoLoja(${p.id})">Emitir NF-e deste pedido</button>${p.nfe ? ` <span style="color:#c81e1e;">(última tentativa: ${esc(p.nfe.status)})</span>` : ''}`
                            : '<em>A NF-e só pode ser emitida depois que o pedido for pago.</em>'}
                </div>
                ${p.tipo_entrega ? `
                <div class="linha-form" style="margin-top:10px;">
                    <div><label>Situação do envio</label><select id="pl-envio-${p.id}">${opcoesEnvio}</select></div>
                    ${retirada ? '' : `<div><label>Código de rastreio</label><input type="text" id="pl-rastreio-${p.id}" maxlength="60" value="${esc(p.codigo_rastreio ?? '')}"></div>`}
                    <div><button class="acao" onclick="salvarEnvioPedidoLoja(${p.id})">Salvar</button></div>
                </div>` : '<p style="font-size:12px; color:var(--cor-texto-suave);">Pedido anterior ao controle de entrega - sem situação de envio.</p>'}
            `;
        }

        function alternarDetalhePedidoLoja(id) {
            const linha = document.getElementById(`pl-detalhe-${id}`);
            linha.style.display = linha.style.display === 'none' ? '' : 'none';
        }

        async function salvarEnvioPedidoLoja(id) {
            const rastreio = document.getElementById(`pl-rastreio-${id}`);
            const resp = await fetch(`${base}/pedidos-loja/${id}/envio`, {
                method: 'PUT',
                headers: headersJson,
                body: JSON.stringify({
                    status_envio: document.getElementById(`pl-envio-${id}`).value,
                    codigo_rastreio: rastreio ? (rastreio.value || null) : null,
                }),
            });
            const msg = document.getElementById('msg-pedidos-loja');
            if (!resp.ok) {
                const erro = await resp.json();
                msg.className = 'msg erro'; msg.textContent = erro.message ?? 'Erro ao salvar o envio.';
                return;
            }
            msg.className = 'msg ok'; msg.textContent = `Pedido #${id} atualizado.`;
            await carregarPedidosLoja();
            alternarDetalhePedidoLoja(id);
        }

        // ---- Frete da loja pública ----

        function linhaRegraFrete(regra) {
            const demais = regra.uf === null || regra.uf === undefined || regra.uf === '';
            return `
                <tr>
                    <td>
                        <input type="text" class="fr-uf" maxlength="2" style="width:60px; text-transform:uppercase" value="${demais ? '' : regra.uf}" placeholder="${demais ? 'demais' : 'UF'}">
                    </td>
                    <td><input type="number" step="0.01" min="0" class="fr-valor" style="width:120px" value="${regra.valor ?? ''}"></td>
                    <td><input type="number" min="0" max="365" class="fr-prazo" style="width:80px" value="${regra.prazo_dias ?? ''}"></td>
                    <td><button type="button" class="secundario" onclick="this.closest('tr').remove()">Remover</button></td>
                </tr>`;
        }

        function adicionarRegraFrete() {
            document.getElementById('tbody-frete-regras').insertAdjacentHTML('beforeend', linhaRegraFrete({ uf: '', valor: '', prazo_dias: '' }));
        }

        function alternarRetirada() {
            document.getElementById('fr-retirada-box').style.display = document.getElementById('fr-retirada').checked ? '' : 'none';
        }

        async function carregarConfigFrete() {
            const resp = await fetch(`${base}/config-frete`, { headers: { 'Accept': 'application/json' } });
            const dados = await resp.json();
            document.getElementById('tbody-frete-regras').innerHTML = dados.regras.map(linhaRegraFrete).join('');
            document.getElementById('fr-gratis').value = dados.frete_gratis_acima ?? '';
            document.getElementById('fr-retirada').checked = !!dados.permite_retirada;
            document.getElementById('fr-retirada-instrucoes').value = dados.instrucoes_retirada ?? '';
            alternarRetirada();
        }

        async function salvarConfigFrete() {
            const regras = Array.from(document.querySelectorAll('#tbody-frete-regras tr'))
                .map(tr => ({
                    uf: tr.querySelector('.fr-uf').value.trim().toUpperCase() || null,
                    valor: tr.querySelector('.fr-valor').value,
                    prazo_dias: tr.querySelector('.fr-prazo').value || null,
                }))
                .filter(r => r.valor !== '');

            const dados = {
                frete_gratis_acima: document.getElementById('fr-gratis').value || null,
                permite_retirada: document.getElementById('fr-retirada').checked,
                instrucoes_retirada: document.getElementById('fr-retirada-instrucoes').value || null,
                regras,
            };

            const resp = await fetch(`${base}/config-frete`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-frete');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Frete salvo.';
            carregarConfigFrete();
        }

        // ---- Parâmetros operacionais (estoque, PDV, etc.) ----

        async function carregarConfigOperacional() {
            const resp = await fetch(`${base}/config-operacional`);
            const dados = await resp.json();
            document.getElementById('pm-estoque-negativo').checked = !!dados.estoque_permite_negativo;
            document.getElementById('pm-impressao-direta').checked = !!dados.pdv_impressao_direta;
        }

        async function salvarConfigOperacional() {
            const dados = {
                estoque_permite_negativo: document.getElementById('pm-estoque-negativo').checked,
                pdv_impressao_direta: document.getElementById('pm-impressao-direta').checked,
            };
            const resp = await fetch(`${base}/config-operacional`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-operacional');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Parâmetros salvos.';
        }

        // ---- Fiscal (emissão, relatório, cancelamento, inutilização) ----
        // Endpoints vivem sob /fiscal/{empresa}/... (GestaoFiscalController),
        // não sob /dashboard/{empresa}/... como o resto desta tela.
        const fiscalBase = `/fiscal/${empresa}`;

        async function carregarRelatorioFiscal() {
            const params = new URLSearchParams({
                modelo: document.getElementById('f-modelo').value,
                data_inicio: document.getElementById('f-data-inicio').value,
                data_fim: document.getElementById('f-data-fim').value,
                status: document.getElementById('f-status').value,
            });
            const resp = await fetch(`${fiscalBase}/relatorio?${params}`);
            const docs = await resp.json();
            const tbody = document.getElementById('tbody-documentos-fiscais');
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
                        <button class="secundario" onclick="reimprimirFiscal(${d.id})">Reimprimir</button>
                        ${d.status === 'autorizada' ? `<button class="perigo" onclick="cancelarFiscal(${d.id})">Cancelar</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function reimprimirFiscal(documentoId) {
            window.open(`${fiscalBase}/documentos/${documentoId}/reimprimir`, '_blank');
        }

        async function cancelarFiscal(documentoId) {
            const justificativa = prompt('Justificativa do cancelamento (mín. 15 caracteres):');
            if (!justificativa) return;
            const resp = await fetch(`${fiscalBase}/documentos/${documentoId}/cancelar`, {
                method: 'POST', headers: headersJson, body: JSON.stringify({ justificativa }),
            });
            const dados = await resp.json();
            const msg = document.getElementById('msg-documentos-fiscais');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = 'Documento cancelado com sucesso.';
            carregarRelatorioFiscal();
        }

        function exportarFiscal(tipo) {
            const params = new URLSearchParams({
                modelo: document.getElementById('f-modelo').value,
                data_inicio: document.getElementById('f-data-inicio').value,
                data_fim: document.getElementById('f-data-fim').value,
            });
            window.location = `${fiscalBase}/exportar/${tipo}?${params}`;
        }

        async function carregarVendasNaoFiscaisFiscal() {
            const resp = await fetch(`${fiscalBase}/vendas-nao-fiscais`);
            const vendas = await resp.json();
            const tbody = document.getElementById('tbody-vendas-nao-fiscais');
            if (!vendas.length) { tbody.innerHTML = '<tr><td colspan="5">Nenhuma venda não fiscal pendente.</td></tr>'; return; }
            tbody.innerHTML = vendas.map(v => `
                <tr>
                    <td>#${v.id}</td>
                    <td>${v.cliente ? esc(v.cliente.nome) : 'Consumidor não identificado'}</td>
                    <td>R$ ${Number(v.valor_total).toFixed(2)}</td>
                    <td>${new Date(v.data_venda).toLocaleString('pt-BR')}</td>
                    <td><button onclick="importarFiscal(${v.id})">Emitir NFC-e</button></td>
                </tr>
            `).join('');
        }

        async function importarFiscal(vendaId) {
            const modelo = Number(document.getElementById('imp-modelo').value);
            const resp = await fetch(`${fiscalBase}/vendas/${vendaId}/importar`, {
                method: 'POST', headers: headersJson, body: JSON.stringify({ modelo }),
            });
            const dados = await resp.json();
            const msg = document.getElementById('msg-importar-fiscal');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = `${modelo === 55 ? 'NFe' : 'NFC-e'} emitida: status ${dados.status}.`;
            carregarVendasNaoFiscaisFiscal();
            carregarRelatorioFiscal();
        }

        async function carregarNfcesDisponiveisFiscal() {
            const resp = await fetch(`${fiscalBase}/nfces-disponiveis-para-nfe`);
            const lista = await resp.json();
            const tbody = document.getElementById('tbody-nfces-disponiveis');
            if (!lista.length) { tbody.innerHTML = '<tr><td colspan="5">Nenhuma NFC-e disponível para importar.</td></tr>'; return; }
            tbody.innerHTML = lista.map(d => `
                <tr>
                    <td>#${d.numero}</td>
                    <td>${esc(d.cliente || 'Não identificado')}${d.cliente_completo ? '' : ' <span style="color:#c81e1e;">(endereço incompleto)</span>'}</td>
                    <td>R$ ${Number(d.total).toFixed(2)}</td>
                    <td>${new Date(d.created_at).toLocaleString('pt-BR')}</td>
                    <td><button onclick="importarNfceFiscal(${d.id})" ${d.cliente_completo ? '' : 'disabled'}>Gerar NFe</button></td>
                </tr>
            `).join('');
        }

        async function importarNfceFiscal(documentoNfceId) {
            const resp = await fetch(`${fiscalBase}/nfces/${documentoNfceId}/importar-para-nfe`, {
                method: 'POST', headers: headersJson,
            });
            const dados = await resp.json();
            const msg = document.getElementById('msg-importar-nfe');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = dados.message; return; }
            msg.className = 'msg ok'; msg.textContent = `NFe emitida: status ${dados.status}.`;
            carregarNfcesDisponiveisFiscal();
            carregarRelatorioFiscal();
        }

        async function inutilizarFiscal() {
            const dados = {
                modelo: Number(document.getElementById('inut-modelo').value),
                serie: document.getElementById('inut-serie').value,
                numero_inicial: Number(document.getElementById('inut-inicial').value),
                numero_final: Number(document.getElementById('inut-final').value),
                justificativa: document.getElementById('inut-justificativa').value,
            };
            const resp = await fetch(`${fiscalBase}/inutilizacoes`, {
                method: 'POST', headers: headersJson, body: JSON.stringify(dados),
            });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-inutilizar-fiscal');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = `Inutilização ${resposta.status}.`;
        }

        // ---- Caixa (consulta - quem opera é o PDV) ----
        // Endpoints vivem sob /pdv/{empresa}/... (PdvController).

        async function carregarCaixaConsulta() {
            const pdvBase = `/pdv/${empresa}`;
            const [statusResp, extratoResp] = await Promise.all([
                fetch(`${pdvBase}/caixa-status`).then(r => r.json()),
                fetch(`${pdvBase}/caixa-extrato`).then(r => r.json()),
            ]);

            document.getElementById('cx-status').textContent = statusResp.status === 'aberto'
                ? `Caixa aberto - saldo atual: R$ ${Number(statusResp.saldo).toFixed(2)}`
                : 'Caixa fechado.';

            document.getElementById('tbody-caixa-consulta').innerHTML = extratoResp.map(m => `
                <tr>
                    <td>${new Date(m.data_hora).toLocaleString('pt-BR')}</td>
                    <td>${m.tipo}</td>
                    <td>R$ ${Number(m.valor).toFixed(2)}</td>
                    <td>${m.usuario ? m.usuario.name : '-'}</td>
                    <td>${m.observacao ?? '-'}</td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum movimento de caixa registrado.</td></tr>';
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
            document.getElementById('cf-serie-nfe').value = c.serie_nfe_atual ?? 1;
            document.getElementById('cf-numero-nfe').value = c.numero_nfe_atual ?? 0;
            document.getElementById('cf-serie-nfce').value = c.serie_nfce_atual ?? 1;
            document.getElementById('cf-numero-nfce').value = c.numero_nfce_atual ?? 0;
            document.getElementById('cf-pis-cofins-exclui-icms').checked = c.pis_cofins_exclui_icms ?? true;
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
                serie_nfe_atual: document.getElementById('cf-serie-nfe').value,
                numero_nfe_atual: document.getElementById('cf-numero-nfe').value,
                serie_nfce_atual: document.getElementById('cf-serie-nfce').value,
                numero_nfce_atual: document.getElementById('cf-numero-nfce').value,
                pis_cofins_exclui_icms: document.getElementById('cf-pis-cofins-exclui-icms').checked,
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

        let formasPagamentoCache = [];

        async function carregarFormasPagamento() {
            const resp = await fetch(`${base}/formas-pagamento`);
            formasPagamentoCache = await resp.json();
            document.getElementById('tbody-formas-pagamento').innerHTML = formasPagamentoCache.map(f => `
                <tr>
                    <td>${f.descricao}</td>
                    <td>${f.tipo}</td>
                    <td>${f.codigo_tpag}</td>
                    <td>${f.ativo ? 'Sim' : 'Não'}</td>
                    <td>
                        <button class="secundario" onclick="editarFormaPagamento(${f.id})">Editar</button>
                        <button class="secundario" onclick="alternarFormaPagamento(${f.id}, ${!f.ativo})">${f.ativo ? 'Desativar' : 'Ativar'}</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhuma forma de pagamento cadastrada.</td></tr>';
        }

        function editarFormaPagamento(id) {
            const f = formasPagamentoCache.find(x => x.id === id);
            if (!f) return;
            document.getElementById('fp-id').value = f.id;
            document.getElementById('fp-descricao').value = f.descricao;
            document.getElementById('fp-tipo').value = f.tipo;
            document.getElementById('fp-codigo').value = f.codigo_tpag;
            document.getElementById('fp-botao').textContent = 'Salvar edição';
            document.getElementById('fp-cancelar').style.display = 'inline-block';
        }

        function limparFormularioFormaPagamento() {
            document.getElementById('fp-id').value = '';
            document.getElementById('fp-descricao').value = '';
            document.getElementById('fp-tipo').value = 'dinheiro';
            document.getElementById('fp-codigo').value = '';
            document.getElementById('fp-botao').textContent = 'Cadastrar';
            document.getElementById('fp-cancelar').style.display = 'none';
        }

        async function salvarFormaPagamento() {
            const id = document.getElementById('fp-id').value;
            const dados = {
                descricao: document.getElementById('fp-descricao').value,
                tipo: document.getElementById('fp-tipo').value,
                codigo_tpag: document.getElementById('fp-codigo').value,
            };
            const url = id ? `${base}/formas-pagamento/${id}` : `${base}/formas-pagamento`;
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-formas-pagamento');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = id ? 'Forma de pagamento atualizada.' : 'Forma de pagamento cadastrada.';
            limparFormularioFormaPagamento();
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
            alternarCamposProvedor();
        }

        function alternarCamposProvedor() {
            const isBaileys = document.getElementById('wa-provider').value === 'baileys';
            document.getElementById('wa-campos-zapi').style.display = isBaileys ? 'none' : 'flex';
            document.getElementById('card-baileys').style.display = isBaileys ? 'block' : 'none';
        }

        async function baileysAtualizarStatus() {
            const el = document.getElementById('baileys-status');
            const qrWrap = document.getElementById('baileys-qr-wrap');
            try {
                const resp = await fetch(`${base}/whatsapp-baileys/status`);
                const dados = await resp.json();
                if (!resp.ok) { el.textContent = dados.erro || 'Erro ao consultar status.'; return; }

                if (dados.status === 'conectado') {
                    el.textContent = 'Conectado.';
                    qrWrap.innerHTML = '';
                } else if (dados.status === 'aguardando_qr' && dados.qr) {
                    el.textContent = 'Aguardando leitura do QR code...';
                    qrWrap.innerHTML = `<img src="${dados.qr}" alt="QR code" style="max-width:220px; border-radius:6px;">`;
                } else {
                    el.textContent = 'Desconectado.';
                    qrWrap.innerHTML = '';
                }
            } catch (e) {
                el.textContent = 'Não foi possível falar com o serviço de WhatsApp (whatsapp-service não está rodando?).';
            }
        }

        async function baileysIniciar() {
            document.getElementById('baileys-status').textContent = 'Gerando QR code...';
            const resp = await fetch(`${base}/whatsapp-baileys/iniciar`, { method: 'POST', headers: headersJson });
            const dados = await resp.json();
            if (!resp.ok) { document.getElementById('baileys-status').textContent = dados.erro || 'Erro ao iniciar sessão.'; return; }
            baileysAtualizarStatus();
        }

        async function baileysDesconectar() {
            await fetch(`${base}/whatsapp-baileys/desconectar`, { method: 'POST', headers: headersJson });
            baileysAtualizarStatus();
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
