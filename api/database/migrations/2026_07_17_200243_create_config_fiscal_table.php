<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_fiscal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas');
            $table->string('crt')->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->string('inscricao_municipal')->nullable();
            $table->string('serie_nfe_atual')->default('1');
            $table->unsignedInteger('numero_nfe_atual')->default(0);
            $table->string('serie_nfce_atual')->default('1');
            $table->unsignedInteger('numero_nfce_atual')->default(0);
            $table->string('csc_nfce')->nullable();
            $table->string('id_token_csc')->nullable();
            $table->enum('ambiente_ativo', ['producao', 'homologacao'])->default('homologacao');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_fiscal');
    }
};
