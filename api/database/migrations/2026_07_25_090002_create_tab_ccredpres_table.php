<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de Código de Crédito Presumido do IBS/CBS (cCredPres) -
     * para produtos/operações com direito a crédito presumido (ex.:
     * setor automotivo incentivado, ZFM, agropecuária). Tabela oficial
     * global, mesma lógica de tab_cclasstrib - sem seed inicial
     * (opcional, só preenchido quando aplicável; cadastrado sob
     * demanda pelo admin).
     */
    public function up(): void
    {
        Schema::create('tab_ccredpres', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 6)->unique();
            $table->string('descricao');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tab_ccredpres');
    }
};
