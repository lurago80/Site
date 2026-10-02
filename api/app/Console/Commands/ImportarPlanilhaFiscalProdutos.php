<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Fiscal\PlanilhaFiscalProdutos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:importar-fiscal-produtos {empresa : Slug ou ID da empresa} {caminho : Caminho do .xlsx devolvido pelo contador} {--aplicar : Grava no banco (sem esta opção só simula e mostra o que mudaria)}')]
#[Description('Lê a planilha fiscal preenchida pelo contador e atualiza os campos fiscais dos produtos (por ID). Sem --aplicar apenas simula.')]
class ImportarPlanilhaFiscalProdutos extends Command
{
    public function handle(PlanilhaFiscalProdutos $planilha): int
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $identificador = $this->argument('empresa');
        $empresa = Empresa::where('slug', $identificador)
            ->when(ctype_digit($identificador), fn ($q) => $q->orWhere('id', $identificador))
            ->first();

        if (! $empresa) {
            $this->error("Empresa não encontrada: {$identificador}");

            return self::FAILURE;
        }

        $caminho = $this->argument('caminho');
        if (! file_exists($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        try {
            $resultado = $planilha->ler($empresa->id, $caminho);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($resultado['erros'] as $linha => $mensagem) {
            $this->warn("Linha {$linha}: {$mensagem}");
        }

        $this->line(sprintf(
            '%d produto(s) a atualizar, %d sem alteração, %d com erro.',
            count($resultado['validas']), $resultado['sem_alteracao'], count($resultado['erros'])
        ));

        if (! $this->option('aplicar')) {
            $this->comment('Simulação: nada foi gravado. Rode novamente com --aplicar para gravar.');

            return $resultado['erros'] === [] ? self::SUCCESS : self::FAILURE;
        }

        // Linhas com erro ficam de fora; as demais são gravadas juntas.
        DB::transaction(function () use ($resultado) {
            foreach ($resultado['validas'] as $item) {
                $item['produto']->save();
            }
        });

        $this->info(count($resultado['validas']).' produto(s) atualizado(s) em '.$empresa->slug.'.');

        return $resultado['erros'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
