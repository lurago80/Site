<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            // Venda do PDV paga na carteira: liga o título ao que o gerou, para
            // poder estornar o título junto se a venda for cancelada.
            $table->foreignId('venda_id')->nullable()->after('cliente_id')->constrained('vendas');
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venda_id');
        });
    }
};
