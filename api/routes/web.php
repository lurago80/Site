<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Fiscal\GestaoFiscalController;
use App\Http\Controllers\Pdv\PdvController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
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

/*
|--------------------------------------------------------------------------
| PDV (frente de caixa)
|--------------------------------------------------------------------------
| O tenant vem sempre do usuário autenticado - mesmo padrão do painel fiscal.
*/
Route::middleware(['auth', 'tenant'])->prefix('pdv/{empresa}')->group(function () {
    Route::get('/caixa', [PdvController::class, 'caixa']);
    Route::get('/produtos', [PdvController::class, 'produtos']);
    Route::get('/agenda', [PdvController::class, 'agenda']);
    Route::get('/vendedores', [PdvController::class, 'vendedores']);
    Route::post('/vendas', [PdvController::class, 'finalizar']);
});

/*
|--------------------------------------------------------------------------
| Painel Super Admin (uso interno da equipe da plataforma)
|--------------------------------------------------------------------------
| Restrito a usuários com perfil super_admin - ver EnsureSuperAdmin.
*/
Route::middleware(['auth', 'tenant', 'super_admin'])->prefix('superadmin')->group(function () {
    Route::get('/painel', [SuperAdminController::class, 'painel']);
    Route::get('/empresas', [SuperAdminController::class, 'empresas']);
    Route::post('/empresas', [SuperAdminController::class, 'criarEmpresa']);
    Route::put('/empresas/{empresaId}', [SuperAdminController::class, 'atualizarEmpresa']);
    Route::get('/planos', [SuperAdminController::class, 'planos']);
    Route::post('/planos', [SuperAdminController::class, 'criarPlano']);
    Route::get('/assinaturas', [SuperAdminController::class, 'assinaturas']);
    Route::post('/assinaturas', [SuperAdminController::class, 'criarAssinatura']);
});
