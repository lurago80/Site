<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('comissao_atendente', 10, 2)->nullable()->after('comissao');
        });

        Schema::table('itens_venda', function (Blueprint $table) {
            $table->decimal('comissao_atendente_percentual', 5, 2)->nullable()->after('comissao_valor');
            $table->decimal('comissao_atendente_valor', 10, 2)->nullable()->after('comissao_atendente_percentual');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('comissao_atendente');
        });

        Schema::table('itens_venda', function (Blueprint $table) {
            $table->dropColumn(['comissao_atendente_percentual', 'comissao_atendente_valor']);
        });
    }
};
