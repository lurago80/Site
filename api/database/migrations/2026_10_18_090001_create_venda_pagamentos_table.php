<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagamento misto do PDV: uma venda pode ser paga combinando várias
     * formas (ex.: metade dinheiro, metade cartão) - cada forma usada vira
     * uma linha aqui. `vendas.forma_pagamento_id` continua existindo e passa
     * a valer só quando a venda teve exatamente 1 forma (fica null com 2+).
     */
    public function up(): void
    {
        Schema::create('venda_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('venda_id')->constrained('vendas');
            $table->foreignId('forma_pagamento_id')->constrained('formas_pagamento');
            $table->decimal('valor', 10, 2);
            // Só preenchido quando a forma é dinheiro - usado para calcular o troco.
            $table->decimal('valor_recebido', 10, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['empresa_id', 'venda_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_pagamentos');
    }
};
