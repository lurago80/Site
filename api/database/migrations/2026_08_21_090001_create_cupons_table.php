<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('codigo', 40);
            $table->enum('tipo', ['percentual', 'valor_fixo']);
            $table->decimal('valor', 10, 2);
            $table->date('valido_ate')->nullable();
            // null = uso ilimitado.
            $table->unsignedInteger('limite_uso')->nullable();
            $table->unsignedInteger('usos_realizados')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            // Código só precisa ser único dentro da empresa - duas lojas
            // diferentes na mesma plataforma podem usar o mesmo código.
            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupons');
    }
};
