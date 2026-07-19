<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->string('historico')->nullable()->after('fornecedor_id');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->string('historico')->nullable()->after('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropColumn('historico');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropColumn('historico');
        });
    }
};
