# Plano de Projeto — Plataforma Multi-Empresa de Comércio e Agendamento

SaaS para comércio com venda direta e/ou agendamento de experiências · Loja Pública · Sistema Interno · PDV Fiscal · **PHP (Laravel) + PostgreSQL**

*Documento atualizado — v2 — Julho de 2026*
*Revisão: decisões técnicas alinhadas com o cliente em reunião de escopo*

---

## Changelog desta versão (v2)

| # | Decisão | Status |
|---|---|---|
| 1 | Stack trocada de Node.js para **PHP (Laravel)**, por maior maturidade de bibliotecas fiscais brasileiras | ✅ Confirmado |
| 2 | Estratégia de controle de vagas definida: **lock pessimista (`SELECT ... FOR UPDATE`)** dentro de transação | ✅ Confirmado |
| 3 | Middleware único de contexto multi-tenant (`SET LOCAL app.current_empresa_id`) para reforçar o RLS | ✅ Confirmado |
| 4 | Ambiente de **homologação** obrigatório por empresa antes de liberar produção fiscal | ✅ Confirmado |
| 5 | **Consentimento LGPD** incorporado ao escopo da Fase 1 (não fica para "evolução futura") | ✅ Confirmado |
| 6 | **Log de auditoria** obrigatório para create/update/delete/read sensível, aplicado de forma genérica (não manual) | ✅ Confirmado |

---

## Changelog técnico — 2026-07-18 (implementação, sem mudança de decisão de escopo)

Registra o que foi efetivamente construído e validado desde o alinhamento acima. Não substitui as decisões da tabela anterior — é o relato de implementação.

| # | Item | Status |
|---|---|---|
| 1 | Esqueleto Laravel 13 criado, RLS habilitado (`FORCE ROW LEVEL SECURITY`) e testado em todas as tabelas multi-tenant, middleware `SetTenantContext` implementado | ✅ Implementado e testado |
| 2 | `ReservaVagaService` (lock pessimista) implementado e testado; comando agendado `app:liberar-reservas-expiradas` liberando reservas vencidas | ✅ Implementado e testado |
| 3 | Fluxo completo da loja pública (catálogo, reserva, checkout com consentimento LGPD) | ✅ Implementado e testado |
| 4 | Log de auditoria genérico (`AuditLogObserver`) aplicado a 9 models sensíveis via `AppServiceProvider`, com redação de campos sensíveis (senha) | ✅ Implementado e testado |
| 5 | Módulo fiscal: **emissão real de NFC-e validada e autorizada pela SEFAZ-SP em homologação**, com certificado A1 real (empresa de testes). Inclui os campos IBS/CBS da Reforma Tributária (alíquotas de teste da fase de transição 2026) | ✅ Validado com a SEFAZ real |
| 6 | Senha do certificado digital armazenada com criptografia real (cast `encrypted` do Laravel) | ✅ Implementado |
| 7 | Painel de gestão fiscal: cancelamento de NFC-e, inutilização de numeração, reimpressão de cupom, importação de venda não fiscal → NFC-e, relatórios e exportação (XMLs + planilha para o contador) | ✅ Implementado e testado (cancelamento validado contra a SEFAZ real) |
| 8 | **Login único do sistema interno** (Escopo v2, seção 2.2) implementado: e-mail/senha identifica a empresa e o nível de acesso automaticamente, sessão protege o painel de gestão fiscal | ✅ Implementado e testado |
| 9 | **Painel Super Admin** (Escopo v2, seção 2.2) implementado: gestão de empresas (cadastro, suspensão/reativação), planos e assinaturas. Suspender uma empresa já bloqueia login de todos os usuários dela | ✅ Implementado e testado com dados reais |
| 10 | **PDV (frente de caixa)** implementado: venda de produtos físicos (com baixa de estoque), venda de visita agendada com trava anti-overbooking (reaproveita o `ReservaVagaService`), comissão por vendedor, venda fiscal (emite NFC-e na hora) ou não fiscal | ✅ Implementado e testado com dados reais |
| 11 | **Dashboard administrativo** implementado: indicadores (vagas ocupadas hoje, vendas do mês, ocupação média, comissões), agenda de visitas, produtos, clientes, vendedores, financeiro (contas a pagar/receber) e usuários (restrito a perfil admin) | ✅ Implementado e testado com dados reais |
| 12 | **NFe (modelo 55)** implementada: destinatário completo (endereço estruturado do cliente, novo), NCM/CFOP por produto, **emissão real autorizada pela SEFAZ-SP em homologação**. Painel de gestão fiscal unificado para NFC-e e NFe: cancelar, inutilizar, reimprimir (view própria em formato DANFE), relatório e exportação com filtro por modelo | ✅ Validado com a SEFAZ real |
| 13 | **Importar NFC-e → NFe (regularização)**: gera uma NFe formal referenciando uma venda já documentada por NFC-e, com CFOP 5929 (mesmo estado) ou 6929 (fora do estado) resolvido automaticamente pela UF do cliente x UF da empresa. **Validado com emissão real autorizada pela SEFAZ-SP** | ✅ Validado com a SEFAZ real |
| 14 | **Identidade visual** aplicada em todo o sistema interno (login, dashboard, PDV, painel fiscal, super admin): logo e paleta de cores extraídos do logo da empresa, sistema de design centralizado (`public/css/sistema.css`) | ✅ Implementado |
| 15 | **Cadastros expandidos**: Produto ganha código/SKU, categoria, unidade, preço de custo e status ativo; Cliente e Fornecedor ganham cadastro/edição completos no dashboard (antes só listagem ou criação implícita via venda) | ✅ Implementado e testado |
| 16 | **Cadastro de emitente e certificado digital** no dashboard (antes só configurável via script/tinker): endereço fiscal + regime tributário/numeração editáveis pelo admin; upload do certificado `.pfx` com validação real (tenta abrir o certificado com a senha informada antes de salvar) e validade extraída automaticamente do próprio arquivo, não digitada | ✅ Implementado e testado com o certificado real |
| 17 | **Módulo de pagamento** (Pix + cartão): gateway escolhido **por empresa** (não global), pois cada cliente negocia taxa melhor com um provedor diferente — `PagamentoGatewayFactory` resolve Mercado Pago, PagSeguro ou Cielo a partir de `ConfigPagamento` da empresa. Mercado Pago com Pix e cartão implementados de verdade (REST direto, sem SDK); PagSeguro/Cielo com contrato pronto mas stub (aguardando credenciais de teste). Sem gateway configurado ou inativo, cai automaticamente no `SimuladoPagamentoGateway` (aprova na hora), preservando o checkout público existente. Cadastro de **forma de pagamento** por empresa (com código `tPag` da NFe/NFC-e, antes fixo em `'01'`), webhook do Mercado Pago (sempre reconsulta a API antes de confirmar, nunca confia no payload recebido) e tela de configuração de gateway no dashboard (token nunca é devolvido pela API, só indica se já existe) | ✅ Implementado e testado (Mercado Pago validado com `Http::fake`, sem credenciais reais ainda) |
| 18 | **Fechamento das pontas do módulo de pagamento**: PDV (`caixa.blade.php`) ganhou seletor de forma de pagamento, enviando `forma_pagamento_id` na finalização da venda; checkout público (`CheckoutController`) passou a aceitar `cartao_token`/`cartao_parcelas`/`cartao_metodo` e, quando presentes, usa `PagamentoService::criarCobrancaCartao()` (gateway real, se configurado); sem token (front-end de loja pública ainda não integra o SDK do gateway - este repositório expõe só a API), mantém o comportamento anterior de aprovar na hora | ✅ Implementado e testado |
| 19 | **Notificações via WhatsApp** (Escopo v2, decisão de 2026-07-19): provedor escolhido **por empresa** (mesma lógica do gateway de pagamento) - a empresa decide entre Z-API (pago, mas API REST simples e estável) ou Baileys (gratuito, mas usa um número comum via QR code, fora dos Termos de Uso do WhatsApp, com risco real de banimento). Z-API implementado de verdade (REST direto). Envia confirmação automática ao concluir um checkout com visita agendada e paga, e lembrete no dia anterior via comando agendado (`app:enviar-lembretes-visita`, diário às 9h). Toda mensagem enviada é registrada em `notificacoes` (base para cobrar a empresa por mensagem no futuro). Tela de configuração no dashboard (token nunca é devolvido pela API) | ✅ Implementado e testado (Z-API validado com `Http::fake`, sem credenciais reais ainda) |
| 20 | **Microserviço `whatsapp-service/` (Baileys)** (Escopo v2, decisão de 2026-07-19: cliente vai iniciar operação com Baileys): serviço Node.js separado (Express + `@whiskeysockets/baileys`) que mantém uma sessão de WhatsApp Web por empresa (`sessoes/{empresa_id}/`, nunca versionada - equivalente em sensibilidade ao certificado digital A1), com reconexão automática em caso de queda. Expõe uma API HTTP interna (autenticada por token compartilhado, `x-internal-token`) com `status`, `iniciar` (gera QR code), `desconectar` e `enviar`. `BaileysNotificacaoGateway` chama esse serviço via `Http`, com a mesma URL/token configurados em `config('services.baileys')`. Dashboard ganhou o card "Parear número (Baileys)": mostra o QR code (atualizado por polling) para o admin escanear com o celular, sem precisar de acesso ao terminal do servidor | ✅ Implementado e validado manualmente (conexão real com os servidores do WhatsApp confirmada - QR code de verdade foi gerado; pareamento completo com um número real ainda depende do cliente escanear) |
| 21 | **Cobrança de assinatura das empresas clientes** (Escopo v2, decisão de 2026-07-20): a **plataforma** cobrando cada **empresa cliente** pela mensalidade (diferente de `ConfigPagamento`, que é a empresa cliente cobrando o próprio consumidor final) - via Asaas, escolhido por não cobrar mensalidade da própria plataforma (só uma taxa quando uma cobrança de fato acontece). Configuração **única e global** (`ConfigAssinatura`, sem `empresa_id`, sem RLS - mesma lógica de `planos`/`empresas`, que são a raiz do multi-tenant), gerenciada só pelo super admin. `AssinaturaService` cria o cliente e a assinatura recorrente de verdade no Asaas quando ativo; sem Asaas configurado, mantém o cadastro manual original (super admin digita o status à mão). Webhook (`/api/webhooks/assinatura/asaas`) reage a `PAYMENT_OVERDUE` **suspendendo automaticamente** o acesso da empresa (mesmo campo `status` usado pela suspensão manual) e a `PAYMENT_RECEIVED`/`PAYMENT_CONFIRMED` reativando. **Baixa manual** sempre disponível para o super admin (botão "Dar baixa" no painel), independente de haver Asaas configurado - cobre pagamento combinado fora do sistema (pedido explícito do cliente) | ✅ Implementado e testado (Asaas validado com `Http::fake`, sem credenciais reais ainda) |
| 22 | **Achados da auditoria de 2026-07-20 resolvidos**: (a) envio de WhatsApp de confirmação de agendamento passou de síncrono (dentro do `CheckoutController`) para assíncrono via `EnviarConfirmacaoAgendamentoJob` (fila `database`, já configurada mas nunca usada até aqui) - um Z-API/Baileys lento não atrasa mais a resposta do checkout ao cliente final; (b) `throttle` adicionado às rotas públicas sem login (catálogo 60/min, escrita - reserva/checkout - 20/min, webhooks 60/min), que antes só existia no `/login`; (c) **recuperação de senha** ("esqueci minha senha") implementada usando o broker nativo do Laravel (`password_reset_tokens`, que já existia na migration inicial mas nunca teve rotas/telas) - fluxo completo (pedir link → e-mail com token → redefinir → login) validado manualmente de ponta a ponta, resposta genérica tanto para e-mail cadastrado quanto não cadastrado (evita enumeração de usuários) | ✅ Implementado e testado |
| 23 | **Busca automática de CNPJ e CEP** nos cadastros de cliente, fornecedor e emitente (dashboard): botão "Buscar CNPJ" consulta a BrasilAPI (dados públicos da Receita Federal, sem chave) e preenche razão social/nome fantasia, endereço completo e telefone/e-mail quando disponíveis; digitar um CEP completo em qualquer um dos três formulários (`onblur`) consulta o ViaCEP e preenche logradouro/bairro/município/UF/código IBGE automaticamente. **Inscrição Estadual continua manual** em todos os casos - não existe fonte nacional gratuita de consulta de IE (é cadastrada por Sefaz estadual, não pela Receita Federal); ambas as chamadas são feitas direto do navegador (sem passar pelo backend), já que são APIs públicas com CORS liberado para esse uso | ✅ Implementado e validado contra as APIs reais (BrasilAPI e ViaCEP responderam com os campos esperados) |
| 24 | **Módulo financeiro expandido** (Escopo v2, decisão de 2026-07-21): `Grupo` (grupo de produtos, formaliza em tabela o que hoje é só o texto livre `produtos.categoria` - convivem, nada foi migrado automaticamente), `PlanoContas` (categoriza contas a pagar/receber por tipo - ex. "Fornecedores", "Salários"), `Banco` (cadastro de contas bancárias da empresa) e `GravaBanco` (todo movimento bancário, gera o extrato de cada conta com saldo corrente calculado). Ao marcar uma conta a pagar/receber como paga informando um banco, o movimento bancário correspondente (débito/crédito) é lançado **automaticamente** em `GravaBanco` - fecha o ciclo entre financeiro e extrato sem lançar a mesma coisa duas vezes. `Caixa` controla o caixa físico do PDV: abertura, fechamento, sangria e suprimento são linhas de uma única tabela (por pedido explícito do cliente); só existe um caixa aberto por empresa por vez, calculado dinamicamente (última abertura sem fechamento posterior), sem tabela de sessão separada. Relatórios: grupo (produtos e valor de estoque por grupo), plano de contas (total/pago/em aberto por categoria e período) e extrato bancário (saldo anterior + movimentos + saldo corrente linha a linha) | ✅ Implementado e testado (17 testes novos; um bug real de saldo em dobro no extrato sem filtro de data foi encontrado e corrigido antes do commit) |

Com isso, as três frentes do Sistema Interno previstas no Escopo v1/v2 (PDV, Dashboard administrativo, Painel Super Admin), a Loja Pública e o módulo fiscal completo (NFC-e + NFe) estão implementados, testados e com identidade visual própria — restam os itens de pendência listados abaixo.

**Achado técnico da emissão real de NFe (2026-07-18):** a SEFAZ rejeitou em sequência três pontos que só um teste real revela (documentado como lição - homologação com envio de verdade continua sendo o único jeito confiável de validar isso):
1. `[745] NF-e sem grupo do PIS` — NFe exige os grupos PIS/COFINS por item mesmo no Simples Nacional (CST 07 - não tributado - resolve, pois o PIS/COFINS já está embutido no DAS unificado);
2. `[232]`/`[805]` — destinatário pessoa jurídica dentro do mesmo estado sem Inscrição Estadual cadastrada: a SEFAZ-SP não aceita nem "não contribuinte" (indIEDest=9) nem "isento" (indIEDest=2) para CNPJ — **a IE do cliente PJ precisa ser real**, cadastrada no dashboard antes de emitir. Para CPF (pessoa física), indIEDest=9 funciona normalmente;
3. `[679] Chave de Acesso referenciada com Modelo inválido` — a tag `NFref/refNFe` do NFePHP só vale para referenciar outra NFe, não uma NFC-e; removida do fluxo de regularização (o vínculo já fica registrado internamente via `documento_fiscal_origem_id` e aparece no painel);
4. `[434] NF-e sem indicativo do intermediador` — campo novo do schema PL_010 (Reforma Tributária), `indIntermed=0` (operação sem marketplace) resolve.

**Achado técnico relevante (2026-07-18):** o RLS por si só criava um paradoxo na autenticação — para descobrir a empresa de um usuário é preciso *ler* a tabela `users` por e-mail, mas o RLS bloqueia essa leitura até o tenant estar definido, e o tenant só se define depois de autenticar. Resolvido com um middleware global (`BootstrapAuthDatabaseContext`) que abre um bypass de RLS só na fase de resolução de autenticação (primeiro middleware do grupo `web`), fechado de volta ao escopo correto pelo `SetTenantContext` antes de qualquer query de negócio rodar. Esse bug só apareceu em teste manual via navegador/curl — os testes automatizados usavam `actingAs()`, que contorna a resolução real de sessão e mascarou o problema. Registrado aqui como lição: **testes automatizados com `actingAs()` não substituem um teste manual do fluxo de login real**.

**Pendências conhecidas, registradas no código (`TODO`) e aqui:**
- Cliente pessoa jurídica sem Inscrição Estadual real cadastrada **não consegue** receber NFe dentro do mesmo estado (rejeição confirmada pela SEFAZ-SP) — o dashboard já tem campo para isso, mas depende de o operador preencher a IE de cada cliente PJ antes de emitir (o restante do endereço/dados agora é preenchido automaticamente por CNPJ/CEP, ver item 23 do changelog técnico - só a IE continua manual, por não existir fonte nacional gratuita de consulta);
- Só testado com Simples Nacional (CRT=1); outros regimes tributários não cobertos;
- Tabela de `cClassTrib` do IBS/CBS usa o código padrão (000001) — precisa revisão quando a SEFAZ consolidar a tabela definitiva por segmento;
- ~~Sem recuperação de senha~~ - implementada (ver item 22 do changelog técnico); ainda falta uma página de erro 403 dedicada para usuário inativo/empresa suspensa (hoje volta pro login com mensagem) - e o e-mail de redefinição depende de configurar SMTP de verdade em produção (hoje `MAIL_MAILER=log`, só grava no log local, não envia de verdade);
- Painel Super Admin ainda não tem edição de plano/reassociação de empresa depois de criada — só cadastro inicial e mudança de status (cobrança de assinatura automática já foi implementada, ver item 21 do changelog técnico);
- O microserviço `whatsapp-service/` (Baileys) precisa estar rodando ao lado do Laravel em produção (`pm2`/`systemd`/container à parte, ver `whatsapp-service/README.md`) - se ele cair, o provedor Baileys passa a registrar as mensagens como "falha" (não trava o resto do sistema, mas para de notificar); pareamento real com um número de WhatsApp de verdade ainda não foi validado (só a geração do QR code e a conexão com os servidores do WhatsApp);
- ~~Envio de WhatsApp síncrono dentro do checkout~~ e ~~rotas públicas sem `throttle`~~ - resolvidos (ver item 22 do changelog técnico). Agora que existe fila de verdade em uso, **produção precisa rodar `php artisan queue:work`** (ou `queue:listen`) como um processo contínuo à parte, senão o job de confirmação de WhatsApp fica parado em `jobs` sem nunca ser processado - mesma lógica operacional do `whatsapp-service/` (Baileys): sem o processo rodando, essa peça específica fica muda, mas não derruba o resto do sistema.

**Achados técnicos (2026-07-18, cadastro de emitente/certificado):**
- `AuditLogObserver` gravava `empresa_id = null` no log ao editar o próprio cadastro da `Empresa` (ela não tem coluna `empresa_id`, só `id`) - violava o RLS de `logs` sempre que um admin comum (não super_admin) editava o endereço fiscal da própria empresa. Corrigido tratando `Empresa` como caso especial (usa o próprio `id` como `empresa_id` do log);
- A regra de validação `mimes:pfx,p12` do Laravel **não reconhece certificados `.pfx` reais** - PKCS12 não tem um MIME type padronizado no mapa do Symfony, então a regra rejeitava até um certificado genuíno com extensão correta. Substituída por validação de extensão + a tentativa real de abrir o certificado com a senha informada (que já seria feita de qualquer forma antes de salvar).

**Achado técnico (2026-07-18, módulo de pagamento):** o bug recorrente de pluralização do Eloquent para nomes em português apareceu de novo — `FormaPagamento` foi mapeado por padrão para `forma_pagamentos` em vez de `formas_pagamento`, quebrando o teste de cadastro com `SQLSTATE[42P01]: Undefined table`. Mesma causa e mesma correção de sempre (`protected $table = '...'` explícito), já visto com `Vendedor`, `Fornecedor`, `AgendaVisitacao` e `ReservaTemporaria` — reforça que todo novo model com nome irregular no plural precisa declarar a tabela manualmente, não confiar na convenção.

**Achado técnico (2026-07-19, fechamento do módulo de pagamento):** não existe view/SPA de loja pública neste repositório — o checkout é só API (`CheckoutController`), consumida por um front-end à parte (conforme já previsto na seção 6, "Loja pública: Framework web moderno consumindo a API Laravel"). Por isso, "tokenização de cartão no front-end" não é algo que o back-end resolve sozinho: o que foi entregue é a API pronta para receber o token do cartão (gerado pelo SDK do gateway do lado do cliente, ex. Mercado Pago.js/Bricks) e processá-lo com o gateway real da empresa; sem token informado, o checkout continua aprovando na hora (mesmo comportamento de antes), então nenhuma loja existente quebra até o front-end de fato integrar o SDK.

**Achado técnico (2026-07-19, notificações WhatsApp):** o Baileys é uma biblioteca Node.js que implementa o protocolo do WhatsApp Web por engenharia reversa — não existe equivalente PHP nativo. Diferente de Z-API/Mercado Pago/PagSeguro/Cielo (todos APIs REST comuns, chamáveis direto do Laravel), usá-lo de verdade exige um processo Node.js rodando à parte, mantendo uma sessão (QR code) por empresa, com uma API HTTP interna para o Laravel conversar com ele. Decisão registrada em conversa com o cliente em 2026-07-19: o cliente final decide, por empresa, entre Z-API (pago, estável) e Baileys (gratuito, mas fora dos Termos de Uso do WhatsApp — risco real de banimento do número); construímos a arquitetura pronta para os dois (`NotificacaoGatewayInterface` + `NotificacaoGatewayFactory`, mesmo padrão do módulo de pagamento).

**Achado técnico (2026-07-19, microserviço Baileys):** o cliente confirmou que vai *iniciar* a operação com Baileys (não Z-API), então construímos o microserviço `whatsapp-service/` (Node.js + Express + `@whiskeysockets/baileys`) de verdade, não como stub. Validado manualmente: o serviço conectou nos servidores reais do WhatsApp e gerou um QR code de verdade (`data:image/png;base64,...`) - o máximo de validação possível sem um número de celular real escaneando. Pontos de atenção documentados no `README.md` do serviço: (1) a pasta `sessoes/` guarda as credenciais da sessão pareada e nunca pode ser versionada nem exposta - mesmo nível de sensibilidade do certificado digital A1; (2) o serviço precisa ficar rodando continuamente (não é uma função serverless - a sessão é um WebSocket de longa duração), então produção exige um supervisor de processo (`pm2`/`systemd`/container) ao lado do Laravel; (3) o proxy no `DashboardController` retorna 503 com mensagem clara se o serviço Node não estiver no ar, em vez de quebrar a tela.

**Achado técnico (2026-07-21, extrato bancário):** o cálculo de saldo do extrato (`extratoBanco`) tinha um bug real, pego pelo teste antes do commit: quando a consulta não informa `data_inicio`, o "saldo anterior ao período" estava sendo calculado somando **todos** os movimentos do banco (sem filtro de data nenhum), e depois a lista de movimentos do período (também sem filtro, no mesmo caso) somava tudo de novo por cima - dobrando o valor de cada movimento no saldo final. Corrigido: a soma de "saldo anterior" só roda quando `data_inicio` é realmente informado; sem filtro de período, o saldo anterior é só o `saldo_inicial` cadastrado do banco. Fica registrado porque é o tipo de bug que só aparece testando o caminho "sem filtro" explicitamente - o caminho "com filtro de data" já funcionava certo de primeira.

---

## 1. Resumo Executivo

*(mantém-se o conteúdo original do documento v1 — plataforma multi-empresa com venda direta e/ou agendamento de experiências, três frentes integradas: loja pública, sistema interno e módulo fiscal, sobre base multi-tenant.)*

O maior desafio técnico do projeto continua sendo duplo: (1) impedir overbooking na venda de vagas/visitas, feita simultaneamente pela loja pública e pelo caixa físico, e (2) garantir isolamento total dos dados entre empresas. Nesta versão, ambas as soluções foram detalhadas a nível de implementação (seções 3.5 e 3.6).

## 2. Escopo do Projeto

*(sem alterações em relação ao v1 — ver seções 2.1 a 2.4 do documento original: página pública por empresa, sistema interno com login único, módulo fiscal NFe/NFC-e, planos Básico e Completo.)*

**Adição ao escopo da Fase 1:**
- Captura de **consentimento LGPD** no cadastro do cliente (loja pública e PDV), com timestamp e versão do termo aceito.
- **Log de auditoria** ativo desde o primeiro módulo entregue, cobrindo todas as tabelas sensíveis (fiscal, financeiro, cliente, usuário).
- Ambiente de **homologação fiscal** disponível por empresa antes da liberação em produção.

### 2.5 Painel de Gestão Fiscal (novo — 2026-07-18)

Tela dedicada à operação do dia a dia do módulo fiscal, complementando a emissão automática feita pelo checkout/PDV:

- **Relatório de documentos fiscais** — listagem com filtro por período e status (autorizada, cancelada, rejeitada, contingência);
- **Cancelamento de NFC-e** — dentro do prazo permitido pela SEFAZ, com justificativa obrigatória (mín. 15 caracteres), enviado de fato à SEFAZ (não é só uma marcação local);
- **Inutilização de numeração** — para faixas de número que nunca chegaram a ser usadas (ex.: falha no sistema no meio da emissão);
- **Reimpressão de cupom** — reconstrói o cupom NFC-e a partir dos dados já armazenados, sem precisar consultar a SEFAZ de novo;
- **Importar venda não fiscal → NFC-e** — para vendas registradas no PDV sem nota (ex.: em contingência) que precisam ser regularizadas depois;
- **Exportação** — pacote `.zip` com os XMLs do período e planilha `.csv` resumo para envio ao contador.

Validado com emissão e cancelamento reais na SEFAZ-SP (homologação). Pendência: falta tela de login do sistema interno para proteger o acesso (ver changelog técnico acima).

## 3. Arquitetura da Solução

A arquitetura em camadas do v1 se mantém — API central concentrando as regras de negócio, consumida pela loja pública, sistema interno (PDV/dashboard) e painel Super Admin. **A camada de API passa de Node.js/TypeScript para PHP/Laravel.**

### 3.1 Multi-empresa (multi-tenant) — sem alteração conceitual
Base única + isolamento por linha (Row-Level Security do PostgreSQL), com campo `empresa_id` em toda tabela de negócio.

### 3.5 Controle de vagas — implementação definida (novo)

Para impedir a venda da mesma vaga duas vezes (loja pública x PDV, ou duas compras simultâneas na loja pública), a função central de reserva segue este fluxo, dentro de uma única transação de banco:

1. Abre transação.
2. `SELECT vagas_total, vagas_reservadas FROM agenda_visitacao WHERE id = :id FOR UPDATE` — trava a linha até o fim da transação, bloqueando qualquer outra tentativa concorrente de reservar a mesma data/horário.
3. Verifica se `vagas_reservadas + quantidade <= vagas_total`.
4. Se houver saldo: insere `reserva_temporaria` (com `expira_em`, padrão 15 minutos) e incrementa `vagas_reservadas`.
5. Se não houver saldo: rejeita a reserva (retorna "vaga esgotada").
6. Commit.

Um job agendado (cron a cada minuto) varre e libera reservas expiradas que não foram convertidas em venda, devolvendo a vaga ao saldo disponível.

Essa função é chamada por um único ponto de entrada na API, reutilizado tanto pelo checkout da loja pública quanto pela tela de venda do PDV — nenhum dos dois canais implementa essa lógica separadamente.

### 3.6 Reforço de isolamento multi-tenant — implementação definida (novo)

Além do RLS nativo do PostgreSQL, a API implementa um **middleware único** (Laravel) executado em toda requisição autenticada (sistema interno) ou identificada por slug de empresa (loja pública):

```sql
SET LOCAL app.current_empresa_id = :empresa_id;
```

Esse comando é executado no início de cada transação de banco, antes de qualquer query de negócio. As policies de RLS no PostgreSQL usam esse valor de sessão:

```sql
CREATE POLICY empresa_isolation ON <tabela>
  USING (empresa_id = current_setting('app.current_empresa_id')::int);
```

**Regra de implementação:** nenhuma query de negócio pode usar conexão paralela/raw sem passar por esse middleware. Para garantir isso, o projeto terá **testes automatizados no CI** que tentam deliberadamente ler dados de uma empresa a partir do contexto de outra e esperam erro ou resultado vazio — rodando desde o primeiro módulo entregue, não como item de "qualidade" posterior.

## 4. Banco de Dados

*(estrutura de tabelas do v1 mantida — ver seção 4 do documento original: `empresa`, `plano/assinatura`, módulo fiscal completo, núcleo de agendamento, demais tabelas de negócio.)*

**Alterações no modelo de dados:**

`cliente` — novo campo:

| Campo | Descrição |
|---|---|
| `consentimento_lgpd` | Booleano — se o cliente aceitou o termo |
| `consentimento_lgpd_data` | Timestamp do aceite |
| `consentimento_lgpd_versao` | Versão do termo aceito no momento |

`config_fiscal` — reforço do campo já previsto:

| Campo | Descrição |
|---|---|
| `ambiente_ativo` | Produção / homologação — define em qual ambiente a empresa está operando no momento |

`log` (auditoria) — estrutura mantida do v1, mas com regra de implementação explícita: aplicado via observer genérico do Laravel em todos os models sensíveis (`documento_fiscal`, `documento_fiscal_item`, `numeracao_inutilizada`, `conta_pagar`, `conta_receber`, `certificado_digital`, `config_fiscal`, `cliente`, `usuario`, `venda`), cobrindo `create`, `update` e `delete` — não implementado manualmente módulo a módulo.

`empresa` — novos campos de endereço fiscal (obrigatórios no XML de NFe/NFC-e, ausentes na v1):

| Campo | Descrição |
|---|---|
| `uf` / `municipio` / `codigo_ibge_municipio` | Localização fiscal do emitente |
| `cep` / `logradouro` / `numero` / `bairro` / `complemento` | Endereço completo do emitente |

`numeracao_inutilizada` — nova tabela, registra faixas de numeração de NFe/NFC-e formalmente inutilizadas junto à SEFAZ:

| Campo | Descrição |
|---|---|
| `empresa_id` / `modelo` / `serie` | Escopo da inutilização |
| `numero_inicial` / `numero_final` | Faixa inutilizada |
| `justificativa` | Motivo (mín. 15 caracteres, exigido pela SEFAZ) |
| `status` / `protocolo` / `motivo` | Retorno da SEFAZ (homologada/rejeitada) |

`grupos` / `plano_contas` / `bancos` / `grava_banco` / `caixas` — novas tabelas do módulo financeiro expandido (2026-07-21):

| Tabela | Campos principais |
|---|---|
| `grupos` | `empresa_id`, `nome`, `descricao`, `ativo` — `produtos.grupo_id` (nullable) referencia |
| `plano_contas` | `empresa_id`, `codigo`, `nome`, `tipo` (receita/despesa) — `contas_pagar.plano_conta_id` e `contas_receber.plano_conta_id` (nullable) referenciam |
| `bancos` | `empresa_id`, `nome`, `codigo_banco`, `agencia`, `numero_conta`, `tipo_conta`, `saldo_inicial`, `ativo` |
| `grava_banco` | `empresa_id`, `banco_id`, `conta_pagar_id`/`conta_receber_id` (nullable), `data_movimento`, `tipo` (crédito/débito), `valor`, `descricao`, `origem` (manual/conta_pagar/conta_receber) |
| `caixas` | `empresa_id`, `usuario_id`, `tipo` (abertura/fechamento/sangria/suprimento), `valor`, `data_hora`, `observacao` |

`contas_pagar` / `contas_receber` — novos campos: `plano_conta_id` e `banco_id` (ambos nullable).

## 5. Telas do Sistema

*(sem alteração — protótipos de referência do v1 seguem válidos: loja pública, PDV, dashboard administrativo.)*

## 6. Ferramentas e Tecnologias (atualizado)

| Camada | Tecnologia | Finalidade |
|---|---|---|
| Banco de dados | PostgreSQL + Row-Level Security | Base única multi-tenant, isolamento entre empresas |
| **API Central** | **PHP 8 + Laravel** | Regras de negócio, controle de vagas, comissões, contexto multi-empresa |
| Loja pública | Framework web moderno (front-end), consumindo a API Laravel | Página de venda/agendamento por empresa, SEO, performance |
| Sistema interno / PDV | Aplicação web (SPA consumindo a API Laravel) | Login único, dashboard e frente de caixa em navegador/tablet |
| **Emissão fiscal — Fase 1** | **NFePHP (sped-nfe)** — biblioteca open-source madura para NFe/NFC-e | Sem custo por nota, mantida pela equipe |
| Emissão fiscal — Fase 2 (futuro) | API fiscal paga (Focus NFe / PlugNotas / eNotas) | Substitui a Fase 1 quando o volume justificar, sem redesenho |
| Pagamento online (checkout) | Pix / Cartão (Mercado Pago, PagSeguro ou Cielo) | Compra do consumidor final na loja pública |
| Cobrança de assinatura | Asaas / Vindi / Iugu | Mensalidade recorrente cobrada de cada empresa cliente |
| Notificações | WhatsApp (Z-API) | Confirmação e lembrete de visita/agendamento |
| Filas / jobs | Laravel Queue (database ou Redis) | Liberação de reservas expiradas, envio assíncrono de notificações, outbox de webhooks |
| Hospedagem | VPS / banco gerenciado em nuvem | Disponibilidade e backup |

## 7. Investimento Estimado

*(mantém-se a faixa e a lógica de fases do v1 — ver seção 7 do documento original. A troca de stack não altera significativamente a estimativa; a maturidade do NFePHP em relação a alternativas Node tende a **reduzir o risco de estouro de prazo** na Fase 1, especificamente no módulo fiscal.)*

## 8. Sugestões de Evolução Futura

*(lista do v1 mantida, com os itens de LGPD e homologação promovidos para escopo obrigatório da Fase 1 — ver changelog no topo deste documento.)*

## 9. Próximos Passos

- ~~Estruturação do projeto Laravel~~ — feito;
- ~~Módulo fiscal validado com a SEFAZ real~~ — feito;
- ~~Login do sistema interno~~ — feito;
- ~~Painel Super Admin~~ — feito, incluindo cobrança automática de assinatura (ver item 21 do changelog técnico);
- ~~PDV (frente de caixa)~~ — feito;
- ~~Dashboard administrativo~~ — feito;
- ~~NFe modelo 55 + NCM/CFOP por produto + importar NFC-e→NFe (CFOP 5929/6929)~~ — feito;
- ~~Identidade visual (logo, cores) no sistema interno~~ — feito;
- ~~Cadastros expandidos (produto, cliente, fornecedor) + emitente/certificado digital no dashboard~~ — feito;
- ~~Pagamento online real na loja pública (Pix + cartão, PDV com forma de pagamento)~~ — feito (cartão depende do front-end de loja pública, que é um projeto à parte, integrar o SDK do gateway e enviar o token);
- ~~Integração de cobrança de assinatura (Asaas) no painel Super Admin, com suspensão automática por inadimplência e baixa manual~~ — feito;
- ~~Notificações via WhatsApp (confirmação/lembrete de visita, Z-API e Baileys)~~ — feito, incluindo o microserviço `whatsapp-service/` do Baileys e o pareamento por QR code no dashboard (falta validar o pareamento com um número real do cliente e colocar o serviço rodando em produção);
- Identidade visual por empresa na loja pública (logo/cor própria de cada cliente da plataforma) - hoje só a plataforma tem marca própria, a loja pública ainda não é personalizável por tenant.
