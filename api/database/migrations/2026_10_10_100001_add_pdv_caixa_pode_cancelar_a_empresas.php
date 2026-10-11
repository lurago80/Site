<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Parâmetro do administrador: libera "Vendas do dia / Cancelar" para o perfil Caixa no PDV.
            $table->boolean('pdv_caixa_pode_cancelar')->default(false)->after('pdv_impressao_direta');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('pdv_caixa_pode_cancelar');
        });
    }
};
