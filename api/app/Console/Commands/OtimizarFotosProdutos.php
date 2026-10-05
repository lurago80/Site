<?php

namespace App\Console\Commands;

use App\Services\Imagens\RedimensionadorImagem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('app:otimizar-fotos-produtos {--aplicar : Grava as mudanças - sem esta opção apenas simula}')]
#[Description('Reduz (1000 px) e converte para WebP as fotos de produto já cadastradas, guardando os originais em backup.')]
class OtimizarFotosProdutos extends Command
{
    private const PASTA_BACKUP = 'backup_fotos_produtos';

    public function handle(RedimensionadorImagem $redimensionador): int
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $aplicar = (bool) $this->option('aplicar');
        $disco = Storage::disk('public');

        $urls = DB::table('produtos')
            ->whereNotNull('imagem_url')
            ->where('imagem_url', 'like', '%/produtos/%')
            ->where('imagem_url', 'not like', '%.webp')
            ->distinct()
            ->pluck('imagem_url');

        $antes = $depois = $otimizadas = $ignoradas = 0;

        foreach ($urls as $url) {
            $nome = basename(parse_url($url, PHP_URL_PATH));
            $caminho = "produtos/{$nome}";

            if (! $disco->exists($caminho)) {
                $this->warn("Arquivo ausente, ignorado: {$caminho}");
                $ignoradas++;

                continue;
            }

            $original = $disco->path($caminho);
            $tamanhoAntes = filesize($original);
            $conteudo = $redimensionador->otimizar($original);

            if ($conteudo === null || strlen($conteudo) >= $tamanhoAntes) {
                $this->line("Mantida (sem ganho): {$caminho}");
                $ignoradas++;

                continue;
            }

            $novoNome = pathinfo($nome, PATHINFO_FILENAME) . '.webp';
            $this->line(sprintf('%s: %.0f KB -> %.0f KB', $caminho, $tamanhoAntes / 1024, strlen($conteudo) / 1024));
            $antes += $tamanhoAntes;
            $depois += strlen($conteudo);
            $otimizadas++;

            if (! $aplicar) {
                continue;
            }

            $disco->put("produtos/{$novoNome}", $conteudo);
            Storage::disk('local')->put(self::PASTA_BACKUP . "/{$nome}", file_get_contents($original));
            DB::table('produtos')->where('imagem_url', $url)->update([
                'imagem_url' => substr($url, 0, -strlen($nome)) . $novoNome,
            ]);
            $disco->delete($caminho);
        }

        $this->info(sprintf(
            '%d foto(s) %s, %d mantida(s). %.1f MB -> %.1f MB.',
            $otimizadas,
            $aplicar ? 'otimizada(s)' : 'a otimizar (simulação, use --aplicar para gravar)',
            $ignoradas,
            $antes / 1048576,
            $depois / 1048576,
        ));
        if ($aplicar && $otimizadas > 0) {
            $this->info('Originais guardados em ' . Storage::disk('local')->path(self::PASTA_BACKUP));
        }

        return self::SUCCESS;
    }
}
