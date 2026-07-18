<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Fiscal\GestaoFiscalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Login único da plataforma (Escopo v2, seção 2.2)
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'mostrarFormulario'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| Painel de gestão fiscal (sistema interno)
|--------------------------------------------------------------------------
| O tenant vem sempre do usuário autenticado (ver SetTenantContext) - o
| {empresa} na URL é só cosmético/legível, não decide o contexto.
*/
Route::middleware(['auth', 'tenant'])->prefix('fiscal/{empresa}')->group(function () {
    Route::get('/painel', function (string $empresa) {
        return view('fiscal.painel', ['empresaSlug' => $empresa]);
    });

    Route::get('/relatorio', [GestaoFiscalController::class, 'relatorio']);
    Route::get('/vendas-nao-fiscais', [GestaoFiscalController::class, 'vendasNaoFiscais']);
    Route::post('/documentos/{documentoId}/cancelar', [GestaoFiscalController::class, 'cancelar']);
    Route::post('/inutilizacoes', [GestaoFiscalController::class, 'inutilizar']);
    Route::post('/vendas/{vendaId}/importar', [GestaoFiscalController::class, 'importarVendaNaoFiscal']);
    Route::get('/documentos/{documentoId}/reimprimir', [GestaoFiscalController::class, 'reimprimir']);
    Route::get('/exportar/xmls', [GestaoFiscalController::class, 'exportarXmls']);
    Route::get('/exportar/relatorio-contador', [GestaoFiscalController::class, 'exportarRelatorioContador']);
});
