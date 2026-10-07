<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kit de escolha livre (ex.: Kit Coringa = 3 itens, sendo 3 cervejas OU 2 cervejas + 1 copo).
        // Quando preenchido, o cliente monta este total de itens misturando os grupos de escolha e a
        // `quantidade` de cada grupo vira o máximo permitido daquele grupo. Nulo = regra antiga
        // (cada grupo de escolha com a quantidade exata).
        Schema::table('produtos', function (Blueprint $table) {
            $table->unsignedSmallInteger('kit_total_escolhas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('kit_total_escolhas');
        });
    }
};
