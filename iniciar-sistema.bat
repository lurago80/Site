@echo off
chcp 65001 >nul
echo ================================================
echo  Iniciando o sistema (SaaS Multi-Empresa)
echo ================================================
echo.
echo Pre-requisito: o PostgreSQL precisa estar rodando.
echo Ele nao e iniciado por este script.
echo.

set BASE=%~dp0

REM ---- API Laravel (obrigatorio) ----
echo [1/3] Abrindo API Laravel - http://localhost:8000
start "API Laravel (porta 8000)" cmd /k "cd /d %BASE%api && php artisan serve"

REM ---- Loja publica Next.js (opcional) ----
if not exist "%BASE%loja-publica\.env.local" goto criarenvloja
echo [2/3] .env.local da loja publica ja existe.
goto verificarnodeloja

:criarenvloja
echo [2/3] Criando loja-publica\.env.local a partir do .env.example...
copy "%BASE%loja-publica\.env.example" "%BASE%loja-publica\.env.local" >nul

:verificarnodeloja
if not exist "%BASE%loja-publica\node_modules" goto semnodeloja
echo       Abrindo loja publica - http://localhost:3000
start "Loja publica - Next.js (porta 3000)" cmd /k "cd /d %BASE%loja-publica && npm run dev"
goto whatsapp

:semnodeloja
echo       AVISO: loja-publica\node_modules nao encontrado.
echo       Rode npm install dentro de loja-publica antes de usar este script.

REM ---- whatsapp-service (opcional) ----
:whatsapp
if not exist "%BASE%whatsapp-service\.env" goto semenvwa
if not exist "%BASE%whatsapp-service\node_modules" goto semnodewa
echo [3/3] Abrindo whatsapp-service...
start "whatsapp-service - Baileys (porta 3300)" cmd /k "cd /d %BASE%whatsapp-service && npm start"
goto fim

:semnodewa
echo [3/3] AVISO: whatsapp-service\node_modules nao encontrado - pulei esse servico.
echo       Rode npm install dentro de whatsapp-service se quiser testar o WhatsApp.
goto fim

:semenvwa
echo [3/3] whatsapp-service\.env nao configurado - pulei esse servico (nao obrigatorio).

:fim
echo.
echo ================================================
echo  Enderecos:
echo   API / Dashboard / PDV / Fiscal / SuperAdmin: http://localhost:8000
echo   Login:                                       http://localhost:8000/login
echo   Loja publica (se abriu):                     http://localhost:3000/cervejaria-teste
echo ================================================
echo.
echo Cada servico abriu em uma janela propria - feche as janelas
echo ou aperte Ctrl+C dentro delas para encerrar quando terminar.
echo.
pause
