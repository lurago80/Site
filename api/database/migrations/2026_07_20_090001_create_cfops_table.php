<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela oficial e global do CFOP (Código Fiscal de Operações e
     * Prestações, Ajuste SINIEF s/nº de 15/12/1970) - mesma lógica de
     * ibpt_produtos/tab_cclasstrib/tab_ccredpres: uma única tabela
     * nacional compartilhada por todas as empresas, sem empresa_id/RLS.
     */
    public function up(): void
    {
        Schema::create('cfops', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique(); // ex.: "5102" (sem pontuação)
            $table->string('descricao', 500);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfops');
    }
};
