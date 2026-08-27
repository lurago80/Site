# Substitui o cron (`* * * * * php artisan schedule:run`) no TERMINAL02.
#
# Roda como serviço Windows via NSSM (o Agendador de Tarefas está
# desabilitado nessa máquina, mesmo motivo do backup do Postgres) -
# loop que chama "php artisan schedule:run" a cada 60s. O próprio
# Laravel decide internamente se algum agendamento (routes/console.php)
# precisa rodar nesse minuto - chamar toda hora sem precisar é seguro,
# é exatamente o que um cron real faria.

$ErrorActionPreference = "Stop"

$PhpBin = "C:\PHP84\php.exe"
$PastaApi = "C:\VS_Code\Site\api"

Write-Output "$(Get-Date -Format s) Loop de schedule:run iniciado (a cada 60s) - $PastaApi"

while ($true) {
    try {
        Push-Location $PastaApi
        $saida = & $PhpBin artisan schedule:run 2>&1
        Pop-Location

        if ($saida) {
            Write-Output "$(Get-Date -Format s) $saida"
        }
    } catch {
        Write-Output "$(Get-Date -Format s) ERRO ao rodar schedule:run: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds 60
}
