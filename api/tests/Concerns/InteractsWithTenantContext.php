<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Helpers para setar o contexto de tenant (Row-Level Security) em testes,
 * espelhando o que App\Http\Middleware\SetTenantContext faz por request.
 */
trait InteractsWithTenantContext
{
    protected function asEmpresa(int $empresaId): void
    {
        DB::statement("SELECT set_config('app.current_empresa_id', ?, false)", [(string) $empresaId]);
        DB::statement("SELECT set_config('app.is_super_admin', 'false', false)");
    }

    protected function asSuperAdmin(): void
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");
    }

    protected function semContextoDeTenant(): void
    {
        DB::statement("SELECT set_config('app.current_empresa_id', '', false)");
        DB::statement("SELECT set_config('app.is_super_admin', 'false', false)");
    }
}
