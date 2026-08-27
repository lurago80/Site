# Backup automático diário do PostgreSQL do SaaS Multi-Empresa (TERMINAL02).
#
# Roda como serviço Windows via NSSM (o Agendador de Tarefas está
# desabilitado nessa máquina) - por isso o script é um loop infinito que
# dorme até o próximo horário de backup em vez de rodar uma vez só.
#
# Usa o superusuário 'postgres' (não o role da aplicação 'saas_app'),
# porque as tabelas têm FORCE ROW LEVEL SECURITY - até o dono da tabela
# fica sujeito às policies, só um superuser do Postgres ignora RLS e
# consegue exportar TODAS as empresas de uma vez.

$ErrorActionPreference = "Stop"

$PgBin = "C:\Program Files\PostgreSQL\17\bin"
$PgHost = "127.0.0.1"
$PgPort = "5432"
$PgUser = "postgres"
$Banco = "saas_multiempresa"

# Senha NUNCA fica neste arquivo (ele é versionado no git) - vem da
# variável de ambiente do serviço Windows, configurada via NSSM
# (AppEnvironmentExtra), fora do controle de versão.
$PgSenha = $env:BACKUP_PG_PASSWORD
if ([string]::IsNullOrEmpty($PgSenha)) {
    throw "Variável de ambiente BACKUP_PG_PASSWORD não definida - configure no serviço NSSM antes de iniciar."
}

$PastaBackup = "C:\Backups\postgres-saas"
$DiasRetencao = 14
$HorarioBackup = "03:00"

if (-not (Test-Path $PastaBackup)) {
    New-Item -ItemType Directory -Force -Path $PastaBackup | Out-Null
}

function Fazer-Backup {
    $timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
    $arquivo = Join-Path $PastaBackup "$Banco`_$timestamp.backup"

    $env:PGPASSWORD = $PgSenha
    & "$PgBin\pg_dump.exe" -h $PgHost -p $PgPort -U $PgUser -d $Banco -F c -f $arquivo

    if ($LASTEXITCODE -eq 0 -and (Test-Path $arquivo) -and (Get-Item $arquivo).Length -gt 0) {
        Write-Output "$(Get-Date -Format s) OK: backup criado em $arquivo ($((Get-Item $arquivo).Length) bytes)"
    } else {
        Write-Output "$(Get-Date -Format s) ERRO: pg_dump falhou (exit code $LASTEXITCODE)"
        if (Test-Path $arquivo) { Remove-Item $arquivo -Force }
    }

    # Rotação: apaga backups mais velhos que $DiasRetencao dias.
    Get-ChildItem $PastaBackup -Filter "*.backup" |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$DiasRetencao) } |
        ForEach-Object {
            Write-Output "$(Get-Date -Format s) Removendo backup antigo: $($_.Name)"
            Remove-Item $_.FullName -Force
        }
}

Write-Output "$(Get-Date -Format s) Serviço de backup iniciado - horário diário: $HorarioBackup, retenção: $DiasRetencao dias, pasta: $PastaBackup"

while ($true) {
    $agora = Get-Date
    $proximaExecucao = Get-Date -Hour ([int]($HorarioBackup.Split(':')[0])) -Minute ([int]($HorarioBackup.Split(':')[1])) -Second 0

    if ($proximaExecucao -le $agora) {
        $proximaExecucao = $proximaExecucao.AddDays(1)
    }

    $segundosParaEsperar = ($proximaExecucao - $agora).TotalSeconds
    Write-Output "$(Get-Date -Format s) Próximo backup em $proximaExecucao (dormindo $([int]$segundosParaEsperar)s)"
    Start-Sleep -Seconds $segundosParaEsperar

    try {
        Fazer-Backup
    } catch {
        Write-Output "$(Get-Date -Format s) ERRO inesperado no backup: $($_.Exception.Message)"
    }
}
