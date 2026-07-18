<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE numeracao_inutilizada ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE numeracao_inutilizada FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY tenant_isolation ON numeracao_inutilizada
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

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON numeracao_inutilizada');
        DB::statement('ALTER TABLE numeracao_inutilizada NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE numeracao_inutilizada DISABLE ROW LEVEL SECURITY');
    }
};
