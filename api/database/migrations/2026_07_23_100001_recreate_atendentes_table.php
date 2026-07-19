<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recria `atendentes` (removida na auditoria de 2026-07-23 por
     * estar órfã - sem uso real até então). O cliente esclareceu o uso
     * real: no PDV, o "vendedor" é o guia da visita (recebe comissão,
     * já existia) e o "atendente" é quem de fato opera a venda no
     * caixa - são pessoas/papéis diferentes, ambos precisam ficar
     * registrados na venda.
     */
    public function up(): void
    {
        Schema::create('atendentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendentes');
    }
};
