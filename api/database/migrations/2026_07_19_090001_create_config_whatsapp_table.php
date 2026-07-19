<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciais de envio de WhatsApp POR EMPRESA - mesma lógica do
     * módulo de pagamento: cada empresa escolhe o provedor (Z-API,
     * pago, integração REST simples; ou Baileys, gratuito mas exige
     * infraestrutura própria - sessão de WhatsApp Web via QR code).
     */
    public function up(): void
    {
        Schema::create('config_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas');
            $table->enum('provider', ['zapi', 'baileys'])->default('zapi');
            $table->string('instance_id')->nullable(); // Z-API: id da instância
            $table->text('token')->nullable(); // criptografado (cast 'encrypted')
            $table->text('client_token')->nullable(); // Z-API: Client-Token da conta (criptografado)
            $table->boolean('ativo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_whatsapp');
    }
};
