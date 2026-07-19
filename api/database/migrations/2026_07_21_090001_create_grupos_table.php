<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grupo de produtos (Escopo v2, decisão de 2026-07-21) - formaliza
     * em tabela própria o que hoje é só o campo de texto livre
     * `produtos.categoria`. Os dois convivem: categoria continua
     * existindo (não migramos dados existentes automaticamente), grupo
     * é o novo cadastro estruturado, usado por relatórios.
     */
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
