<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->foreignId('plano_conta_id')->nullable()->after('fornecedor_id')->constrained('plano_contas');
            $table->foreignId('banco_id')->nullable()->after('plano_conta_id')->constrained('bancos');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->foreignId('plano_conta_id')->nullable()->after('cliente_id')->constrained('plano_contas');
            $table->foreignId('banco_id')->nullable()->after('plano_conta_id')->constrained('bancos');
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plano_conta_id');
            $table->dropConstrainedForeignId('banco_id');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plano_conta_id');
            $table->dropConstrainedForeignId('banco_id');
        });
    }
};
