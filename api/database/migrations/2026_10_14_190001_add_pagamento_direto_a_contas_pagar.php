<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suporta a tela de "Pagamentos" (lançamento direto, já pago, com recibo):
 * o beneficiário pode ser um fornecedor (já existia), um atendente ou um
 * vendedor; guarda também a forma de pagamento e o instante exato do
 * pagamento (pago_em), para o recibo e para distinguir esses lançamentos
 * diretos dos antigos "marcar como pago" (que só tinham status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->foreignId('atendente_id')->nullable()->after('fornecedor_id')->constrained('atendentes');
            $table->foreignId('vendedor_id')->nullable()->after('atendente_id')->constrained('vendedores');
            $table->foreignId('forma_pagamento_id')->nullable()->after('banco_id')->constrained('formas_pagamento');
            $table->timestamp('pago_em')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atendente_id');
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropConstrainedForeignId('forma_pagamento_id');
            $table->dropColumn('pago_em');
        });
    }
};
