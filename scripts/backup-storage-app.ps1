# Backup automático diário dos arquivos da aplicação (TERMINAL02) - a
# pasta api/storage/app guarda certificados digitais (.pfx) e documentos
# privados de cada empresa, nada disso está no git (é conteúdo sensível
# e/ou gerado em produção).
#
# Roda como serviço Windows via NSSM (o Agendador de Tarefas está
# desabilitado nessa máquina) - por isso o script é um loop infinito que
# dorme até o próximo horário de backup em vez de rodar uma vez só.
# Mesmo padrão do scripts/backup-postgres-saas.ps1, só que compactando
# uma pasta em vez de rodar pg_dump.

$ErrorActionPreference = "Stop"

$PastaOrigem = "C:\VS_Code\Site\api\storage\app"
$PastaBackup = "C:\Backups\storage-app"
# Cópia extra num compartilhamento à parte - ainda é a mesma máquina/
# disco físico (não é offsite de verdade: não protege contra a máquina
# inteira falhar, ser roubada ou pegar fogo), mas isola de apagar ou
# corromper a pasta principal por acidente. $null = pula essa etapa.
$PastaBackupExtra = "C:\Backup_Site\storage-app"
$DiasRetencao = 14
$HorarioBackup = "03:15"

if (-not (Test-Path $PastaBackup)) {
    New-Item -ItemType Directory -Force -Path $PastaBackup | Out-Null
}
if ($PastaBackupExtra -and -not (Test-Path $PastaBackupExtra)) {
    New-Item -ItemType Directory -Force -Path $PastaBackupExtra | Out-Null
}

function Fazer-Backup {
    $timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
    $arquivo = Join-Path $PastaBackup "storage-app_$timestamp.zip"

    Compress-Archive -Path "$PastaOrigem\*" -DestinationPath $arquivo -CompressionLevel Optimal

    if ((Test-Path $arquivo) -and (Get-Item $arquivo).Length -gt 0) {
        Write-Output "$(Get-Date -Format s) OK: backup criado em $arquivo ($((Get-Item $arquivo).Length) bytes)"

        if ($PastaBackupExtra) {
            try {
                Copy-Item $arquivo -Destination $PastaBackupExtra -Force
                Write-Output "$(Get-Date -Format s) OK: cópia extra em $PastaBackupExtra"
            } catch {
                Write-Output "$(Get-Date -Format s) ERRO ao copiar para $PastaBackupExtra`: $($_.Exception.Message)"
            }
        }
    } else {
        Write-Output "$(Get-Date -Format s) ERRO: Compress-Archive não gerou o arquivo esperado"
    }

    # Rotação: apaga backups mais velhos que $DiasRetencao dias (nas duas pastas).
    foreach ($pasta in @($PastaBackup, $PastaBackupExtra)) {
        if (-not $pasta -or -not (Test-Path $pasta)) { continue }

        Get-ChildItem $pasta -Filter "*.zip" |
            Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$DiasRetencao) } |
            ForEach-Object {
                Write-Output "$(Get-Date -Format s) Removendo backup antigo: $($_.FullName)"
                Remove-Item $_.FullName -Force
            }
    }
}

Write-Output "$(Get-Date -Format s) Serviço de backup de arquivos iniciado - horário diário: $HorarioBackup, retenção: $DiasRetencao dias, pasta: $PastaBackup"

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
