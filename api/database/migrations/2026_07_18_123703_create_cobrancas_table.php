<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de cada tentativa de cobrança feita num gateway externo -
     * separado de `venda` porque uma venda pode ter mais de uma
     * tentativa de cobrança (ex.: Pix expirou, cliente tenta de novo).
     */
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('venda_id')->constrained('vendas');
            $table->enum('gateway', ['mercadopago', 'pagseguro', 'cielo', 'simulado']);
            $table->enum('metodo', ['pix', 'cartao_credito', 'cartao_debito']);
            $table->string('referencia_externa')->nullable(); // id da cobrança no gateway
            $table->enum('status', ['pendente', 'aprovado', 'recusado', 'cancelado', 'expirado'])->default('pendente');
            $table->decimal('valor', 10, 2);
            $table->text('qr_code')->nullable(); // Pix copia-e-cola
            $table->text('qr_code_base64')->nullable(); // Pix imagem do QR
            $table->string('link_pagamento')->nullable(); // checkout de cartão, quando aplicável
            $table->jsonb('payload_retorno')->nullable(); // resposta crua do gateway, para depuração
            $table->timestamp('expira_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
