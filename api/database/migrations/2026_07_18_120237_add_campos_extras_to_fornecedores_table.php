<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('nome_fantasia')->nullable()->after('razao_social');
            $table->string('telefone')->nullable()->after('contato');
            $table->string('email')->nullable()->after('telefone');
            $table->text('endereco')->nullable()->after('email');
            $table->string('inscricao_estadual')->nullable()->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropColumn(['nome_fantasia', 'telefone', 'email', 'endereco', 'inscricao_estadual']);
        });
    }
};
