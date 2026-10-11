<#
.SYNOPSIS
  Sobe arquivos do desenvolvimento (C:\VS_Code\Site\api) para a produção, com conferência.

.DESCRIPTION
  1. Escolhe o caminho: rede local (192.168.0.23) ou Tailscale (100.73.48.102), o que responder.
  2. Abre uma sessão WinRM (pede a senha do Windows da produção; nada de senha neste arquivo).
  3. Com -Migrar: faz backup do banco antes (pede a senha do postgres).
  4. Copia os arquivos, confere a sintaxe dos .php lá, roda a migração (se -Migrar) e limpa o cache.

.EXAMPLE
  .\deploy-producao.ps1 -Arquivos 'app\Models\Venda.php','routes\web.php'

.EXAMPLE
  .\deploy-producao.ps1 -Migrar -Arquivos 'app\Models\Empresa.php','database\migrations\2026_10_10_100001_add_pdv_caixa_pode_cancelar_a_empresas.php'

.NOTES
  Caminhos de -Arquivos são relativos a C:\VS_Code\Site\api. Remove-Item na produção é bloqueado
  de propósito: este script só copia e substitui, nunca apaga.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)] [string[]] $Arquivos,
    [switch] $Migrar,
    [string] $Usuario = 'Lurago',
    [string] $Origem = 'C:\VS_Code\Site\api',
    [string] $Destino = 'C:\VS_Code\Site\api',
    [string] $PhpRemoto = 'C:\PHP84\php.exe',
    [string[]] $Hosts = @('192.168.0.23', '100.73.48.102')   # rede local, depois Tailscale (TERMINAL02)
)

$ErrorActionPreference = 'Stop'

# --- 0) Confere que os arquivos existem aqui
$faltando = $Arquivos | Where-Object { -not (Test-Path (Join-Path $Origem $_)) }
if ($faltando) { throw "Arquivo(s) não encontrado(s) em ${Origem}: $($faltando -join ', ')" }

# --- 1) Escolhe o caminho que responde (WinRM 5985)
$alvo = $null
foreach ($h in $Hosts) {
    if ((Test-NetConnection $h -Port 5985 -WarningAction SilentlyContinue).TcpTestSucceeded) { $alvo = $h; break }
}
if (-not $alvo) { throw "Nenhum caminho responde na porta 5985: $($Hosts -join ', '). Confira rede/Tailscale." }
Write-Host "Produção alcançada por: $alvo" -ForegroundColor Cyan

# --- 2) Sessão (para IP, o WinRM exige o host em TrustedHosts: PowerShell como administrador, uma vez)
$cred = Get-Credential -UserName $Usuario -Message "Senha do Windows da produção ($alvo)"
$sess = New-PSSession -ComputerName $alvo -Credential $cred -Authentication Negotiate

try {
    $nome = Invoke-Command -Session $sess { $env:COMPUTERNAME }
    Write-Host "Conectado em $nome." -ForegroundColor Cyan

    # --- 3) Backup antes de migrar
    if ($Migrar) {
        $pg = Read-Host 'Senha do postgres da produção' -AsSecureString
        $pgPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($pg))
        $dump = Invoke-Command -Session $sess -ArgumentList $pgPlain {
            param($pw)
            $env:PGPASSWORD = $pw
            $f = "C:\Backups_Site\prod_antes_deploy_$(Get-Date -Format yyyyMMdd_HHmm).dump"
            New-Item -ItemType Directory -Force C:\Backups_Site | Out-Null
            & 'C:\Program Files\PostgreSQL\17\bin\pg_dump.exe' -h 127.0.0.1 -p 5432 -U postgres -Fc -f $f saas_multiempresa
            Get-Item $f | Select-Object -ExpandProperty Length
            $f
        }
        if ($dump[0] -le 0) { throw "Backup vazio em $($dump[1]); deploy cancelado." }
        Write-Host ("Backup: {0} ({1:N0} KB)" -f $dump[1], ($dump[0] / 1KB)) -ForegroundColor Green
    }

    # --- 4) Copia (cria a pasta de destino se for arquivo novo)
    foreach ($f in $Arquivos) {
        $remoto = Join-Path $Destino $f
        Invoke-Command -Session $sess -ArgumentList (Split-Path $remoto) { param($d) New-Item -ItemType Directory -Force $d | Out-Null }
        Copy-Item -ToSession $sess -Path (Join-Path $Origem $f) -Destination $remoto -Force
        Write-Host "  copiado: $f"
    }

    # --- 5) Sintaxe, migração, cache
    Invoke-Command -Session $sess -ArgumentList $Destino, $PhpRemoto, $Arquivos, $Migrar.IsPresent {
        param($dest, $php, $arqs, $migrar)
        Set-Location $dest
        foreach ($f in $arqs | Where-Object { $_ -like '*.php' -and $_ -notlike '*.blade.php' }) {
            $r = & $php -l $f 2>&1
            if ($LASTEXITCODE -ne 0) { throw "Erro de sintaxe em ${f}: $r" }
        }
        if ($migrar) { & $php artisan migrate --force }
        & $php artisan optimize:clear | Select-Object -Last 1
    }

    Write-Host 'Deploy concluído.' -ForegroundColor Green
}
finally {
    Remove-PSSession $sess
}
