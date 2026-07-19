# loja-publica

Front-end da loja pública (venda direta + agendamento) do SaaS multi-empresa - a página que o **consumidor final** vê para comprar produtos ou agendar uma visita. Um único front-end atende todas as empresas clientes da plataforma, via rota dinâmica `/[empresa]` (o slug da empresa).

Consome só a API já pronta do backend Laravel (`api/routes/api.php`, prefixo `loja/{empresa}`) - nenhuma regra de negócio mora aqui, é só apresentação e a tokenização de cartão (que precisa acontecer no navegador do cliente por exigência do PCI-DSS).

## Stack

Next.js 16 (App Router) + React 19 + TypeScript, sem CSS framework (CSS simples, mesma filosofia do resto do sistema). Next.js foi escolhido pela renderização no servidor (melhor SEO/performance para uma loja pública) e por já ser o que a maioria dos devs conhece.

## Rodando localmente

```bash
cd loja-publica
npm install
cp .env.example .env.local
# ajuste NEXT_PUBLIC_API_URL se a API Laravel não estiver em localhost:8000
npm run dev
```

Depois acesse `http://localhost:3000/{slug-de-uma-empresa}` - o slug é o mesmo usado no backend (ex.: `cervejaria-teste`).

## Estrutura

```
src/
  app/
    page.tsx                    - página raiz (orientação simples, a loja de verdade vive em /[empresa])
    [empresa]/
      layout.tsx                - busca dados da empresa (nome/logo/cor), monta o CarrinhoProvider e o Header
      page.tsx                  - catálogo (produtos + agenda de visitação)
      carrinho/page.tsx
      checkout/page.tsx         - dados do cliente, LGPD, Pix (QR real) ou cartão (Mercado Pago Bricks)
  components/
    Header.tsx, Catalogo.tsx, CardBrick.tsx
  lib/
    api.ts                      - cliente HTTP fino para a API Laravel
    cart.tsx                    - carrinho em Context + localStorage (por slug de empresa)
    types.ts
```

## Identidade visual por empresa

`layout.tsx` de `[empresa]` busca `GET /loja/{empresa}/info` (logo, cor primária, razão social) e define a variável CSS `--cor-primaria` no elemento raiz - o admin de cada empresa configura isso no Dashboard (seção "Configuração Fiscal" → card "Identidade visual da loja pública").

## Pagamento

- **Pix**: sempre disponível, usa o `PagamentoService` já existente no backend (gateway real se a empresa configurou, ou simulado caso contrário). O QR code retornado (`cobranca.qr_code_base64`) é exibido direto na tela de confirmação.
- **Cartão**: só aparece se a empresa tiver o Mercado Pago configurado e ativo (`GET /loja/{empresa}/config-pagamento-publica` retorna a `public_key`). Usa o [Card Payment Brick](https://www.mercadopago.com.br/developers/pt/docs/checkout-bricks/card-payment-brick/introduction) do Mercado Pago para tokenizar o cartão **no navegador do cliente** - o número do cartão nunca passa pelo nosso backend. Sem gateway configurado, a opção de cartão fica desabilitada com uma mensagem explicando, e a loja continua funcionando normalmente com Pix.

## O que falta (para produção real)

- Validar a tokenização de cartão de ponta a ponta com credenciais reais de sandbox do Mercado Pago (não temos acesso a isso neste ambiente de desenvolvimento).
- Deploy (Vercel é o caminho mais simples para um projeto Next.js) e configurar `NEXT_PUBLIC_API_URL` apontando para a API em produção.
- SEO fino por empresa (meta tags dinâmicas com nome/descrição de cada uma) - hoje o `<title>`/`<meta>` são genéricos.
