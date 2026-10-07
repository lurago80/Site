<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Itens "à escolha" com o mesmo nome de grupo formam uma escolha só (ex.: grupo "Copo" com COPO WINDSOR
        // e COPO CALDERETA: o cliente leva um OU outro e o limite do grupo vale para a soma dos dois).
        // Sem grupo, cada produto é o seu próprio grupo (comportamento anterior).
        Schema::table('kit_componentes', function (Blueprint $table) {
            $table->string('grupo', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kit_componentes', function (Blueprint $table) {
            $table->dropColumn('grupo');
        });
    }
};
