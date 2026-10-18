<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generaliza lancamentos_plano_contas para aceitar lançamentos de compras (entrada de
 * notas), além dos de vendas: venda_id passa a ser opcional e ganha compra_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_plano_contas', function (Blueprint $table) {
            $table->foreignId('venda_id')->nullable()->change();
            $table->foreignId('compra_id')->nullable()->after('venda_id')->constrained('compras')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_plano_contas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compra_id');
            $table->foreignId('venda_id')->nullable(false)->change();
        });
    }
};
