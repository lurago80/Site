<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            // Lote importado (ex.: planilha de 2000 códigos promocionais) - diferencia
            // do cupom cadastrado manualmente no painel, pra não misturar os dois
            // na tela de gestão (2000 linhas não cabem numa lista sem paginação).
            $table->boolean('importado')->default(false)->after('ativo');
            $table->timestamp('usado_em')->nullable()->after('usos_realizados');
            $table->foreignId('usado_por_cliente_id')->nullable()->after('usado_em')->constrained('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usado_por_cliente_id');
            $table->dropColumn(['usado_em', 'importado']);
        });
    }
};
