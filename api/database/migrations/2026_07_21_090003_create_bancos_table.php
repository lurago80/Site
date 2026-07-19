<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro de bancos/contas bancárias da empresa cliente (Escopo
     * v2, decisão de 2026-07-21) - base para o extrato de movimentos
     * em `grava_banco`.
     */
    public function up(): void
    {
        Schema::create('bancos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome'); // ex.: "Banco do Brasil - Conta Movimento"
            $table->string('codigo_banco')->nullable(); // código Febraban
            $table->string('agencia')->nullable();
            $table->string('numero_conta')->nullable();
            $table->enum('tipo_conta', ['corrente', 'poupanca'])->default('corrente');
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancos');
    }
};
