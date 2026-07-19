<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciais de gateway de pagamento POR EMPRESA - cada empresa
     * cliente da plataforma pode usar um gateway diferente (taxas
     * variam por negociação própria de cada uma), mesma lógica de
     * isolamento multi-tenant do resto do sistema.
     */
    public function up(): void
    {
        Schema::create('config_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas');
            $table->enum('gateway', ['mercadopago', 'pagseguro', 'cielo'])->default('mercadopago');
            $table->enum('ambiente', ['sandbox', 'producao'])->default('sandbox');
            $table->text('access_token')->nullable(); // criptografado (cast 'encrypted')
            $table->string('public_key')->nullable();
            $table->string('client_id')->nullable(); // Cielo: merchantId
            $table->text('client_secret')->nullable(); // Cielo: merchantKey (criptografado)
            $table->boolean('ativo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_pagamento');
    }
};
