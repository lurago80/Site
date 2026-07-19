<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia com uma página 403 dedicada (resources/views/errors/403.blade.php)
 * o usuário cujo cadastro foi desativado, ou cuja empresa foi suspensa,
 * DEPOIS de já ter uma sessão de login válida - o LoginController já
 * impede login nesses casos, mas sem esta checagem uma sessão aberta
 * antes da suspensão continuava navegando normalmente até fazer logout.
 *
 * Aplicado só nas rotas que renderizam página HTML (painel/caixa), não
 * nas rotas de API JSON do mesmo grupo - o front-end delas não trata
 * uma resposta 403 em HTML, só em JSON.
 */
class EnsureContaAtiva
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            if (! $user->ativo || $user->empresa?->status !== 'ativa') {
                abort(403, 'Sua conta está inativa ou sua empresa foi suspensa. Fale com o administrador da sua empresa ou com o suporte.');
            }
        }

        return $next($request);
    }
}
