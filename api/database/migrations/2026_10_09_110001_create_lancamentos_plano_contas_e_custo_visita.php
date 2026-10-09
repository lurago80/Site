<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Custo por ticket da visita (os produtos já têm preco_custo).
        Schema::table('agenda_visitacoes', function (Blueprint $table) {
            $table->decimal('custo_visita', 10, 2)->default(0)->after('valor_visita');
        });

        // Custo apurado da linha no momento do lançamento (produto, kit ou visita).
        Schema::table('itens_venda', function (Blueprint $table) {
            $table->decimal('custo_total', 12, 2)->nullable()->after('valor_total');
        });

        // Lançamentos gerados pelas vendas: receita de produtos (002.001), receita de
        // serviços (002.002) e descontos concedidos (001.015). Cancelar a venda apaga os lançamentos.
        Schema::create('lancamentos_plano_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('plano_conta_id')->constrained('plano_contas');
            $table->string('origem', 20); // receita_produtos | receita_servicos | desconto
            $table->decimal('valor', 12, 2);
            $table->date('data');
            $table->string('historico');
            $table->timestamps();
            $table->index(['empresa_id', 'plano_conta_id', 'data']);
        });

        DB::statement('ALTER TABLE lancamentos_plano_contas ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE lancamentos_plano_contas FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON lancamentos_plano_contas
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
        Schema::dropIfExists('lancamentos_plano_contas');

        Schema::table('itens_venda', function (Blueprint $table) {
            $table->dropColumn('custo_total');
        });

        Schema::table('agenda_visitacoes', function (Blueprint $table) {
            $table->dropColumn('custo_visita');
        });
    }
};
