<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * vendedor_id (guia da visita, recebe comissão) e atendente_id
     * (quem operou a venda no caixa) são papéis diferentes - uma venda
     * pode ter os dois preenchidos ao mesmo tempo.
     */
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('atendente_id')->nullable()->after('vendedor_id')->constrained('atendentes');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atendente_id');
        });
    }
};
