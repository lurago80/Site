<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos fiscais completos por produto (Escopo v2, decisão de
     * 2026-07-25, baseado no documento do cliente sobre a Reforma
     * Tributária/LC 214/2025): hoje o CFOP e IBS/CBS da nota fiscal
     * são resolvidos no nível do documento (NfePhpFiscalGateway),
     * com valores padrão fixos (cClassTrib sempre '000001'). Isso
     * muda para ser configurável por produto.
     */
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // ---- Campos gerais ----
            $table->integer('estoque_minimo')->nullable()->after('estoque_atual');
            $table->string('imagem_url')->nullable()->after('descricao');
            $table->boolean('pesavel')->default(false)->after('unidade');
            $table->decimal('valor_atacado', 10, 2)->nullable()->after('preco_venda');
            $table->string('codigo_barras')->nullable()->after('codigo');
            $table->decimal('peso_liquido', 10, 3)->nullable()->after('valor_atacado');
            $table->decimal('peso_bruto', 10, 3)->nullable()->after('peso_liquido');
            $table->enum('tipo_produto_fiscal', ['consumo', 'materia_prima', 'produto', 'servico', 'brinde'])
                ->default('produto')->after('tipo');

            // ---- Regime antigo: ICMS ----
            $table->string('cfop_interestadual', 4)->nullable()->after('cfop_padrao');
            $table->string('cest', 7)->nullable()->after('ncm');
            $table->string('cst_origem', 1)->nullable();
            $table->string('cst_icms', 2)->nullable();
            $table->decimal('aliquota_icms', 5, 2)->nullable();
            $table->decimal('fcp_percentual', 5, 2)->nullable();
            $table->decimal('mva_percentual', 5, 2)->nullable();
            $table->decimal('reducao_base_calculo_icms', 5, 2)->nullable();
            $table->string('grupo_fiscal')->nullable();
            $table->string('codigo_beneficio_fiscal')->nullable();

            // ---- Regime antigo: PIS/COFINS ----
            $table->string('cst_pis', 2)->nullable();
            $table->decimal('aliquota_pis', 5, 2)->nullable();
            $table->string('cst_cofins', 2)->nullable();
            $table->decimal('aliquota_cofins', 5, 2)->nullable();
            $table->string('natureza_receita_pis_cofins')->nullable();

            // ---- Regime antigo: IPI ----
            $table->string('cst_ipi', 2)->nullable();
            $table->decimal('aliquota_ipi', 5, 2)->nullable();
            $table->string('codigo_enquadramento_ipi')->nullable();

            // ---- Novo regime: IBS/CBS ----
            $table->enum('situacao_novo_regime', ['0', '1', '2'])->default('0');
            $table->string('cst_ibs_cbs', 3)->nullable();
            $table->foreignId('cclasstrib_id')->nullable()->constrained('tab_cclasstrib');
            $table->decimal('aliquota_ibs', 5, 2)->nullable();
            $table->decimal('aliquota_cbs', 5, 2)->nullable();
            $table->decimal('reducao_base_calculo_ibs', 5, 2)->nullable();
            $table->decimal('reducao_base_calculo_cbs', 5, 2)->nullable();
            $table->decimal('percentual_credito_ibs', 5, 2)->nullable();
            $table->decimal('percentual_credito_cbs', 5, 2)->nullable();
            $table->foreignId('ccredpres_id')->nullable()->constrained('tab_ccredpres');

            // ---- Imposto Seletivo (IS) ----
            $table->boolean('sujeito_imposto_seletivo')->default(false);
            $table->enum('tipo_imposto_seletivo', [
                'veiculos', 'cigarros', 'bebidas_alcoolicas', 'bebidas_acucaradas',
                'combustiveis_fosseis', 'bens_minerais',
            ])->nullable();
            $table->string('cclasstrib_is', 6)->nullable();
            $table->decimal('aliquota_is', 5, 2)->nullable();

            // ---- Destinação e crédito ----
            $table->enum('destinacao_tributaria', ['RV', 'UC', 'AT', 'SV'])->nullable();
            $table->enum('tipo_credito', ['IN', 'PA', 'NE'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cclasstrib_id');
            $table->dropConstrainedForeignId('ccredpres_id');
            $table->dropColumn([
                'estoque_minimo', 'imagem_url', 'pesavel', 'valor_atacado', 'codigo_barras',
                'peso_liquido', 'peso_bruto', 'tipo_produto_fiscal',
                'cfop_interestadual', 'cest', 'cst_origem', 'cst_icms', 'aliquota_icms',
                'fcp_percentual', 'mva_percentual', 'reducao_base_calculo_icms', 'grupo_fiscal',
                'codigo_beneficio_fiscal', 'cst_pis', 'aliquota_pis', 'cst_cofins', 'aliquota_cofins',
                'natureza_receita_pis_cofins', 'cst_ipi', 'aliquota_ipi', 'codigo_enquadramento_ipi',
                'situacao_novo_regime', 'cst_ibs_cbs', 'aliquota_ibs', 'aliquota_cbs',
                'reducao_base_calculo_ibs', 'reducao_base_calculo_cbs', 'percentual_credito_ibs',
                'percentual_credito_cbs', 'sujeito_imposto_seletivo', 'tipo_imposto_seletivo',
                'cclasstrib_is', 'aliquota_is', 'destinacao_tributaria', 'tipo_credito',
            ]);
        });
    }
};
