<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->string('codigo_produto_fornecedor')->nullable();
            $table->string('descricao_xml')->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('valor_unitario', 10, 4);
            $table->decimal('valor_total', 10, 2);
            $table->string('ncm')->nullable();
            $table->string('cfop')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_compra');
    }
};
