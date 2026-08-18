<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_fiscal_itens', function (Blueprint $table) {
            $table->foreignId('item_compra_id')->nullable()->after('item_venda_id')->constrained('itens_compra');
        });
    }

    public function down(): void
    {
        Schema::table('documento_fiscal_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_compra_id');
        });
    }
};
