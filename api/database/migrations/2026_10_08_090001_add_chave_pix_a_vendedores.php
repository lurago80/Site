<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            // Chave PIX para o pagamento da comissão (CPF/CNPJ, e-mail, telefone ou chave aleatória; até 77 caracteres).
            $table->string('chave_pix', 77)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            $table->dropColumn('chave_pix');
        });
    }
};
