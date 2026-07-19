// Configuração do PM2 (gerenciador de processos Node.js) para rodar o
// whatsapp-service continuamente em produção, com reinício automático
// em caso de crash.
//
// Uso:
//   npm install -g pm2
//   pm2 start ecosystem.config.js
//   pm2 save && pm2 startup   # garante que reinicia junto com o servidor
//   pm2 logs whatsapp-service # acompanhar logs
//   pm2 restart whatsapp-service
module.exports = {
    apps: [
        {
            name: 'whatsapp-service',
            script: 'src/index.js',
            cwd: __dirname,
            instances: 1, // não rodar mais de uma instância - a sessão do WhatsApp é local ao processo
            exec_mode: 'fork',
            autorestart: true,
            watch: false,
            max_memory_restart: '300M',
            env: {
                NODE_ENV: 'production',
            },
            error_file: './logs/error.log',
            out_file: './logs/output.log',
            time: true,
        },
    ],
};
