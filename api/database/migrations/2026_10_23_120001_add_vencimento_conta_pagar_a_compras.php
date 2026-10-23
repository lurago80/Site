<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vencimento do contas a pagar gerado ao confirmar a compra - informável só na
 * entrada manual (XML não tem como o usuário digitar nada no momento do
 * upload). Nula = mantém o comportamento antigo (usa data_entrada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->date('vencimento_conta_pagar')->nullable()->after('data_entrada');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('vencimento_conta_pagar');
        });
    }
};
