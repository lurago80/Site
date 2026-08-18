<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vendas em dinheiro no PDV agora lançam uma linha tipo=venda em `caixas`
 * (ver App\Services\Pdv\CaixaService::registrarVenda) - o saldo do caixa
 * precisa refletir o troco físico entrando na gaveta, não só
 * abertura/sangria/suprimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE caixas DROP CONSTRAINT caixas_tipo_check');
        DB::statement("ALTER TABLE caixas ADD CONSTRAINT caixas_tipo_check CHECK (tipo IN ('abertura', 'fechamento', 'sangria', 'suprimento', 'venda'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE caixas DROP CONSTRAINT caixas_tipo_check');
        DB::statement("ALTER TABLE caixas ADD CONSTRAINT caixas_tipo_check CHECK (tipo IN ('abertura', 'fechamento', 'sangria', 'suprimento'))");
    }
};
