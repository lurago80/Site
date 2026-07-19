<?php

namespace App\Services\Notificacao\Dto;

readonly class ResultadoEnvio
{
    public function __construct(
        public string $status, // 'enviado' | 'falha'
        public ?string $referenciaExterna = null,
        public ?array $payloadBruto = null,
        public ?string $motivoFalha = null,
    ) {}
}
