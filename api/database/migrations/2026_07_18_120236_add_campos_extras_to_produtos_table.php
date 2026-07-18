<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('codigo')->nullable()->after('nome'); // SKU/código interno
            $table->string('unidade', 6)->default('UN')->after('tipo');
            $table->decimal('preco_custo', 10, 2)->nullable()->after('preco_venda');
            $table->string('categoria')->nullable()->after('descricao');
            $table->boolean('ativo')->default(true)->after('estoque_atual');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'unidade', 'preco_custo', 'categoria', 'ativo']);
        });
    }
};
