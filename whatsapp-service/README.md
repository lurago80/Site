# whatsapp-service

Microserviço Node.js que roda o [Baileys](https://github.com/WhiskeySockets/Baileys) (protocolo do WhatsApp Web) - provedor de WhatsApp **gratuito**, usado como alternativa ao Z-API (pago). Chamado internamente pelo backend Laravel (`App\Services\Notificacao\BaileysNotificacaoGateway`).

Cada empresa cliente da plataforma pode ter sua própria sessão (número de WhatsApp pareado via QR code), mantida em `sessoes/{empresa_id}/`.

## Por que um serviço separado?

O Baileys implementa o protocolo do WhatsApp Web por engenharia reversa - não existe biblioteca equivalente em PHP. A sessão também é uma conexão WebSocket de longa duração, não uma chamada HTTP request/response comum, então precisa de um processo próprio rodando continuamente (diferente do Z-API, que é uma API REST comum chamável direto do Laravel).

**Risco importante:** usar um número de WhatsApp comum de forma automatizada é contra os Termos de Uso do WhatsApp - o número pode ser banido a qualquer momento, sem aviso. Cada empresa cliente decide, por conta própria, se aceita esse risco (opção gratuita) ou prefere o Z-API (pago, API oficial).

## Rodando localmente

```bash
cd whatsapp-service
npm install
cp .env.example .env
# edite .env e defina um INTERNAL_TOKEN aleatório (o mesmo valor vai em BAILEYS_INTERNAL_TOKEN no .env do Laravel)
npm start
```

## Endpoints (todos exigem o header `x-internal-token`)

- `GET /empresas/:empresaId/status` — `{ status: 'desconectado'|'aguardando_qr'|'conectado', qr: string|null }`
- `POST /empresas/:empresaId/iniciar` — abre/retoma a sessão, retorna o QR code (data URL PNG) para parear
- `POST /empresas/:empresaId/desconectar` — faz logout e apaga a sessão salva em disco
- `POST /empresas/:empresaId/enviar` — `{ telefone, mensagem }` → envia uma mensagem de texto

## Produção

Precisa rodar como um processo de longa duração ao lado do Laravel (ex.: `pm2`, `systemd` ou um container à parte) - não é uma função serverless, a conexão WebSocket precisa ficar viva. A pasta `sessoes/` guarda as credenciais da sessão de cada empresa e **nunca deve ser versionada nem exposta publicamente** (equivalente, em sensibilidade, ao certificado digital A1 do módulo fiscal).
