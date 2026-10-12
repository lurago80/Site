<#
.SYNOPSIS
  Configura na PRODUCAO a copia diaria dos backups para o Google Drive (criptografada).

.DESCRIPTION
  Rode UMA vez, na maquina de desenvolvimento, depois de instalar o rclone e autorizar o Google Drive
  (veja o passo a passo no fim deste arquivo). O script:
   1. Gera a senha de criptografia (os arquivos vao cifrados para o Drive; sem a senha nao ha como abrir).
   2. Instala o rclone e a configuracao em C:\ProgramData\BackupNuvem (acesso so de Administradores/SYSTEM).
   3. Cria a tarefa agendada "BackupNuvem" (todo dia as 05:00, depois do espelho em F: das 04:00).
   4. Faz a primeira copia e confere que chegou no Drive.

  O que vai para o Drive: C:\Backups (pg_dump, storage-app, radar), C:\Backups_Site (backups de deploy)
  e F:\Backups_Espelho\config (.env e APP_KEY). Fica 45 dias; arquivos mais velhos sao apagados da nuvem.
  O rclone usa o escopo "drive.file": so enxerga o que ele mesmo criou, nunca o resto do seu Drive.

.NOTES
  Nao ha senha neste arquivo. O token do Google e pedido na hora e a senha de criptografia e mostrada uma vez no fim.
#>
[CmdletBinding()]
param(
    [string] $Usuario = 'TERMINAL02\Lurago',
    [string[]] $Hosts = @('192.168.0.23', '100.73.48.102'),
    [string] $PastaNuvem = 'BackupsProducao'
)

$ErrorActionPreference = 'Stop'

# --- 0) rclone local (para copiar para a producao e gerar a senha ofuscada)
$rcloneLocal = (Get-Command rclone -ErrorAction SilentlyContinue).Source
if (-not $rcloneLocal) { throw 'rclone nao encontrado. Instale com: winget install Rclone.Rclone  (depois abra um novo PowerShell).' }
Write-Host "rclone local: $rcloneLocal" -ForegroundColor Cyan

# --- 1) Token do Google Drive (gerado por: rclone authorize "drive" "eyJzY29wZSI6ImRyaXZlLmZpbGUifQ")
#     Aceita os dois formatos que o rclone entrega: JSON puro ({"access_token":...}) ou o texto codificado (eyJ...).
Write-Host "`nCole o codigo gerado pelo comando 'rclone authorize' (o texto entre 'Paste the following...' e 'End paste'):"
$entrada = (Read-Host 'Token').Trim()
if (-not $entrada.StartsWith('{')) {
    try { $entrada = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($entrada)) }
    catch { throw 'Texto nao reconhecido: cole o codigo inteiro gerado pelo rclone authorize.' }
}
$obj = $entrada | ConvertFrom-Json
# Formato novo: {"client_id":"","client_secret":"","token":"{...}"} - o token verdadeiro e o campo "token" (texto).
$token = if ($obj.PSObject.Properties.Name -contains 'token') { [string]$obj.token } else { $entrada }
if ($token -notmatch '"access_token"' -or $token -notmatch '"refresh_token"') { throw 'Token invalido: nao tem access_token/refresh_token. Rode o rclone authorize de novo.' }

# --- 2) Senha de criptografia (gerada aqui; mostrada uma vez no fim)
function Nova-Senha([int]$tamanho) {
    $bytes = New-Object byte[] $tamanho
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $alfabeto = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    -join ($bytes | ForEach-Object { $alfabeto[$_ % $alfabeto.Length] })
}
$senhaCripto = Nova-Senha 32
$salCripto = Nova-Senha 32
$senhaOfuscada = (& $rcloneLocal obscure $senhaCripto).Trim()
$salOfuscado = (& $rcloneLocal obscure $salCripto).Trim()

$conf = @"
[gdrive]
type = drive
scope = drive.file
token = $token

[gdrive-crypt]
type = crypt
remote = gdrive:$PastaNuvem
password = $senhaOfuscada
password2 = $salOfuscado
filename_encryption = standard
directory_name_encryption = true
"@

# --- 3) Script que roda todo dia na producao
$scriptCopia = @'
$ErrorActionPreference = 'Continue'
$dir = 'C:\ProgramData\BackupNuvem'
$rc = Join-Path $dir 'rclone.exe'
$conf = Join-Path $dir 'rclone.conf'
$log = Join-Path $dir 'copia-nuvem.log'
$falhou = $false

"$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ===== inicio" | Add-Content $log

$origens = @(
    @{ De = 'C:\Backups';                Para = 'gdrive-crypt:copia/Backups' },
    @{ De = 'C:\Backups_Site';           Para = 'gdrive-crypt:copia/Backups_Site' },
    @{ De = 'F:\Backups_Espelho\config'; Para = 'gdrive-crypt:copia/config' }
)
foreach ($o in $origens) {
    if (-not (Test-Path $o.De)) { "$(Get-Date -Format 'HH:mm:ss') pulando (nao existe): $($o.De)" | Add-Content $log; continue }
    & $rc copy $o.De $o.Para --config $conf --transfers 2 --log-file $log --log-level INFO --exclude '*.tmp'
    if ($LASTEXITCODE -ne 0) { $falhou = $true }
}

# Retencao na nuvem: 45 dias
& $rc delete 'gdrive-crypt:copia' --min-age 45d --config $conf --log-file $log --log-level INFO
if ($LASTEXITCODE -ne 0) { $falhou = $true }

"$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ===== fim " + $(if ($falhou) { 'COM FALHA' } else { 'ok' }) | Add-Content $log
if ($falhou) { exit 1 }
'@

# --- 4) Conexao com a producao
$alvo = $null
foreach ($h in $Hosts) {
    if ((Test-NetConnection $h -Port 5985 -WarningAction SilentlyContinue).TcpTestSucceeded) { $alvo = $h; break }
}
if (-not $alvo) { throw "Producao nao responde em $($Hosts -join ', ')." }
Write-Host "Producao alcancada por: $alvo" -ForegroundColor Cyan

$cred = Get-Credential -UserName $Usuario -Message "Senha do Windows da producao ($alvo)"
$sess = New-PSSession -ComputerName $alvo -Credential $cred -Authentication Negotiate

try {
    Invoke-Command -Session $sess { New-Item -ItemType Directory -Force 'C:\ProgramData\BackupNuvem' | Out-Null }
    Copy-Item -ToSession $sess -Path $rcloneLocal -Destination 'C:\ProgramData\BackupNuvem\rclone.exe' -Force

    Invoke-Command -Session $sess -ArgumentList $conf, $scriptCopia {
        param($conf, $scriptCopia)
        $dir = 'C:\ProgramData\BackupNuvem'
        [IO.File]::WriteAllText("$dir\rclone.conf", $conf, (New-Object Text.UTF8Encoding($false)))
        [IO.File]::WriteAllText("$dir\copiar-para-nuvem.ps1", $scriptCopia, (New-Object Text.UTF8Encoding($true)))

        # Pasta so de Administradores e SYSTEM (a configuracao guarda o acesso ao Drive)
        # SIDs (e nao nomes): o grupo se chama "Administradores" em Windows em portugues.
        & icacls $dir /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Nao consegui proteger a pasta $dir (icacls saiu com $LASTEXITCODE)." }

        $acao = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$dir\copiar-para-nuvem.ps1`""
        $gatilho = New-ScheduledTaskTrigger -Daily -At '05:00'
        $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
        Register-ScheduledTask -TaskName 'BackupNuvem' -Action $acao -Trigger $gatilho -Principal $principal -Force | Out-Null
        'Tarefa BackupNuvem criada (todo dia as 05:00).'
    }

    # --- 5) Primeira copia agora, e conferencia de que chegou no Drive
    Write-Host "`nFazendo a primeira copia (pode levar alguns minutos)..." -ForegroundColor Yellow
    Invoke-Command -Session $sess {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File 'C:\ProgramData\BackupNuvem\copiar-para-nuvem.ps1'
        "Codigo de saida: $LASTEXITCODE"
        Get-Content 'C:\ProgramData\BackupNuvem\copia-nuvem.log' -Tail 6
        & 'C:\ProgramData\BackupNuvem\rclone.exe' size 'gdrive-crypt:copia' --config 'C:\ProgramData\BackupNuvem\rclone.conf'
    }
}
finally {
    Remove-PSSession $sess
}

Write-Host "`n================ ANOTE AGORA (aparece uma vez so) ================" -ForegroundColor Red
Write-Host "Senha de criptografia: $senhaCripto"
Write-Host "Salt (senha 2)       : $salCripto"
Write-Host "Guarde as DUAS em local seguro FORA do servidor (gerenciador de senhas)." -ForegroundColor Red
Write-Host "Sem elas, os backups no Drive nao podem ser abertos se o servidor for perdido." -ForegroundColor Red
Write-Host "==================================================================`n" -ForegroundColor Red

<#
PASSO A PASSO ANTES DE RODAR (na maquina de desenvolvimento):

  1) winget install Rclone.Rclone          # depois feche e abra o PowerShell
  2) rclone authorize "drive" "eyJzY29wZSI6ImRyaXZlLmZpbGUifQ"
     -> abre o navegador: entre na conta Google do backup e clique em Permitir.
     -> no PowerShell aparece um JSON entre "Paste the following into your remote machine --->"
        e "<---End paste". Copie o JSON inteiro (uma linha, de { ate }).
  3) .\configurar-backup-nuvem.ps1         # cole o JSON quando pedir

COMO RESTAURAR (em qualquer computador com rclone):
  - Crie o mesmo remote "gdrive" (rclone authorize) e o "gdrive-crypt" com a MESMA senha e salt
    (rclone config -> crypt, remote = gdrive:BackupsProducao).
  - rclone ls gdrive-crypt:copia
  - rclone copy gdrive-crypt:copia/Backups/postgres-saas C:\Restaurar\
#>
