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

**Achado técnico relevante (2026-07-18):** o RLS por si só criava um paradoxo na autenticação — para descobrir a empresa de um usuário é preciso *ler* a tabela `users` por e-mail, mas o RLS bloqueia essa leitura até o tenant estar definido, e o tenant só se define depois de autenticar. Resolvido com um middleware global (`BootstrapAuthDatabaseContext`) que abre um bypass de RLS só na fase de resolução de autenticação (primeiro middleware do grupo `web`), fechado de volta ao escopo correto pelo `SetTenantContext` antes de qualquer query de negócio rodar. Esse bug só apareceu em teste manual via navegador/curl — os testes automatizados usavam `actingAs()`, que contorna a resolução real de sessão e mascarou o problema. Registrado aqui como lição: **testes automatizados com `actingAs()` não substituem um teste manual do fluxo de login real**.

**Pendências conhecidas, registradas no código (`TODO`) e aqui:**
- NCM/CFOP por produto ainda são valores fixos genéricos — falta campo próprio no cadastro de produto;
- Emissão cobre hoje só NFC-e (modelo 65); NFe (modelo 55, com destinatário completo) não implementada;
- Só testado com Simples Nacional (CRT=1); outros regimes tributários não cobertos;
- Tabela de `cClassTrib` do IBS/CBS usa o código padrão (000001) — precisa revisão quando a SEFAZ consolidar a tabela definitiva por segmento;
- Painel do Super Admin (gestão de empresas/planos/faturamento, ver seção 2.2) ainda não implementado — usuário `super_admin` autentica mas não tem para onde ir;
- Sem recuperação de senha ("esqueci minha senha") nem página de erro 403 dedicada para usuário inativo/empresa suspensa (hoje volta pro login com mensagem).

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
- **Painel Super Admin (prioridade)** — hoje um usuário `super_admin` consegue logar mas não tem painel para gerir empresas/planos/faturamento;
- Definição da identidade visual (logo, cores, fotos) para aplicar aos protótipos;
- NCM/CFOP por produto no cadastro (hoje fixo/genérico no módulo fiscal);
- NFe modelo 55 (com destinatário completo) — hoje só NFC-e está implementada;
- Desenvolvimento incremental dos módulos restantes (PDV, dashboard administrativo, painel Super Admin), com validação a cada entrega.
