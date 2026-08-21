<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Laravel implementa enum() no Postgres como varchar + CHECK
     * constraint (não é um tipo enum nativo) - por isso adicionar um
     * valor novo é DROP + ADD CONSTRAINT, não "ALTER TYPE ... ADD VALUE".
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE config_pagamento DROP CONSTRAINT config_pagamento_gateway_check');
        DB::statement("ALTER TABLE config_pagamento ADD CONSTRAINT config_pagamento_gateway_check CHECK (gateway IN ('mercadopago', 'pagseguro', 'cielo', 'stone'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE config_pagamento DROP CONSTRAINT config_pagamento_gateway_check');
        DB::statement("ALTER TABLE config_pagamento ADD CONSTRAINT config_pagamento_gateway_check CHECK (gateway IN ('mercadopago', 'pagseguro', 'cielo'))");
    }
};
