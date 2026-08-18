<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('fornecedor_id')->constrained('fornecedores');
            $table->string('numero_nota')->nullable();
            $table->string('serie_nota')->nullable();
            $table->string('chave_acesso', 44)->nullable()->unique();
            $table->enum('tipo_entrada', ['manual', 'xml']);
            $table->date('data_emissao')->nullable();
            $table->date('data_entrada');
            $table->decimal('valor_produtos', 10, 2)->default(0);
            $table->decimal('valor_frete', 10, 2)->default(0);
            $table->decimal('valor_desconto', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->enum('status', ['pendente', 'confirmada', 'cancelada'])->default('pendente');
            $table->text('xml_conteudo')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'data_entrada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
