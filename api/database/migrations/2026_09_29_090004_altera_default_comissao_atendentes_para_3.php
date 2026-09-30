<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atendentes', function (Blueprint $table) {
            $table->decimal('percentual_comissao', 5, 2)->default(3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('atendentes', function (Blueprint $table) {
            $table->decimal('percentual_comissao', 5, 2)->default(5)->change();
        });
    }
};
