// pm2: pm2 start ecosystem.config.js && pm2 save
// Chama o script JS do Next (nao o next.cmd) e passa "start" + porta 3010 (usada pelo tunel Cloudflare).
module.exports = {
    apps: [
        {
            name: 'loja-publica',
            cwd: __dirname,
            script: 'node_modules/next/dist/bin/next',
            args: 'start -p 3010',
            env: { NODE_ENV: 'production' },
        },
    ],
};
