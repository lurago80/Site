<?php

namespace App\Console\Commands;

use App\Models\Cupom;
use App\Models\Empresa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:importar-cupons {empresa : Slug ou ID da empresa} {caminho? : Caminho do .csv (colunas: codigo,percentual) - padrão: storage/app/importacoes/cupons_desconto_qm.csv}')]
#[Description('Importa um lote de cupons de desconto de uso único a partir de um .csv (ex.: planilha de códigos promocionais gerados em massa). Cada linha vira um cupom independente, com limite de 1 uso.')]
class ImportarCuponsDesconto extends Command
{
    public function handle(): int
    {
        // Rodando fora de uma requisição HTTP não há tenant setado pelo
        // SetTenantContext - precisa do bypass de super admin pra RLS deixar
        // ler/gravar em cupons (mesmo padrão dos outros comandos/jobs).
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $identificador = $this->argument('empresa');

        // Postgres não aceita comparar uma coluna bigint com string não-numérica
        // (erro 22P02) - só entra o "or id = " quando o argumento for numérico.
        $empresa = Empresa::where('slug', $identificador)
            ->when(ctype_digit($identificador), fn ($q) => $q->orWhere('id', $identificador))
            ->first();

        if (! $empresa) {
            $this->error('Empresa não encontrada: '.$this->argument('empresa'));

            return self::FAILURE;
        }

        $caminho = $this->argument('caminho') ?? storage_path('app/importacoes/cupons_desconto_qm.csv');

        if (! file_exists($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $linhas = array_map('str_getcsv', file($caminho));
        $cabecalho = array_map('trim', array_shift($linhas));

        $colCodigo = array_search('codigo', $cabecalho);
        $colPercentual = array_search('percentual', $cabecalho);

        if ($colCodigo === false || $colPercentual === false) {
            $this->error('CSV precisa ter as colunas "codigo" e "percentual".');

            return self::FAILURE;
        }

        $agora = now();
        $registros = [];

        foreach ($linhas as $linha) {
            if (empty($linha[$colCodigo])) {
                continue;
            }

            $registros[] = [
                'empresa_id' => $empresa->id,
                'codigo' => mb_strtoupper(trim($linha[$colCodigo])),
                'tipo' => 'percentual',
                'valor' => round(((float) $linha[$colPercentual]) * 100, 2),
                'limite_uso' => 1,
                'usos_realizados' => 0,
                'ativo' => true,
                'importado' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        $existentes = Cupom::where('empresa_id', $empresa->id)
            ->whereIn('codigo', array_column($registros, 'codigo'))
            ->pluck('codigo')
            ->map(fn ($c) => mb_strtoupper($c))
            ->all();

        if ($existentes !== []) {
            $registros = array_values(array_filter($registros, fn ($r) => ! in_array($r['codigo'], $existentes, true)));
            $this->warn(count($existentes).' código(s) já existiam para esta empresa e foram ignorados.');
        }

        foreach (array_chunk($registros, 500) as $lote) {
            DB::table('cupons')->insert($lote);
        }

        $this->info(count($registros)." cupom(ns) importado(s) para {$empresa->nome_fantasia}.");

        return self::SUCCESS;
    }
}
