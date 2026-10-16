<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A DeltaPag (plataforma Zoov/Bempaggo) não manda nenhum dado no corpo
     * do webhook de cobrança - só um POST vazio na `notificationUrl` que a
     * gente mesmo informa ao criar a cobrança. Por isso embutimos nossa
     * própria referência (gerada antes de chamar a API, diferente do id
     * que a DeltaPag atribui) na query string dessa URL, e guardamos aqui
     * para o webhook conseguir achar a Cobranca correspondente.
     */
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->string('referencia_pedido')->nullable()->after('referencia_externa');
            $table->index(['gateway', 'referencia_pedido']);
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'referencia_pedido']);
            $table->dropColumn('referencia_pedido');
        });
    }
};
