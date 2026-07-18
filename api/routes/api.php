<?php

use App\Http\Controllers\Loja\CatalogoController;
use App\Http\Controllers\Loja\CheckoutController;
use App\Http\Controllers\Loja\ReservaController;
use Illuminate\Support\Facades\Route;

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
