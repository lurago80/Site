<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores');
            $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento');
            $table->enum('canal', ['site', 'pdv']);
            $table->enum('tipo_doc', ['fiscal', 'nao_fiscal']);
            $table->enum('status_pagamento', ['pendente', 'pago', 'cancelado'])->default('pendente');
            $table->decimal('valor_total', 10, 2);
            $table->decimal('comissao', 10, 2)->nullable();
            $table->timestamp('data_venda')->useCurrent();
            $table->timestamps();

            $table->index(['empresa_id', 'data_venda']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas');
    }
};
