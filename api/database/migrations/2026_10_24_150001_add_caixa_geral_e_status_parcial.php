<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancos', function (Blueprint $table) {
            $table->boolean('eh_caixa_geral')->default(false)->after('tipo_conta');
        });

        DB::statement("ALTER TABLE contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_status_check");
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_status_check CHECK (status IN ('em_aberto', 'parcial', 'pago', 'atrasado'))");

        DB::statement("ALTER TABLE contas_receber DROP CONSTRAINT IF EXISTS contas_receber_status_check");
        DB::statement("ALTER TABLE contas_receber ADD CONSTRAINT contas_receber_status_check CHECK (status IN ('em_aberto', 'parcial', 'pago', 'atrasado'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE contas_pagar SET status = 'em_aberto' WHERE status = 'parcial'");
        DB::statement("ALTER TABLE contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_status_check");
        DB::statement("ALTER TABLE contas_pagar ADD CONSTRAINT contas_pagar_status_check CHECK (status IN ('em_aberto', 'pago', 'atrasado'))");

        DB::statement("UPDATE contas_receber SET status = 'em_aberto' WHERE status = 'parcial'");
        DB::statement("ALTER TABLE contas_receber DROP CONSTRAINT IF EXISTS contas_receber_status_check");
        DB::statement("ALTER TABLE contas_receber ADD CONSTRAINT contas_receber_status_check CHECK (status IN ('em_aberto', 'pago', 'atrasado'))");

        Schema::table('bancos', function (Blueprint $table) {
            $table->dropColumn('eh_caixa_geral');
        });
    }
};
