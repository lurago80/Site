<?php

namespace App\Services\Imagens;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Reduz as fotos enviadas (lado maior limitado) e converte para WebP,
 * para a loja virtual não baixar originais de vários MB da câmera.
 * Se a GD não estiver disponível ou a imagem não for decodificável,
 * o arquivo original é gravado sem alteração.
 */
class RedimensionadorImagem
{
    public const LADO_MAXIMO = 1000;
    public const QUALIDADE_WEBP = 82;

    /**
     * Grava a imagem no disco público e devolve o caminho relativo.
     */
    public function armazenar(UploadedFile $arquivo, string $pasta): string
    {
        $conteudo = $this->otimizar($arquivo->getRealPath());

        if ($conteudo === null) {
            return $arquivo->store($pasta, 'public');
        }

        $caminho = trim($pasta, '/') . '/' . bin2hex(random_bytes(20)) . '.webp';
        Storage::disk('public')->put($caminho, $conteudo);

        return $caminho;
    }

    /**
     * Devolve o binário WebP redimensionado, ou null se não for possível.
     */
    public function otimizar(string $arquivo): ?string
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $bruto = @file_get_contents($arquivo);
        $origem = $bruto === false ? false : @imagecreatefromstring($bruto);
        if ($origem === false) {
            return null;
        }

        $origem = $this->aplicarOrientacaoExif($origem, $bruto);

        $largura = imagesx($origem);
        $altura = imagesy($origem);
        $escala = min(1, self::LADO_MAXIMO / max($largura, $altura));

        if ($escala < 1) {
            $reduzida = imagescale($origem, max(1, (int) round($largura * $escala)), max(1, (int) round($altura * $escala)));
            if ($reduzida === false) {
                return null;
            }
            $origem = $reduzida;
        }

        imagepalettetotruecolor($origem);
        imagealphablending($origem, false);
        imagesavealpha($origem, true);

        ob_start();
        $ok = imagewebp($origem, null, self::QUALIDADE_WEBP);
        $saida = ob_get_clean();

        return $ok && $saida !== '' ? $saida : null;
    }

    /**
     * Fotos de celular vêm giradas via EXIF; GD ignora isso, então corrige aqui.
     */
    private function aplicarOrientacaoExif(\GdImage $imagem, string $bruto): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagem;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bruto));
        $angulo = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angulo === 0) {
            return $imagem;
        }

        return imagerotate($imagem, $angulo, 0) ?: $imagem;
    }
}
