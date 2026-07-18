<?php

use App\Exceptions\VagasIndisponiveisException;
use App\Http\Middleware\BootstrapAuthDatabaseContext;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenantContext::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);

        // Precisa rodar antes até do 'auth' padrão do Laravel - ver
        // App\Http\Middleware\BootstrapAuthDatabaseContext para o motivo.
        $middleware->prependToGroup('web', BootstrapAuthDatabaseContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 'api/*' sempre em JSON (loja pública); demais rotas usam a
        // detecção padrão do Laravel (Accept header) - necessário para o
        // painel fiscal, que vive em routes/web.php (sessão + CSRF) mas
        // seu JS chama os endpoints via fetch esperando JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (VagasIndisponiveisException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 409));
    })->create();
