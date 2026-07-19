<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `atendentes` foi criada na concepção inicial do projeto (migration
     * 2026_07_17_200234) mas nunca chegou a ter model, controller ou
     * rota - o conceito de "atendente" acabou implementado como
     * `users.perfil = 'atendente'`. Tabela sempre esteve vazia (achado
     * da auditoria de 2026-07-23) - removida por segurança de esquema
     * (menos superfície não usada para confundir futuras leituras do
     * banco).
     */
    public function up(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON atendentes');
        Schema::dropIfExists('atendentes');
    }

    public function down(): void
    {
        Schema::create('atendentes', function ($table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->string('nome');
            $table->timestamps();
        });
    }
};
