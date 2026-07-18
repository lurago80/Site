<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numeracao_inutilizada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->unsignedTinyInteger('modelo');
            $table->string('serie');
            $table->unsignedInteger('numero_inicial');
            $table->unsignedInteger('numero_final');
            $table->text('justificativa');
            $table->enum('status', ['homologada', 'rejeitada'])->default('rejeitada');
            $table->string('protocolo')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numeracao_inutilizada');
    }
};
