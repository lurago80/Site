<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\DashboardController;
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
    Route::get('/nfces-disponiveis-para-nfe', [GestaoFiscalController::class, 'nfcesDisponiveisParaNfe']);
    Route::post('/documentos/{documentoId}/cancelar', [GestaoFiscalController::class, 'cancelar']);
    Route::post('/inutilizacoes', [GestaoFiscalController::class, 'inutilizar']);
    Route::post('/vendas/{vendaId}/importar', [GestaoFiscalController::class, 'importarVendaNaoFiscal']);
    Route::post('/nfces/{documentoNfceId}/importar-para-nfe', [GestaoFiscalController::class, 'importarVendaNfce']);
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
    Route::get('/formas-pagamento', [PdvController::class, 'formasPagamento']);
    Route::post('/vendas', [PdvController::class, 'finalizar']);
});

/*
|--------------------------------------------------------------------------
| Dashboard administrativo (cadastros, agenda, financeiro, relatórios)
|--------------------------------------------------------------------------
| O tenant vem sempre do usuário autenticado - mesmo padrão dos demais.
*/
Route::middleware(['auth', 'tenant'])->prefix('dashboard/{empresa}')->group(function () {
    Route::get('/painel', [DashboardController::class, 'painel']);
    Route::get('/indicadores', [DashboardController::class, 'indicadores']);

    Route::get('/agenda', [DashboardController::class, 'agenda']);
    Route::post('/agenda', [DashboardController::class, 'criarAgenda']);

    Route::get('/produtos', [DashboardController::class, 'produtos']);
    Route::post('/produtos', [DashboardController::class, 'criarProduto']);
    Route::put('/produtos/{produtoId}', [DashboardController::class, 'atualizarProduto']);

    Route::get('/clientes', [DashboardController::class, 'clientes']);
    Route::post('/clientes', [DashboardController::class, 'criarCliente']);
    Route::put('/clientes/{clienteId}', [DashboardController::class, 'atualizarCliente']);

    Route::get('/vendedores', [DashboardController::class, 'vendedores']);
    Route::post('/vendedores', [DashboardController::class, 'criarVendedor']);

    Route::get('/fornecedores', [DashboardController::class, 'fornecedores']);
    Route::post('/fornecedores', [DashboardController::class, 'criarFornecedor']);
    Route::put('/fornecedores/{fornecedorId}', [DashboardController::class, 'atualizarFornecedor']);

    Route::get('/contas-pagar', [DashboardController::class, 'contasPagar']);
    Route::post('/contas-pagar', [DashboardController::class, 'criarContaPagar']);
    Route::put('/contas-pagar/{contaId}/pagar', [DashboardController::class, 'marcarContaPagarPaga']);

    Route::get('/contas-receber', [DashboardController::class, 'contasReceber']);
    Route::post('/contas-receber', [DashboardController::class, 'criarContaReceber']);
    Route::put('/contas-receber/{contaId}/pagar', [DashboardController::class, 'marcarContaReceberPaga']);

    Route::get('/usuarios', [DashboardController::class, 'usuarios']);
    Route::post('/usuarios', [DashboardController::class, 'criarUsuario']);
    Route::put('/usuarios/{usuarioId}', [DashboardController::class, 'atualizarUsuario']);

    Route::get('/config-fiscal', [DashboardController::class, 'configFiscal']);
    Route::put('/config-fiscal', [DashboardController::class, 'atualizarConfigFiscal']);

    Route::get('/certificado', [DashboardController::class, 'certificado']);
    Route::post('/certificado', [DashboardController::class, 'salvarCertificado']);

    Route::get('/formas-pagamento', [DashboardController::class, 'formasPagamento']);
    Route::post('/formas-pagamento', [DashboardController::class, 'criarFormaPagamento']);
    Route::put('/formas-pagamento/{formaId}', [DashboardController::class, 'atualizarFormaPagamento']);

    Route::get('/config-pagamento', [DashboardController::class, 'configPagamento']);
    Route::put('/config-pagamento', [DashboardController::class, 'atualizarConfigPagamento']);
    Route::get('/config-whatsapp', [DashboardController::class, 'configWhatsapp']);
    Route::put('/config-whatsapp', [DashboardController::class, 'atualizarConfigWhatsapp']);
    Route::get('/whatsapp-baileys/status', [DashboardController::class, 'baileysStatus']);
    Route::post('/whatsapp-baileys/iniciar', [DashboardController::class, 'baileysIniciar']);
    Route::post('/whatsapp-baileys/desconectar', [DashboardController::class, 'baileysDesconectar']);
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
    Route::put('/assinaturas/{assinaturaId}', [SuperAdminController::class, 'atualizarStatusAssinatura']);
    Route::put('/assinaturas/{assinaturaId}/baixar', [SuperAdminController::class, 'baixarAssinaturaManual']);
    Route::get('/config-assinatura', [SuperAdminController::class, 'configAssinatura']);
    Route::put('/config-assinatura', [SuperAdminController::class, 'atualizarConfigAssinatura']);
});
