<?php

use App\Http\Controllers\Loja\CatalogoController;
use App\Http\Controllers\Loja\CheckoutController;
use App\Http\Controllers\Loja\ReservaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sistema interno (login único da plataforma)
|--------------------------------------------------------------------------
| O tenant vem do usuário autenticado — ver App\Http\Middleware\SetTenantContext.
*/
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/me', function (Request $request) {
        return $request->user()->load('empresa');
    });
});

/*
|--------------------------------------------------------------------------
| Loja pública (por empresa, sem login)
|--------------------------------------------------------------------------
| O tenant vem do slug {empresa} na URL — ver App\Http\Middleware\SetTenantContext.
*/
Route::middleware(['tenant'])->prefix('loja/{empresa}')->group(function () {
    Route::get('/produtos', [CatalogoController::class, 'produtos']);
    Route::get('/agenda', [CatalogoController::class, 'agenda']);
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
});
