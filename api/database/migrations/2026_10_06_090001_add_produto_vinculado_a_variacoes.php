<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produto_variacoes', function (Blueprint $table) {
            // Variação "vitrine": o estoque e o cadastro fiscal são do produto vinculado
            // (ex.: opção PILSEN da CERVEJA ARTESANAL baixa o estoque da CERVEJA PILSEN).
            // Sem cascade de propósito: um produto vinculado não pode ser apagado.
            $table->foreignId('produto_vinculado_id')->nullable()->constrained('produtos');
        });
    }

    public function down(): void
    {
        Schema::table('produto_variacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produto_vinculado_id');
        });
    }
};
