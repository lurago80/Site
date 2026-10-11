<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Celular (só dígitos, com DDD) que recebe os alertas ao administrador por WhatsApp. Vazio = só no painel.
            $table->string('alerta_whatsapp', 20)->nullable()->after('alerta_sangria');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('alerta_whatsapp');
        });
    }
};
