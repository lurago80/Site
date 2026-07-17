<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_visitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->dateTime('data_hora');
            $table->integer('vagas_total');
            $table->integer('vagas_reservadas')->default(0);
            $table->enum('status', ['aberta', 'lotada', 'cancelada'])->default('aberta');
            $table->decimal('valor_visita', 10, 2);
            $table->timestamps();

            $table->index(['empresa_id', 'data_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_visitacoes');
    }
};
