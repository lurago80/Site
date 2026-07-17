<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('venda_id')->constrained('vendas');
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->foreignId('agenda_visitacao_id')->nullable()->constrained('agenda_visitacoes');
            $table->integer('quantidade');
            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('valor_total', 10, 2);
            $table->decimal('comissao_percentual', 5, 2)->nullable();
            $table->decimal('comissao_valor', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_venda');
    }
};
