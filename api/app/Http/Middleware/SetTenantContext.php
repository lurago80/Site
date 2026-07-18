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

            // A empresa do contexto vem sempre do usuário logado, nunca do
            // slug da URL - mesmo que a rota tenha {empresa}, um usuário
            // autenticado não pode "visitar" o painel de outra empresa só
            // trocando o slug no navegador.
            if ($user->empresa) {
                $request->attributes->set('empresaAtual', $user->empresa);
            }
        } elseif ($slug = $request->route('empresa')) {
            $empresa = Empresa::where('slug', $slug)
                ->where('status', 'ativa')
                ->first();

            abort_if($empresa === null, 404, 'Loja não encontrada.');

            $empresaId = $empresa->id;
            $request->attributes->set('empresaAtual', $empresa);
        }

        DB::statement("SELECT set_config('app.current_empresa_id', ?, false)", [(string) ($empresaId ?? '')]);
        DB::statement("SELECT set_config('app.is_super_admin', ?, false)", [$isSuperAdmin ? 'true' : 'false']);

        return $next($request);
    }
}
