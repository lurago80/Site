<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('ncm', 8)->nullable()->after('tipo');
            // CFOP "padrão" para venda interna (dentro do estado) - o
            // fiscal calcula a variante correta (interestadual/NFC-e
            // regularização) a partir deste valor, ver Fiscal\CfopResolver.
            $table->string('cfop_padrao', 4)->nullable()->after('ncm');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['ncm', 'cfop_padrao']);
        });
    }
};
