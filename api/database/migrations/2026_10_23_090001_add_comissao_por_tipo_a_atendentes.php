<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Troca o percentual único (nunca usado no cálculo de comissão - ver
 * VendaPdvService) por duas comissões independentes, cada uma com seu
 * próprio liga/desliga: o atendente pode ganhar comissão só em produtos,
 * só em visitas, nos dois, ou em nenhum (cadastro padrão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atendentes', function (Blueprint $table) {
            $table->boolean('comissao_produtos_ativa')->default(false)->after('percentual_comissao');
            $table->decimal('percentual_comissao_produtos', 5, 2)->nullable()->after('comissao_produtos_ativa');
            $table->boolean('comissao_visitas_ativa')->default(false)->after('percentual_comissao_produtos');
            $table->decimal('percentual_comissao_visitas', 5, 2)->nullable()->after('comissao_visitas_ativa');
        });

        Schema::table('atendentes', function (Blueprint $table) {
            $table->dropColumn('percentual_comissao');
        });
    }

    public function down(): void
    {
        Schema::table('atendentes', function (Blueprint $table) {
            $table->decimal('percentual_comissao', 5, 2)->default(3)->after('telefone');
        });

        Schema::table('atendentes', function (Blueprint $table) {
            $table->dropColumn(['comissao_produtos_ativa', 'percentual_comissao_produtos', 'comissao_visitas_ativa', 'percentual_comissao_visitas']);
        });
    }
};
