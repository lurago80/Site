<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itens_venda', function (Blueprint $table) {
            $table->foreignId('produto_variacao_id')->nullable()->after('produto_id')->constrained('produto_variacoes');
        });
    }

    public function down(): void
    {
        Schema::table('itens_venda', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produto_variacao_id');
        });
    }
};
