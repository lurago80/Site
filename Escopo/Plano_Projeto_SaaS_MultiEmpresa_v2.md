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

## 1. Resumo Executivo

*(mantém-se o conteúdo original do documento v1 — plataforma multi-empresa com venda direta e/ou agendamento de experiências, três frentes integradas: loja pública, sistema interno e módulo fiscal, sobre base multi-tenant.)*

O maior desafio técnico do projeto continua sendo duplo: (1) impedir overbooking na venda de vagas/visitas, feita simultaneamente pela loja pública e pelo caixa físico, e (2) garantir isolamento total dos dados entre empresas. Nesta versão, ambas as soluções foram detalhadas a nível de implementação (seções 3.5 e 3.6).

## 2. Escopo do Projeto

*(sem alterações em relação ao v1 — ver seções 2.1 a 2.4 do documento original: página pública por empresa, sistema interno com login único, módulo fiscal NFe/NFC-e, planos Básico e Completo.)*

**Adição ao escopo da Fase 1:**
- Captura de **consentimento LGPD** no cadastro do cliente (loja pública e PDV), com timestamp e versão do termo aceito.
- **Log de auditoria** ativo desde o primeiro módulo entregue, cobrindo todas as tabelas sensíveis (fiscal, financeiro, cliente, usuário).
- Ambiente de **homologação fiscal** disponível por empresa antes da liberação em produção.

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

`log` (auditoria) — estrutura mantida do v1, mas com regra de implementação explícita: aplicado via observer/trait genérico do Laravel em todos os models sensíveis (`documento_fiscal`, `conta_pagar`, `conta_receber`, `cliente`, `usuario`, `venda`), cobrindo `create`, `update`, `delete` e leitura de dados fiscais/financeiros sensíveis — não implementado manualmente módulo a módulo.

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

- Validação deste documento v2 com o cliente;
- Definição da identidade visual (logo, cores, fotos) para aplicar aos protótipos;
- Estruturação do projeto Laravel: esqueleto da API, migrations do banco com RLS habilitado, middleware de contexto multi-tenant e testes automatizados de isolamento;
- Desenvolvimento incremental por módulo, com validação a cada entrega.
