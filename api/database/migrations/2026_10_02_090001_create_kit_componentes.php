<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->boolean('eh_kit')->default(false);
        });

        // tipo 'fixo': item que vem sempre no kit (ex.: 1 caneca).
        // tipo 'escolha': o cliente escolhe `quantidade` unidades entre as
        // variações do produto (ex.: 3 cervejas entre os sabores).
        Schema::create('kit_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kit_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('tipo', 10);
            $table->unsignedSmallInteger('quantidade');
            $table->timestamps();

            $table->unique(['kit_id', 'produto_id']);
        });

        DB::statement('ALTER TABLE kit_componentes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE kit_componentes FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON kit_componentes
            USING (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
            WITH CHECK (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
        ");

        // O que o cliente escolheu em cada kit vendido (produto, sabor e
        // quantidade) - o item da venda é o kit, a composição fica registrada.
        Schema::table('itens_venda', function (Blueprint $table) {
            $table->jsonb('composicao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('itens_venda', function (Blueprint $table) {
            $table->dropColumn('composicao');
        });

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON kit_componentes');
        Schema::dropIfExists('kit_componentes');

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('eh_kit');
        });
    }
};
