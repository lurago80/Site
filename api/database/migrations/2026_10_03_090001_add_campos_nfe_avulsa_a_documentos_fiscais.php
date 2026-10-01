<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            // Destinatário da NFe emitida direto na retaguarda (sem venda de PDV).
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            // modFrete da NFe: 0 remetente (CIF), 1 destinatário (FOB), 2 terceiros, 3/4 transporte próprio, 9 sem frete.
            $table->unsignedSmallInteger('modalidade_frete')->default(9);
            $table->jsonb('transportadora')->nullable();
            $table->text('informacoes_adicionais')->nullable();
            // indPres da NFe (1 presencial, 2 internet, 9 outros) e tPag (ex.: 01, 17, 90).
            $table->unsignedSmallInteger('indicador_presenca')->nullable();
            $table->string('tpag', 2)->nullable();
        });

        Schema::table('documento_fiscal_itens', function (Blueprint $table) {
            $table->string('descricao', 255)->nullable();
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            // Parte do frete rateada neste item (a soma dos itens fecha com o frete da nota).
            $table->decimal('valor_frete', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('documento_fiscal_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variacao_id');
            $table->dropColumn(['descricao', 'valor_frete']);
        });

        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn(['modalidade_frete', 'transportadora', 'informacoes_adicionais', 'indicador_presenca', 'tpag']);
        });
    }
};
