require('dotenv').config();
const express = require('express');
const sessionManager = require('./sessionManager');

const app = express();
app.use(express.json());

const PORTA = process.env.PORT || 3300;
const TOKEN_INTERNO = process.env.INTERNAL_TOKEN;

if (!TOKEN_INTERNO) {
    console.error('INTERNAL_TOKEN não definido - defina no .env antes de iniciar o serviço.');
    process.exit(1);
}

/**
 * Só o backend Laravel deve conseguir chamar este serviço - ele fica
 * numa rede interna, mas mesmo assim exige um token compartilhado
 * simples (não é exposto à internet pública).
 */
app.use((req, res, next) => {
    if (req.headers['x-internal-token'] !== TOKEN_INTERNO) {
        return res.status(401).json({ erro: 'Token interno inválido.' });
    }
    next();
});

app.get('/empresas/:empresaId/status', (req, res) => {
    res.json(sessionManager.obterStatus(req.params.empresaId));
});

app.post('/empresas/:empresaId/iniciar', async (req, res) => {
    try {
        const resultado = await sessionManager.iniciar(req.params.empresaId);
        res.json(resultado);
    } catch (erro) {
        res.status(500).json({ erro: erro.message });
    }
});

app.post('/empresas/:empresaId/desconectar', async (req, res) => {
    try {
        const resultado = await sessionManager.desconectar(req.params.empresaId);
        res.json(resultado);
    } catch (erro) {
        res.status(500).json({ erro: erro.message });
    }
});

app.post('/empresas/:empresaId/enviar', async (req, res) => {
    const { telefone, mensagem } = req.body;

    if (!telefone || !mensagem) {
        return res.status(422).json({ erro: 'Campos "telefone" e "mensagem" são obrigatórios.' });
    }

    try {
        const resultado = await sessionManager.enviarMensagem(req.params.empresaId, telefone, mensagem);
        res.json({ status: 'enviado', ...resultado });
    } catch (erro) {
        res.status(erro.codigo === 'SESSAO_DESCONECTADA' ? 409 : 500).json({ erro: erro.message });
    }
});

app.listen(PORTA, () => {
    console.log(`Serviço WhatsApp (Baileys) rodando na porta ${PORTA}`);
});
