<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE atendentes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE atendentes FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY tenant_isolation ON atendentes
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON atendentes');
        DB::statement('ALTER TABLE atendentes NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE atendentes DISABLE ROW LEVEL SECURITY');
    }
};
