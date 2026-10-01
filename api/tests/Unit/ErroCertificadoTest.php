<?php

namespace Tests\Unit;

use App\Support\ErroCertificado;
use PHPUnit\Framework\TestCase;

class ErroCertificadoTest extends TestCase
{
    public function test_formato_antigo_do_pfx_nao_e_apresentado_como_senha_errada(): void
    {
        // texto real devolvido pelo NFePHP com OpenSSL 3 diante de um A1 com RC2/3DES
        $erro = new \RuntimeException('Impossivel ler o certificado, ocorreu o seguinte erro: (error:0308010C:digital envelope routines::unsupported)');

        $mensagem = ErroCertificado::mensagem($erro);

        $this->assertStringContainsString('criptografia antigo', $mensagem);
        $this->assertStringContainsString('não significa que a senha esteja errada', $mensagem);
        $this->assertStringContainsString('openssl pkcs12 -legacy', $mensagem);
    }

    public function test_senha_errada_e_identificada(): void
    {
        $this->assertSame(
            'Senha do certificado incorreta.',
            ErroCertificado::mensagem(new \RuntimeException('error:11800071:PKCS12 routines::mac verify failure'))
        );
    }

    public function test_outros_erros_recebem_mensagem_generica(): void
    {
        $mensagem = ErroCertificado::mensagem(new \RuntimeException('error:0480006C:PEM routines::no start line'));

        $this->assertStringContainsString('verifique se o arquivo é um .pfx/.p12 válido', $mensagem);
    }
}
