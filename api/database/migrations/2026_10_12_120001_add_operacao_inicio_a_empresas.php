<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Os relatórios ignoram o que veio antes desta data (vendas de teste ficam no banco, fora dos números).
            $table->date('operacao_inicio')->nullable()->after('custo_visita_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('operacao_inicio');
        });
    }
};
