<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banco de indicações ("como o cliente chegou até nós"): o atendente digita
     * a indicação ao validar o ticket no PDV; nomes já cadastrados são reaproveitados
     * nas próximas buscas e o administrador vê o resumo por indicação.
     */
    public function up(): void
    {
        Schema::create('indicacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            $table->timestamps();
        });

        // Um mesmo nome (sem diferenciar maiúsculas) só pode existir uma vez por empresa.
        DB::statement('CREATE UNIQUE INDEX indicacoes_empresa_nome_unico ON indicacoes (empresa_id, lower(nome))');

        DB::statement('ALTER TABLE indicacoes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE indicacoes FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON indicacoes
            USING (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
            WITH CHECK (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
        ");

        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('indicacao_id')->nullable()->after('atendente_id')->constrained('indicacoes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('indicacao_id');
        });
        Schema::dropIfExists('indicacoes');
    }
};
