const path = require('path');
const fs = require('fs');
const QRCode = require('qrcode');
const pino = require('pino');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');

const PASTA_SESSOES = path.join(__dirname, '..', 'sessoes');
const logger = pino({ level: 'silent' });

/**
 * Gerencia uma sessão do WhatsApp Web (via Baileys) por empresa. Cada
 * empresa tem sua própria pasta de credenciais em disco (sessoes/{id}),
 * então reiniciar o processo não derruba sessões já pareadas.
 *
 * Isso é o motivo de o Baileys precisar de um serviço à parte do
 * Laravel: a sessão é um socket WebSocket de longa duração (protocolo
 * do WhatsApp Web), não uma chamada HTTP request/response comum.
 */
class SessionManager {
    constructor() {
        /** @type {Map<string, { socket: any, status: string, qr: string|null }>} */
        this.sessoes = new Map();

        if (!fs.existsSync(PASTA_SESSOES)) {
            fs.mkdirSync(PASTA_SESSOES, { recursive: true });
        }
    }

    _pastaEmpresa(empresaId) {
        return path.join(PASTA_SESSOES, String(empresaId));
    }

    obterStatus(empresaId) {
        const sessao = this.sessoes.get(String(empresaId));

        if (!sessao) {
            return { status: 'desconectado', qr: null };
        }

        return { status: sessao.status, qr: sessao.qr };
    }

    async iniciar(empresaId) {
        const chave = String(empresaId);
        const existente = this.sessoes.get(chave);

        if (existente && existente.status === 'conectado') {
            return { status: 'conectado', qr: null };
        }

        if (existente && existente.status === 'aguardando_qr') {
            return { status: 'aguardando_qr', qr: existente.qr };
        }

        const { state, saveCreds } = await useMultiFileAuthState(this._pastaEmpresa(empresaId));
        const { version } = await fetchLatestBaileysVersion();

        const socket = makeWASocket({
            version,
            auth: state,
            logger,
            printQRInTerminal: false,
        });

        const registro = { socket, status: 'aguardando_qr', qr: null };
        this.sessoes.set(chave, registro);

        socket.ev.on('creds.update', saveCreds);

        socket.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                registro.qr = await QRCode.toDataURL(qr);
                registro.status = 'aguardando_qr';
            }

            if (connection === 'open') {
                registro.status = 'conectado';
                registro.qr = null;
            }

            if (connection === 'close') {
                const motivo = lastDisconnect?.error?.output?.statusCode;
                const deveReconectar = motivo !== DisconnectReason.loggedOut;

                registro.status = 'desconectado';
                this.sessoes.delete(chave);

                if (deveReconectar) {
                    // Queda de conexão (não foi logout manual) - tenta
                    // reabrir a sessão automaticamente.
                    this.iniciar(empresaId).catch(() => {});
                }
            }
        });

        // Dá um tempo curto para o QR code chegar antes de responder,
        // sem travar a requisição indefinidamente.
        await new Promise((resolve) => setTimeout(resolve, 3000));

        const atual = this.sessoes.get(chave);

        return atual ? { status: atual.status, qr: atual.qr } : { status: 'desconectado', qr: null };
    }

    async desconectar(empresaId) {
        const chave = String(empresaId);
        const sessao = this.sessoes.get(chave);

        if (sessao) {
            try {
                await sessao.socket.logout();
            } catch (erro) {
                // Sessão já pode estar fechada - segue para limpar o disco.
            }
            this.sessoes.delete(chave);
        }

        const pasta = this._pastaEmpresa(empresaId);
        if (fs.existsSync(pasta)) {
            fs.rmSync(pasta, { recursive: true, force: true });
        }

        return { status: 'desconectado' };
    }

    async enviarMensagem(empresaId, telefone, mensagem) {
        const sessao = this.sessoes.get(String(empresaId));

        if (!sessao || sessao.status !== 'conectado') {
            const erro = new Error('Sessão do WhatsApp não está conectada para esta empresa.');
            erro.codigo = 'SESSAO_DESCONECTADA';
            throw erro;
        }

        const numero = telefone.replace(/\D/g, '');
        const jid = `${numero}@s.whatsapp.net`;

        const resultado = await sessao.socket.sendMessage(jid, { text: mensagem });

        return { id: resultado?.key?.id ?? null };
    }
}

module.exports = new SessionManager();
