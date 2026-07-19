<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Todas as transações envolvendo dinheiro no caixa físico do PDV
     * (Escopo v2, decisão de 2026-07-21): abertura, fechamento, sangria
     * e suprimento - cada evento é uma linha nesta tabela. O caixa
     * "está aberto" quando existe uma linha tipo=abertura sem uma
     * linha tipo=fechamento posterior para a mesma empresa (ver
     * App\Services\Pdv\CaixaService).
     */
    public function up(): void
    {
        Schema::create('caixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('tipo', ['abertura', 'fechamento', 'sangria', 'suprimento']);
            $table->decimal('valor', 12, 2);
            $table->dateTime('data_hora');
            $table->string('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixas');
    }
};
