<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // uf nula = valor para os estados sem regra própria ("demais estados").
        Schema::create('frete_regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('uf', 2)->nullable();
            $table->decimal('valor', 10, 2);
            $table->unsignedSmallInteger('prazo_dias')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'uf']);
        });

        DB::statement('ALTER TABLE frete_regras ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE frete_regras FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON frete_regras
            USING (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
            WITH CHECK (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
        ");

        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('frete_gratis_acima', 10, 2)->nullable();
            $table->boolean('permite_retirada')->default(false);
            $table->string('instrucoes_retirada', 500)->nullable();
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->string('tipo_entrega', 10)->nullable();
            $table->decimal('valor_frete', 10, 2)->default(0);
            // Foto do endereço no momento da compra - o cadastro do cliente
            // pode mudar depois, mas o pedido precisa lembrar para onde foi.
            $table->jsonb('endereco_entrega')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['tipo_entrega', 'valor_frete', 'endereco_entrega']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['frete_gratis_acima', 'permite_retirada', 'instrucoes_retirada']);
        });

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON frete_regras');
        Schema::dropIfExists('frete_regras');
    }
};
