<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\InventarioSnapshot;
use App\Models\Produto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fotografa o estoque atual de cada produto/tamanho, todo dia, para que o
 * inventário de um dia qualquer possa ser consultado depois (ex.: "quanto
 * tinha em estoque no dia 10/10?"). Kits e produtos sem controle de estoque
 * não entram - não há o que fotografar.
 *
 * Idempotente: rodar de novo no mesmo dia substitui (updateOrCreate) em vez
 * de duplicar, então um --data de teste ou um reprocessamento não gera lixo.
 */
#[Signature('app:gerar-inventario-diario {--data= : Data do snapshot (Y-m-d); padrão hoje} {--empresa= : Só esta empresa (id)}')]
#[Description('Grava uma fotografia do estoque atual de cada produto/tamanho, para consulta histórica do inventário por dia.')]
class GerarInventarioDiario extends Command
{
    public function handle(): int
    {
        // Comando roda para todas as empresas - precisa do bypass de RLS.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $data = $this->option('data') ? \Carbon\Carbon::parse($this->option('data'))->toDateString() : now()->toDateString();

        $empresas = Empresa::query()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $totalLinhas = 0;

        foreach ($empresas as $empresa) {
            $produtos = Produto::where('empresa_id', $empresa->id)
                ->where('eh_kit', false)
                ->with('variacoes.produtoVinculado:id,estoque_atual')
                ->get();

            foreach ($produtos as $produto) {
                if ($produto->variacoes->isNotEmpty()) {
                    foreach ($produto->variacoes as $variacao) {
                        $estoque = $variacao->estoqueDisponivel();
                        if ($estoque === null) {
                            continue;
                        }

                        InventarioSnapshot::updateOrCreate(
                            ['empresa_id' => $empresa->id, 'data' => $data, 'produto_id' => $produto->id, 'produto_variacao_id' => $variacao->id],
                            ['nome' => $produto->nome, 'tamanho' => $variacao->tamanho, 'estoque' => $estoque],
                        );
                        $totalLinhas++;
                    }

                    continue;
                }

                if ($produto->estoque_atual === null) {
                    continue;
                }

                InventarioSnapshot::updateOrCreate(
                    ['empresa_id' => $empresa->id, 'data' => $data, 'produto_id' => $produto->id, 'produto_variacao_id' => null],
                    ['nome' => $produto->nome, 'tamanho' => null, 'estoque' => (int) $produto->estoque_atual],
                );
                $totalLinhas++;
            }
        }

        $this->info("Inventário de {$data} gravado: {$totalLinhas} linha(s) em ".$empresas->count().' empresa(s).');

        return self::SUCCESS;
    }
}
