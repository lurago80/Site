<?php

namespace App\Support;

/**
 * Traduz a falha ao abrir um certificado .pfx numa mensagem que diz o que
 * fazer. O motivo mais comum em A1 antigos NÃO é senha errada: o arquivo usa
 * criptografia legada (RC2/3DES) que o OpenSSL 3 do PHP recusa por padrão.
 */
class ErroCertificado
{
    public static function mensagem(\Throwable $erro): string
    {
        $detalhe = $erro->getMessage();

        // OpenSSL 3 sem o provedor legado: "error:0308010C:digital envelope routines::unsupported"
        if (stripos($detalhe, 'unsupported') !== false || stripos($detalhe, '0308010C') !== false) {
            return 'Este certificado usa um formato de criptografia antigo (RC2/3DES) que o servidor não consegue ler - '
                .'isso não significa que a senha esteja errada. Converta o arquivo para o formato atual e envie de novo. '
                .'Com o OpenSSL: "openssl pkcs12 -legacy -in certificado.pfx -nodes -out temporario.pem" e depois '
                .'"openssl pkcs12 -export -in temporario.pem -out certificado_novo.pfx" (use a mesma senha e apague o .pem).';
        }

        // Senha errada: "mac verify failure" / "invalid password"
        if (stripos($detalhe, 'mac verify') !== false || stripos($detalhe, 'password') !== false || stripos($detalhe, 'senha') !== false) {
            return 'Senha do certificado incorreta.';
        }

        return 'Não foi possível ler o certificado - verifique se o arquivo é um .pfx/.p12 válido e se a senha está correta.';
    }
}
