<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('cnpj', 18)->unique();
            $table->string('slug')->unique();
            $table->string('segmento')->nullable();
            $table->boolean('modulo_agendamento_ativo')->default(false);
            $table->foreignId('plano_id')->constrained('planos');
            $table->enum('status', ['ativa', 'suspensa', 'cancelada'])->default('ativa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
