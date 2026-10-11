<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perfil "gerente": acesso ao painel como o administrador nas rotinas do dia a dia
 * (cupons, descontos, atendentes, formas de pagamento) e cancela/reimprime vendas no
 * PDV sempre, mas sem gerenciar usuários, parâmetros, fiscal, bancos e plano de contas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_perfil_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_perfil_check CHECK (perfil IN ('super_admin', 'admin', 'gerente', 'caixa', 'atendente'))");
    }

    public function down(): void
    {
        // Quem era gerente volta a atendente (perfil mais restrito do painel).
        DB::statement("UPDATE users SET perfil = 'atendente' WHERE perfil = 'gerente'");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_perfil_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_perfil_check CHECK (perfil IN ('super_admin', 'admin', 'caixa', 'atendente'))");
    }
};
