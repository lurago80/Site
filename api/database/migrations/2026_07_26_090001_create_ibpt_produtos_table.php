<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela do IBPT (Instituto Brasileiro de Planejamento e
     * Tributação) - alíquotas aproximadas de tributos por NCM, exigidas
     * na nota fiscal/cupom pela Lei da Transparência Fiscal (Lei
     * 12.741/2012). É uma tabela OFICIAL, importada em bloco a partir
     * do .csv que o IBPT distribui - global, sem empresa_id/RLS (mesma
     * lógica de tab_cclasstrib/tab_ccredpres/planos).
     */
    public function up(): void
    {
        Schema::create('ibpt_produtos', function (Blueprint $table) {
            $table->id();
            // NCM tem 8 dígitos, mas NBS (serviço, tipo=1) chega a 9 e
            // LC 116 (tipo=2) usa formato menor (ex.: "0101") - 10 dá
            // margem para todos os tipos vistos na tabela real do IBPT.
            $table->string('codigo', 10);
            $table->string('ex', 2)->nullable();
            $table->unsignedTinyInteger('tipo')->default(0); // 0 = NCM (produto), 1 = NBS (serviço), 2 = LC 116
            $table->string('descricao', 500)->nullable();
            $table->decimal('aliquota_nacional_federal', 6, 2)->nullable();
            $table->decimal('aliquota_importados_federal', 6, 2)->nullable();
            $table->decimal('aliquota_estadual', 6, 2)->nullable();
            $table->decimal('aliquota_municipal', 6, 2)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->string('chave', 20)->nullable(); // hash de validação exigido pelo IBPT
            $table->string('versao', 20)->nullable();
            $table->string('fonte', 100)->nullable();
            $table->timestamps();

            $table->unique(['codigo', 'ex']);
            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ibpt_produtos');
    }
};
