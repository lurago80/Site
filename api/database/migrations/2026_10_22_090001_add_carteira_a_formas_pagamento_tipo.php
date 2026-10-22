<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres não deixa adicionar valor a enum dentro de transação junto com
        // outros comandos, então troca a coluna para string com check constraint,
        // igual o padrão do Laravel faria num ALTER TYPE (mysql usa enum nativo).
        DB::statement("ALTER TABLE formas_pagamento DROP CONSTRAINT IF EXISTS formas_pagamento_tipo_check");
        DB::statement("ALTER TABLE formas_pagamento ADD CONSTRAINT formas_pagamento_tipo_check CHECK (tipo IN ('dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'carteira', 'outro'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE formas_pagamento SET tipo = 'outro' WHERE tipo = 'carteira'");
        DB::statement("ALTER TABLE formas_pagamento DROP CONSTRAINT IF EXISTS formas_pagamento_tipo_check");
        DB::statement("ALTER TABLE formas_pagamento ADD CONSTRAINT formas_pagamento_tipo_check CHECK (tipo IN ('dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'outro'))");
    }
};
