<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuários com perfil "caixa" são restritos ao PDV - não podem acessar
 * dashboard, gestão fiscal nem qualquer outra área administrativa.
 */
class EnsureNaoSomentePdv
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->perfil === 'caixa', 403, 'Este usuário só tem acesso ao PDV.');

        return $next($request);
    }
}
