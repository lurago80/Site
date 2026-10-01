<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_fiscal', function (Blueprint $table) {
            // Regime normal: exclui o ICMS destacado da base de PIS/COFINS (tese do STF).
            $table->boolean('pis_cofins_exclui_icms')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('config_fiscal', function (Blueprint $table) {
            $table->dropColumn('pis_cofins_exclui_icms');
        });
    }
};
