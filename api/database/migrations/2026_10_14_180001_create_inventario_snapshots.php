<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->date('data');
            $table->foreignId('produto_id')->constrained();
            $table->foreignId('produto_variacao_id')->nullable()->constrained('produto_variacoes');
            // Nome e tamanho guardados aqui (não só via relação): se o produto for
            // renomeado ou excluído depois, o inventário antigo continua legível.
            $table->string('nome');
            $table->string('tamanho')->nullable();
            $table->integer('estoque');
            $table->timestamps();

            $table->unique(['empresa_id', 'data', 'produto_id', 'produto_variacao_id'], 'inventario_snapshots_unico');
            $table->index(['empresa_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_snapshots');
    }
};
