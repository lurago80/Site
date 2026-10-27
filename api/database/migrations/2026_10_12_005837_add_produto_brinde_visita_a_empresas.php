<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Produto baixado automaticamente a cada visita efetivada (ex.: caneca de chopp
            // de cortesia). Nulo = recurso desligado, nenhuma baixa automática acontece.
            $table->foreignId('produto_brinde_visita_id')->nullable()->after('custo_visita_padrao')
                ->constrained('produtos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produto_brinde_visita_id');
        });
    }
};
