<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('venda_id')->constrained('vendas');
            $table->unsignedTinyInteger('modelo'); // 55 = NFe, 65 = NFC-e
            $table->string('serie');
            $table->unsignedInteger('numero');
            $table->string('chave_acesso', 44)->nullable();
            $table->enum('ambiente', ['producao', 'homologacao']);
            $table->enum('status', ['autorizada', 'cancelada', 'denegada', 'contingencia', 'rejeitada'])->default('contingencia');
            $table->string('protocolo_autorizacao')->nullable();
            $table->string('natureza_operacao')->nullable();
            $table->string('cfop_geral', 4)->nullable();
            $table->decimal('valor_produtos', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('frete', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('valor_icms', 10, 2)->default(0);
            $table->decimal('valor_pis', 10, 2)->default(0);
            $table->decimal('valor_cofins', 10, 2)->default(0);
            $table->text('xml_path')->nullable();
            $table->text('danfe_path')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamp('data_cancelamento')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'modelo', 'serie', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_fiscais');
    }
};
