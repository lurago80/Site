<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identidade visual por empresa (Escopo v2, item pendente desde a
     * loja pública ganhar front-end de verdade): logo e cor primária
     * da própria empresa cliente, usados na loja pública dela - hoje
     * só a plataforma (sistema interno) tinha marca própria.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('segmento');
            $table->string('cor_primaria', 7)->nullable()->after('logo_url'); // hex, ex: #394285
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'cor_primaria']);
        });
    }
};
