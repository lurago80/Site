<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tabelas isoladas por empresa_id via Row-Level Security.
     * empresas/planos ficam de fora: são a raiz do multi-tenant, não linhas de uma empresa.
     */
    private array $tabelasComTenant = [
        'users',
        'assinaturas',
        'fornecedores',
        'clientes',
        'produtos',
        'vendedores',
        'atendentes',
        'agenda_visitacoes',
        'reservas_temporarias',
        'formas_pagamento',
        'vendas',
        'itens_venda',
        'contas_pagar',
        'contas_receber',
        'certificados_digitais',
        'config_fiscal',
        'documentos_fiscais',
        'documento_fiscal_itens',
        'logs',
    ];

    public function up(): void
    {
        foreach ($this->tabelasComTenant as $tabela) {
            // FORCE garante que a policy vale até para o dono da tabela (saas_app),
            // não só para roles "externas" - sem isso o RLS não protege o app.
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");

            DB::statement("
                CREATE POLICY tenant_isolation ON {$tabela}
                USING (
                    current_setting('app.is_super_admin', true) = 'true'
                    OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_super_admin', true) = 'true'
                    OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tabelasComTenant as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }
};
