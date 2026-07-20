<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de Classificação Tributária (cClassTrib) do IBS/CBS -
     * código de 6 dígitos que vai no XML da NF-e (os 3 primeiros
     * dígitos = CST do novo regime). É uma tabela OFICIAL (Receita/
     * Comitê Gestor do IBS), igual para todas as empresas - por isso
     * fica global, sem empresa_id/RLS (mesma lógica de `planos`).
     *
     * Muda com frequência (~150 códigos, várias revisões desde 2025) -
     * por isso é uma tabela auxiliar administrável, não um enum fixo
     * no código. Semeada aqui só com os códigos mais comuns no varejo/
     * food service - o resto é cadastrado sob demanda.
     */
    public function up(): void
    {
        Schema::create('tab_cclasstrib', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 6)->unique();
            $table->string('descricao');
            $table->string('anexo_lc214')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::table('tab_cclasstrib')->insert([
            ['codigo' => '000001', 'descricao' => 'Tributação integral (padrão para a maioria dos itens)', 'anexo_lc214' => null, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '200003', 'descricao' => 'Alimentação humana (Anexo I)', 'anexo_lc214' => 'Anexo I', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '200047', 'descricao' => 'Bares e Restaurantes', 'anexo_lc214' => null, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '400001', 'descricao' => 'Cesta básica universal (isenção total)', 'anexo_lc214' => null, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '410001', 'descricao' => 'Bonificação em documento fiscal', 'anexo_lc214' => null, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '410002', 'descricao' => 'Transferência entre estabelecimentos do mesmo contribuinte', 'anexo_lc214' => null, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tab_cclasstrib');
    }
};
