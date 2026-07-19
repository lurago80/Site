<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Todas as transações bancárias da empresa (Escopo v2, decisão de
     * 2026-07-21) - gera o extrato de cada conta em `bancos`. Pode ser
     * lançada manualmente ou automaticamente ao marcar uma conta a
     * pagar/receber como paga informando o banco usado.
     */
    public function up(): void
    {
        Schema::create('grava_banco', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar');
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber');
            $table->date('data_movimento');
            $table->enum('tipo', ['credito', 'debito']);
            $table->decimal('valor', 12, 2);
            $table->string('descricao')->nullable();
            $table->enum('origem', ['manual', 'conta_pagar', 'conta_receber'])->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grava_banco');
    }
};
