<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tabelas = ['grupos', 'plano_contas', 'bancos', 'grava_banco', 'caixas'];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");

            DB::statement("
                CREATE POLICY tenant_isolation ON {$tabela}
                USING (
                    current_setting('app.is_super_admin', true) = 'true'
                    OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_super_admin', true) = 'true'
                    OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }
};
