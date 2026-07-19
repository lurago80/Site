<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plano de contas (Escopo v2, decisão de 2026-07-21) - categoriza
     * as contas a pagar/receber (ex.: "Fornecedores", "Salários",
     * "Vendas de produtos") para relatório financeiro por categoria.
     */
    public function up(): void
    {
        Schema::create('plano_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('codigo')->nullable();
            $table->string('nome');
            $table->enum('tipo', ['receita', 'despesa']);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plano_contas');
    }
};
