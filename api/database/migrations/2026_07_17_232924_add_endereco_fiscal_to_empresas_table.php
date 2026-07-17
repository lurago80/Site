<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('uf', 2)->nullable()->after('segmento');
            $table->string('municipio')->nullable()->after('uf');
            $table->string('codigo_ibge_municipio', 7)->nullable()->after('municipio');
            $table->string('cep', 9)->nullable()->after('codigo_ibge_municipio');
            $table->string('logradouro')->nullable()->after('cep');
            $table->string('numero', 20)->nullable()->after('logradouro');
            $table->string('bairro')->nullable()->after('numero');
            $table->string('complemento')->nullable()->after('bairro');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'uf', 'municipio', 'codigo_ibge_municipio', 'cep',
                'logradouro', 'numero', 'bairro', 'complemento',
            ]);
        });
    }
};
