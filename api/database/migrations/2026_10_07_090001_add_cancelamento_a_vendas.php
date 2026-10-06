<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cancelamento de venda do PDV (só administrador): guarda quem cancelou,
     * quando e por quê, na própria venda. A mudança do status_pagamento para
     * 'cancelado' também fica no log de auditoria (Venda é model auditado).
     */
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->timestamp('cancelada_em')->nullable()->after('check_in_usuario_id');
            $table->foreignId('cancelada_por_usuario_id')->nullable()->after('cancelada_em')->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelamento')->nullable()->after('cancelada_por_usuario_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelada_por_usuario_id');
            $table->dropColumn(['cancelada_em', 'motivo_cancelamento']);
        });
    }
};
