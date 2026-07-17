<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define, para toda a duração da conexão de banco desta requisição,
 * a empresa (tenant) em uso — reforço aplicado pelas policies de
 * Row-Level Security do PostgreSQL (ver migration enable_row_level_security).
 *
 * Duas origens possíveis do tenant:
 * - Sistema interno (rota autenticada): a empresa vem do usuário logado.
 * - Loja pública (rota com {empresa} no path, sem login): a empresa vem do slug.
 *
 * Sem este middleware nas rotas de negócio, o RLS bloqueia tudo por padrão
 * (current_empresa_id fica vazio) — falha segura, não vazamento.
 */
class SetTenantContext
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresaId = null;
        $isSuperAdmin = false;

        if ($user = $request->user()) {
            $isSuperAdmin = $user->isSuperAdmin();
            $empresaId = $user->empresa_id;
        } elseif ($slug = $request->route('empresa')) {
            $empresaId = Empresa::where('slug', $slug)
                ->where('status', 'ativa')
                ->value('id');
        }

        DB::statement('SET app.current_empresa_id = ?', [(string) ($empresaId ?? '')]);
        DB::statement('SET app.is_super_admin = ?', [$isSuperAdmin ? 'true' : 'false']);

        return $next($request);
    }
}
