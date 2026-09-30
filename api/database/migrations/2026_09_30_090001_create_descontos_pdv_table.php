<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descontos_pdv', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('descricao');
            $table->decimal('percentual', 5, 2);
            // 'produtos' = incide só nos produtos; 'visitas' = só nas visitas (máx. 2 tickets).
            $table->string('aplica_em', 10);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('desconto_pdv_id')->nullable()->after('cupom_id')
                ->constrained('descontos_pdv')->nullOnDelete();
        });

        DB::statement('ALTER TABLE descontos_pdv ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE descontos_pdv FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY tenant_isolation ON descontos_pdv
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
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desconto_pdv_id');
        });

        Schema::dropIfExists('descontos_pdv');
    }
};
