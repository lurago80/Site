<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciais de cobrança de assinatura das empresas clientes - é a
     * PLATAFORMA cobrando cada empresa, não uma empresa cobrando seu
     * cliente final (isso já existe em config_pagamento). Por isso é
     * uma configuração ÚNICA e global, gerenciada só pelo super admin -
     * sem empresa_id, sem RLS (mesma lógica de `planos`/`empresas`,
     * que também são a raiz do multi-tenant, não linhas de uma empresa).
     */
    public function up(): void
    {
        Schema::create('config_assinatura', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['asaas'])->default('asaas');
            $table->enum('ambiente', ['sandbox', 'producao'])->default('sandbox');
            $table->text('api_key')->nullable(); // criptografado (cast 'encrypted')
            $table->boolean('ativo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_assinatura');
    }
};
