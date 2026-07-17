<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->enum('tipo', ['fisico', 'agendamento']);
            $table->decimal('preco_venda', 10, 2);
            $table->integer('estoque_atual')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
