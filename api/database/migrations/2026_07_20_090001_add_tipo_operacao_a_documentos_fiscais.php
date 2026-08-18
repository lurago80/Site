<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->string('tipo_operacao', 30)->default('venda')->after('documento_fiscal_origem_id');
            $table->string('direcao_devolucao', 30)->nullable()->after('tipo_operacao');
        });

        DB::table('documentos_fiscais')
            ->whereNotNull('documento_fiscal_origem_id')
            ->where('modelo', 55)
            ->whereIn('documento_fiscal_origem_id', function ($query) {
                $query->select('id')->from('documentos_fiscais')->where('modelo', 65);
            })
            ->update(['tipo_operacao' => 'regularizacao_nfce']);
    }

    public function down(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropColumn(['tipo_operacao', 'direcao_devolucao']);
        });
    }
};
