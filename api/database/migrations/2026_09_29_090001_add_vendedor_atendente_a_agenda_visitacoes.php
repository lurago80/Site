<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_visitacoes', function (Blueprint $table) {
            $table->foreignId('vendedor_id')->nullable()->after('produto_id')->constrained('vendedores');
            $table->foreignId('atendente_id')->nullable()->after('vendedor_id')->constrained('atendentes');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_visitacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropConstrainedForeignId('atendente_id');
        });
    }
};
