<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_temporarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('agenda_visitacao_id')->constrained('agenda_visitacoes');
            $table->integer('quantidade');
            $table->timestamp('expira_em');
            $table->enum('status', ['ativa', 'expirada', 'confirmada'])->default('ativa');
            $table->timestamps();

            $table->index(['agenda_visitacao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_temporarias');
    }
};
