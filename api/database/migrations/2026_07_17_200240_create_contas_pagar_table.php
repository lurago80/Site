<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores');
            $table->decimal('valor', 10, 2);
            $table->date('vencimento');
            $table->enum('status', ['em_aberto', 'pago', 'atrasado'])->default('em_aberto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_pagar');
    }
};
