<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico de cada mensagem de WhatsApp enviada - além de
     * auditoria, é a base para cobrar a empresa por mensagem enviada
     * no futuro (contagem por empresa/mês).
     */
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('venda_id')->nullable()->constrained('vendas');
            $table->enum('canal', ['whatsapp'])->default('whatsapp');
            $table->enum('tipo', ['confirmacao_agendamento', 'lembrete_visita', 'outro'])->default('outro');
            $table->string('telefone');
            $table->text('mensagem');
            $table->enum('provider', ['zapi', 'baileys', 'simulado'])->default('simulado');
            $table->enum('status', ['enviado', 'falha'])->default('falha');
            $table->string('referencia_externa')->nullable();
            $table->jsonb('payload_retorno')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
