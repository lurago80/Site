<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            // a_separar -> enviado (ou pronto p/ retirada) -> entregue (ou retirado)
            $table->string('status_envio', 15)->nullable();
            $table->string('codigo_rastreio', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['status_envio', 'codigo_rastreio']);
        });
    }
};
