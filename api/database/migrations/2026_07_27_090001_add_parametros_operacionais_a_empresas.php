<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('estoque_permite_negativo')->default(false)->after('cor_primaria');
            $table->boolean('pdv_impressao_direta')->default(false)->after('estoque_permite_negativo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['estoque_permite_negativo', 'pdv_impressao_direta']);
        });
    }
};
