<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            $table->string('telefone')->nullable()->after('nome');
            $table->decimal('percentual_comissao', 5, 2)->default(5)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            $table->dropColumn('telefone');
            $table->decimal('percentual_comissao', 5, 2)->default(0)->change();
        });
    }
};
