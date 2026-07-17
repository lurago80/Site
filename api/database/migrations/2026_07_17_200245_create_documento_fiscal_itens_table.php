<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_fiscal_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('documento_fiscal_id')->constrained('documentos_fiscais');
            $table->foreignId('item_venda_id')->nullable()->constrained('itens_venda');
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->string('ncm', 8)->nullable();
            $table->string('cfop', 4)->nullable();
            $table->string('cst_csosn', 4)->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('valor_total', 10, 2);
            $table->decimal('base_calculo_icms', 10, 2)->nullable();
            $table->decimal('aliquota_icms', 5, 2)->nullable();
            $table->decimal('valor_icms', 10, 2)->nullable();
            $table->decimal('valor_pis', 10, 2)->nullable();
            $table->decimal('valor_cofins', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_fiscal_itens');
    }
};
