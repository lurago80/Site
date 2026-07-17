<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gateway fiscal ativo
    |--------------------------------------------------------------------------
    | 'simulado' - não fala com a SEFAZ, usado em dev/homologação sem
    |              certificado digital de testes disponível.
    | 'nfephp'   - Fase 1 do módulo fiscal (Escopo v2, seção 3.4): biblioteca
    |              gratuita/open-source. Ver App\Services\Fiscal\NfePhpFiscalGateway
    |              para o que ainda falta implementar antes de usar em produção.
    */
    'driver' => env('FISCAL_GATEWAY', 'simulado'),
];
