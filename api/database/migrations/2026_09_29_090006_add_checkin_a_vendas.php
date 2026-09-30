<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check-in da visita (recepção confere o recibo do cliente na chegada -
     * pago e válido) - marcado uma vez na venda inteira, não por item, já
     * que o grupo chega junto e usa o mesmo ticket/pedido.
     */
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->timestamp('check_in_em')->nullable()->after('status_pagamento');
            $table->foreignId('check_in_usuario_id')->nullable()->after('check_in_em')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_in_usuario_id');
            $table->dropColumn('check_in_em');
        });
    }
};
