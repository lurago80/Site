<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->foreignId('compra_id')->nullable()->after('venda_id')->constrained('compras');
        });

        // Devolução empresa->fornecedor não tem venda associada - a origem
        // passa a ser compra_id. Exatamente um entre venda_id/compra_id
        // deve estar preenchido (validado na aplicação, não no schema).
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->foreignId('venda_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compra_id');
            $table->foreignId('venda_id')->nullable(false)->change();
        });
    }
};
