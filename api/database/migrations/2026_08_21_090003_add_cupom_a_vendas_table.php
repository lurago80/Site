<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('cupom_id')->nullable()->after('forma_pagamento_id')->constrained('cupons');
            // valor_total já é o total COM desconto aplicado - este campo
            // é só o registro do quanto foi abatido, para relatório/auditoria.
            $table->decimal('valor_desconto', 10, 2)->nullable()->after('valor_total');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cupom_id');
            $table->dropColumn('valor_desconto');
        });
    }
};
