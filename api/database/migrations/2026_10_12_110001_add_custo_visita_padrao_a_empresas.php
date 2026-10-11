<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Custo por visitante usado quando um horário da agenda é criado sem informar o custo.
            $table->decimal('custo_visita_padrao', 10, 2)->default(0)->after('pdv_caixa_pode_cancelar');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('custo_visita_padrao');
        });
    }
};
