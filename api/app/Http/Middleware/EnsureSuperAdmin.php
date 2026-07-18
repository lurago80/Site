<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o painel Super Admin (gestão de empresas/planos/assinaturas
 * de toda a plataforma) a usuários com perfil super_admin - ver Escopo
 * v2, seção 2.2.
 */
class EnsureSuperAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Acesso restrito ao Super Admin.');

        return $next($request);
    }
}
