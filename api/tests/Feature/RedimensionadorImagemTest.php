<?php

namespace Tests\Feature;

use App\Services\Imagens\RedimensionadorImagem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RedimensionadorImagemTest extends TestCase
{
    public function test_reduz_foto_grande_e_converte_para_webp(): void
    {
        Storage::fake('public');
        $arquivo = UploadedFile::fake()->image('foto.jpg', 3000, 2000);

        $caminho = app(RedimensionadorImagem::class)->armazenar($arquivo, 'produtos');

        $this->assertStringEndsWith('.webp', $caminho);
        Storage::disk('public')->assertExists($caminho);
        [$largura, $altura] = getimagesizefromstring(Storage::disk('public')->get($caminho));
        $this->assertSame(1000, $largura);
        $this->assertSame(667, $altura);
    }

    public function test_nao_amplia_foto_pequena(): void
    {
        Storage::fake('public');
        $arquivo = UploadedFile::fake()->image('foto.png', 400, 300);

        $caminho = app(RedimensionadorImagem::class)->armazenar($arquivo, 'produtos');

        [$largura, $altura] = getimagesizefromstring(Storage::disk('public')->get($caminho));
        $this->assertSame(400, $largura);
        $this->assertSame(300, $altura);
    }
}
