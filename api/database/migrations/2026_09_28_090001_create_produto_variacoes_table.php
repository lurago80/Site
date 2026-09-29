<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_variacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('tamanho', 10);
            $table->integer('estoque_atual')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['produto_id', 'tamanho']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_variacoes');
    }
};
