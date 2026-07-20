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
        .card > h1:first-child, .card > .abas-produto:first-child { margin-top: 0; }
        .card table { margin-top: 8px; }
    </style>
</head>
<body>
    <div class="layout">
        <div class="sidebar">
            <div class="logo"><img src="/images/logo.jpg" alt="Logo"></div>
            <div class="empresa">{{ $empresaSlug }}</div>
            <button class="ativo" onclick="mostrarSecao('dashboard', this)">Dashboard</button>

            <div class="grupo-label">PDV</div>
            <a class="link-pdv" href="{{ url("/pdv/{$empresaSlug}/caixa") }}" target="_blank">Abrir frente de caixa ↗</a>

            <div class="grupo-label">Cadastros</div>
            <button onclick="mostrarSecao('agenda', this)">Agenda de Visitas</button>
            <button onclick="mostrarSecao('produtos', this)">Produtos</button>
            <button onclick="mostrarSecao('grupos', this)">Grupos de Produto</button>
            <button onclick="mostrarSecao('clientes', this)">Clientes</button>
            <button onclick="mostrarSecao('fornecedores', this)">Fornecedores</button>
            <button onclick="mostrarSecao('vendedores', this)">Vendedores</button>
            <button onclick="mostrarSecao('atendentes', this)">Atendentes</button>

            <div class="grupo-label">Financeiro</div>
            <button onclick="mostrarSecao('financeiro', this)">Contas a Pagar/Receber</button>
            <button onclick="mostrarSecao('plano-contas', this)">Plano de Contas</button>
            <button onclick="mostrarSecao('bancos', this)">Bancos</button>
            <button onclick="mostrarSecao('caixa-consulta', this)">Caixa (consulta)</button>

            <div class="grupo-label">Fiscal</div>
            <button onclick="mostrarSecao('fiscal', this)">Emissão e Relatórios</button>
            <button onclick="mostrarSecao('config-fiscal', this)">Config. Fiscal</button>

            <div class="grupo-label">Configurações</div>
            <button onclick="mostrarSecao('usuarios', this)">Usuários</button>
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

                    <div class="abas-produto">
                        <button type="button" class="ativa" onclick="mostrarAbaProduto('geral', this)">Geral</button>
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
                            </div>
                        </div>

                        <div class="grupo-campos">
                            <h3>Dimensões e mídia</h3>
                            <div class="linha-form">
                                <div><label>Peso líquido (kg)</label><input type="number" step="0.001" id="pr-peso-liquido" style="width:110px"></div>
                                <div><label>Peso bruto (kg)</label><input type="number" step="0.001" id="pr-peso-bruto" style="width:110px"></div>
                                <div style="flex:1; min-width:220px"><label>Imagem (URL)</label><input type="text" id="pr-imagem" placeholder="https://..."></div>
                            </div>
                            <div class="linha-form">
                                <div style="flex:1"><label>Descrição</label><input type="text" id="pr-descricao"></div>
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
                        <div style="align-self:flex-end;"><button type="button" class="secundario" onclick="buscarCnpjCliente()">Buscar CNPJ</button></div>
                        <div><label>Telefone</label><input type="text" id="cl-telefone" style="width:120px"></div>
                        <div><label>E-mail</label><input type="email" id="cl-email"></div>
                        <div><label><input type="checkbox" id="cl-lgpd"> Consentimento LGPD</label></div>
                    </div>
                    <div class="linha-form">
                        <div><label>CEP</label><input type="text" id="cl-cep" style="width:90px" placeholder="00000-000" onblur="buscarCepCliente()"></div>
                        <div><label>Logradouro</label><input type="text" id="cl-logradouro"></div>
                        <div><label>Número</label><input type="text" id="cl-numero" style="width:70px"></div>
                        <div><label>Bairro</label><input type="text" id="cl-bairro"></div>
                        <div><label>Município</label><input type="text" id="cl-municipio"></div>
                        <div><label>UF</label><input type="text" id="cl-uf" style="width:50px" maxlength="2"></div>
                        <div><label>Cód. IBGE</label><input type="text" id="cl-ibge" style="width:80px"></div>
                        <div><label>IE</label><input type="text" id="cl-ie" style="width:100px" placeholder="preencher manualmente"></div>
                    </div>
                    <p style="font-size:11px; color:var(--cor-texto-suave); margin-top:0;">
                        CEP preenche o endereço automaticamente. "Buscar CNPJ" traz razão social/endereço da Receita
                        Federal - Inscrição Estadual não vem dessa consulta (é cadastrada por estado, não existe API
                        nacional gratuita), preencha à mão.
                    </p>
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
                        <div style="align-self:flex-end;"><button type="button" class="secundario" onclick="buscarCnpjFornecedor()">Buscar CNPJ</button></div>
                        <div><label>IE</label><input type="text" id="fo-ie" style="width:100px" placeholder="preencher manualmente"></div>
                    </div>
                    <div class="linha-form">
                        <div><label>Contato</label><input type="text" id="fo-contato"></div>
                        <div><label>Telefone</label><input type="text" id="fo-telefone" style="width:120px"></div>
                        <div><label>E-mail</label><input type="email" id="fo-email"></div>
                    </div>
                    <div class="linha-form">
                        <div><label>CEP</label><input type="text" id="fo-cep" style="width:90px" placeholder="00000-000" onblur="buscarCepFornecedor()"></div>
                        <div style="flex:1"><label>Endereço</label><input type="text" id="fo-endereco" style="width:100%"></div>
                    </div>
                    <p style="font-size:11px; color:var(--cor-texto-suave); margin-top:0;">
                        CEP preenche o endereço automaticamente. "Buscar CNPJ" traz razão social/endereço da Receita
                        Federal - Inscrição Estadual não vem dessa consulta, preencha à mão.
                    </p>
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
                        <div><button class="acao" id="at-botao" onclick="salvarAtendente()">Cadastrar</button></div>
                        <div><button class="secundario" onclick="limparFormularioAtendente()" style="display:none;" id="at-cancelar">Cancelar edição</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Nome</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tbody-atendentes"></tbody>
                    </table>
                    <p class="msg" id="msg-atendentes"></p>
                </div>
                <div class="card">
                    <h2 style="font-size:14px; margin-top:0;">Relatório de vendas por atendente</h2>
                    <div class="linha-form">
                        <div><label>De</label><input type="date" id="atr-inicio"></div>
                        <div><label>Até</label><input type="date" id="atr-fim"></div>
                        <div><button class="secundario" onclick="carregarRelatorioAtendentes()">Consultar</button></div>
                    </div>
                    <table>
                        <thead><tr><th>Atendente</th><th>Vendas</th><th>Valor total</th></tr></thead>
                        <tbody id="tbody-relatorio-atendentes"></tbody>
                    </table>
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
                        <div style="flex:1"><label>URL do logo</label><input type="text" id="lj-logo" placeholder="https://..." style="width:100%"></div>
                        <div><label>Cor primária</label><input type="color" id="lj-cor" style="width:60px; padding:2px;"></div>
                        <div><button class="acao" onclick="salvarConfigLoja()">Salvar</button></div>
                    </div>
                    <p class="msg" id="msg-config-loja"></p>
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
        const base = `/dashboard/${empresa}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const headersJson = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken };

        const carregadores = {
            dashboard: carregarIndicadores,
            agenda: carregarAgenda,
            produtos: () => { carregarProdutos(); carregarGrupos(); },
            clientes: carregarClientes,
            fornecedores: carregarFornecedores,
            vendedores: carregarVendedores,
            atendentes: carregarAtendentes,
            grupos: carregarGrupos,
            financeiro: () => { carregarSelectsFinanceiro().then(() => { carregarContasPagar(); carregarContasReceber(); }); },
            'plano-contas': () => { carregarPlanoContas(); },
            bancos: carregarBancos,
            usuarios: carregarUsuarios,
            'config-fiscal': () => { carregarConfigFiscal(); carregarCertificado(); carregarConfigLoja(); },
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
            await Promise.all([carregarFornecedoresParaSelect(), carregarTabelasFiscaisProduto()]);
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
            document.getElementById('pr-preco').value = p.preco_venda;
            document.getElementById('pr-custo').value = p.preco_custo ?? '';
            document.getElementById('pr-valor-atacado').value = p.valor_atacado ?? '';
            document.getElementById('pr-estoque').value = p.estoque_atual ?? '';
            document.getElementById('pr-estoque-minimo').value = p.estoque_minimo ?? '';
            document.getElementById('pr-peso-liquido').value = p.peso_liquido ?? '';
            document.getElementById('pr-peso-bruto').value = p.peso_bruto ?? '';
            document.getElementById('pr-imagem').value = p.imagem_url ?? '';
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

        function limparFormularioProduto() {
            document.getElementById('pr-id').value = '';
            [
                'pr-codigo', 'pr-codigo-barras', 'pr-nome', 'pr-categoria', 'pr-custo', 'pr-valor-atacado',
                'pr-estoque', 'pr-estoque-minimo', 'pr-peso-liquido', 'pr-peso-bruto', 'pr-imagem', 'pr-descricao',
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
            document.getElementById('pr-sujeito-is').checked = false;
            document.getElementById('pr-botao').textContent = 'Cadastrar';
            document.getElementById('pr-cancelar').style.display = 'none';
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
                preco_venda: Number(document.getElementById('pr-preco').value),
                preco_custo: document.getElementById('pr-custo').value || null,
                valor_atacado: document.getElementById('pr-valor-atacado').value || null,
                estoque_atual: document.getElementById('pr-estoque').value || null,
                estoque_minimo: document.getElementById('pr-estoque-minimo').value || null,
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
            const resp = await fetch(url, { method: id ? 'PUT' : 'POST', headers: headersJson, body: JSON.stringify(dados) });
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

        let atendentesCache = [];

        async function carregarAtendentes() {
            const resp = await fetch(`${base}/atendentes`);
            atendentesCache = await resp.json();
            document.getElementById('tbody-atendentes').innerHTML = atendentesCache.map(a => `
                <tr>
                    <td>${a.nome}</td>
                    <td>${a.ativo ? 'Sim' : 'Não'}</td>
                    <td><button class="secundario" onclick="editarAtendente(${a.id})">Editar</button></td>
                </tr>
            `).join('') || '<tr><td colspan="3">Nenhum atendente cadastrado.</td></tr>';
        }

        function editarAtendente(id) {
            const a = atendentesCache.find(x => x.id === id);
            if (!a) return;
            document.getElementById('at-id').value = a.id;
            document.getElementById('at-nome').value = a.nome;
            document.getElementById('at-botao').textContent = 'Salvar edição';
            document.getElementById('at-cancelar').style.display = 'inline-block';
        }

        function limparFormularioAtendente() {
            document.getElementById('at-id').value = '';
            document.getElementById('at-nome').value = '';
            document.getElementById('at-botao').textContent = 'Cadastrar';
            document.getElementById('at-cancelar').style.display = 'none';
        }

        async function salvarAtendente() {
            const id = document.getElementById('at-id').value;
            const dados = { nome: document.getElementById('at-nome').value };
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

            const resp = await fetch(`${base}/atendentes-relatorio?${params}`);
            const lista = await resp.json();
            document.getElementById('tbody-relatorio-atendentes').innerHTML = lista.map(r => `
                <tr><td>${r.nome}</td><td>${r.vendas_count}</td><td>R$ ${Number(r.valor_total).toFixed(2)}</td></tr>
            `).join('') || '<tr><td colspan="3">Nenhum atendente cadastrado.</td></tr>';
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
                    <td>${c.cliente ? c.cliente.nome : '-'}</td>
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
                clientesLista.map(c => `<option value="${c.id}">${c.nome}</option>`).join('');
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
            document.getElementById('lj-cor').value = dados.cor_primaria ?? '#394285';
        }

        async function salvarConfigLoja() {
            const dados = {
                segmento: document.getElementById('lj-segmento').value || null,
                logo_url: document.getElementById('lj-logo').value || null,
                cor_primaria: document.getElementById('lj-cor').value || null,
            };
            const resp = await fetch(`${base}/config-loja`, { method: 'PUT', headers: headersJson, body: JSON.stringify(dados) });
            const resposta = await resp.json();
            const msg = document.getElementById('msg-config-loja');
            if (!resp.ok) { msg.className = 'msg erro'; msg.textContent = resposta.message || JSON.stringify(resposta.errors); return; }
            msg.className = 'msg ok'; msg.textContent = 'Identidade visual salva.';
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
                    <td>${v.cliente ? v.cliente.nome : 'Consumidor não identificado'}</td>
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
                    <td>${d.cliente || 'Não identificado'}${d.cliente_completo ? '' : ' <span style="color:#c81e1e;">(endereço incompleto)</span>'}</td>
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
