<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_digitais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->enum('tipo', ['A1', 'A3']);
            $table->text('arquivo_referencia');
            $table->text('senha_criptografada');
            $table->date('validade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_digitais');
    }
};
