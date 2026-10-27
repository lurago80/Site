<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            // Marcado quando o EnviarAlertaWhatsappJob confirma o envio (status 'enviado' na
            // notificação) - nulo enquanto não enviou, nunca tentou, ou falhou.
            $table->timestamp('whatsapp_enviado_em')->nullable()->after('lida_em');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn('whatsapp_enviado_em');
        });
    }
};
