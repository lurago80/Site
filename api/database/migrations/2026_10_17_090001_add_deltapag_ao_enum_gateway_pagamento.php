<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Laravel implementa enum() no Postgres como varchar + CHECK
     * constraint (não é um tipo enum nativo) - por isso adicionar um
     * valor novo é DROP + ADD CONSTRAINT, não "ALTER TYPE ... ADD VALUE".
     *
     * Aproveitado para também adicionar 'stone' ao CHECK de `cobrancas`,
     * que tinha ficado de fora quando a Stone foi adicionada em
     * 2026_08_21_100001 (só o config_pagamento foi atualizado na época -
     * sem isso, uma cobrança feita pela Stone não conseguiria ser salva).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE config_pagamento DROP CONSTRAINT config_pagamento_gateway_check');
        DB::statement("ALTER TABLE config_pagamento ADD CONSTRAINT config_pagamento_gateway_check CHECK (gateway IN ('mercadopago', 'pagseguro', 'cielo', 'stone', 'deltapag'))");

        DB::statement('ALTER TABLE cobrancas DROP CONSTRAINT cobrancas_gateway_check');
        DB::statement("ALTER TABLE cobrancas ADD CONSTRAINT cobrancas_gateway_check CHECK (gateway IN ('mercadopago', 'pagseguro', 'cielo', 'simulado', 'stone', 'deltapag'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE config_pagamento DROP CONSTRAINT config_pagamento_gateway_check');
        DB::statement("ALTER TABLE config_pagamento ADD CONSTRAINT config_pagamento_gateway_check CHECK (gateway IN ('mercadopago', 'pagseguro', 'cielo', 'stone'))");

        DB::statement('ALTER TABLE cobrancas DROP CONSTRAINT cobrancas_gateway_check');
        DB::statement("ALTER TABLE cobrancas ADD CONSTRAINT cobrancas_gateway_check CHECK (gateway IN ('mercadopago', 'pagseguro', 'cielo', 'simulado'))");
    }
};
