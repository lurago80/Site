<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o "ovo e galinha" da autenticação sob Row-Level Security: para
 * descobrir a empresa de um usuário é preciso primeiro LER a tabela
 * `users` por e-mail/sessão - mas o RLS bloqueia essa leitura até o
 * tenant estar definido, e o tenant só se define depois de autenticar.
 *
 * Este middleware roda antes de tudo (primeiro no grupo 'web'), abrindo
 * um bypass temporário de RLS só para a fase de resolução de auth
 * (busca por e-mail no login, e recarregamento do usuário a partir da
 * sessão). Em toda rota protegida por App\Http\Middleware\SetTenantContext,
 * esse bypass é fechado de volta ao escopo da empresa correta antes do
 * controller rodar - nenhuma query de negócio acontece nesta janela.
 */
class BootstrapAuthDatabaseContext
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        return $next($request);
    }
}
