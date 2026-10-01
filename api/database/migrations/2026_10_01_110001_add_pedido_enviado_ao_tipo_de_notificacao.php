<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE notificacoes DROP CONSTRAINT IF EXISTS notificacoes_tipo_check');
        DB::statement(
            "ALTER TABLE notificacoes ADD CONSTRAINT notificacoes_tipo_check CHECK (tipo IN ('confirmacao_agendamento', 'lembrete_visita', 'pedido_enviado', 'outro'))"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE notificacoes SET tipo = 'outro' WHERE tipo = 'pedido_enviado'");
        DB::statement('ALTER TABLE notificacoes DROP CONSTRAINT IF EXISTS notificacoes_tipo_check');
        DB::statement(
            "ALTER TABLE notificacoes ADD CONSTRAINT notificacoes_tipo_check CHECK (tipo IN ('confirmacao_agendamento', 'lembrete_visita', 'outro'))"
        );
    }
};
