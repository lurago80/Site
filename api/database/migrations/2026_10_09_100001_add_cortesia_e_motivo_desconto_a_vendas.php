<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            // Cortesia = desconto de 100% (venda sem cobrança). O motivo é obrigatório
            // sempre que o PDV aplica desconto ou cortesia.
            $table->boolean('cortesia')->default(false)->after('valor_desconto');
            $table->string('motivo_desconto', 255)->nullable()->after('cortesia');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['cortesia', 'motivo_desconto']);
        });
    }
};
