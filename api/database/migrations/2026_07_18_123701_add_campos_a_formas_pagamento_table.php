<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            // Código tPag da NFe/NFC-e (01=dinheiro, 03=cartão crédito,
            // 04=cartão débito, 17=Pix, 99=outros...) - sem isso o módulo
            // fiscal não tinha como saber qual código usar e sempre
            // mandava "01" fixo (ver NfePhpFiscalGateway, TODO removido).
            $table->string('codigo_tpag', 2)->default('99')->after('descricao');
            $table->enum('tipo', ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'outro'])
                ->default('outro')->after('codigo_tpag');
            $table->boolean('ativo')->default(true)->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn(['codigo_tpag', 'tipo', 'ativo']);
        });
    }
};
