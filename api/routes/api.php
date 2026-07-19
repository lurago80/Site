<?php

use App\Http\Controllers\Loja\CatalogoController;
use App\Http\Controllers\Loja\CheckoutController;
use App\Http\Controllers\Loja\ReservaController;
use App\Http\Controllers\Webhooks\WebhookAssinaturaController;
use App\Http\Controllers\Webhooks\WebhookPagamentoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Loja pública (por empresa, sem login)
|--------------------------------------------------------------------------
| O tenant vem do slug {empresa} na URL — ver App\Http\Middleware\SetTenantContext.
*/
Route::middleware(['tenant', 'throttle:60,1'])->prefix('loja/{empresa}')->group(function () {
    Route::get('/produtos', [CatalogoController::class, 'produtos']);
    Route::get('/agenda', [CatalogoController::class, 'agenda']);
});

// Escrita (gera reserva/venda de verdade) - limite mais apertado que a
// simples navegação do catálogo acima, para dificultar spam de vendas
// ou reservas falsas.
Route::middleware(['tenant', 'throttle:20,1'])->prefix('loja/{empresa}')->group(function () {
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Webhooks de gateways de pagamento (públicos, sem login/tenant - ver
| App\Http\Controllers\Webhooks\WebhookPagamentoController)
|--------------------------------------------------------------------------
| Limite generoso (gateways reenviam em rajada quando há retry), mas
| ainda finito - evita que o endpoint fique exposto sem nenhum teto.
*/
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/webhooks/pagamento/mercadopago', [WebhookPagamentoController::class, 'mercadoPago']);
    Route::post('/webhooks/assinatura/asaas', [WebhookAssinaturaController::class, 'asaas']);
});
