<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Venda mínima em unidades, somando todas as variações do produto
            // (ex.: caixa fechada de 6 cervejas - o cliente escolhe livremente
            // os sabores, mas o total precisa bater esse mínimo).
            $table->unsignedInteger('quantidade_minima_venda')->nullable()->after('estoque_minimo');
        });

        // varchar(10) só cabia P/M/G/GG - variações agora também guardam
        // nomes de sabor (ex. "Pilsen", "Vermelha"), sem doctrine/dbal
        // disponível pra usar ->change().
        DB::statement('ALTER TABLE produto_variacoes ALTER COLUMN tamanho TYPE VARCHAR(40)');
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('quantidade_minima_venda');
        });

        DB::statement('ALTER TABLE produto_variacoes ALTER COLUMN tamanho TYPE VARCHAR(10)');
    }
};
