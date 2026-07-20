<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
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

Route::middleware('throttle:5,1')->group(function () {
    Route::get('/esqueci-senha', [PasswordResetController::class, 'mostrarFormularioEsqueci'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetController::class, 'enviarLink'])->name('password.email');
    Route::get('/redefinir-senha/{token}', [PasswordResetController::class, 'mostrarFormularioRedefinir'])->name('password.reset');
    Route::post('/redefinir-senha', [PasswordResetController::class, 'redefinir'])->name('password.update');
});

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
    })->middleware('conta_ativa');

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
    Route::get('/caixa', [PdvController::class, 'caixa'])->middleware('conta_ativa');
    Route::get('/produtos', [PdvController::class, 'produtos']);
    Route::get('/agenda', [PdvController::class, 'agenda']);
    Route::get('/vendedores', [PdvController::class, 'vendedores']);
    Route::get('/atendentes', [PdvController::class, 'atendentes']);
    Route::get('/formas-pagamento', [PdvController::class, 'formasPagamento']);
    Route::post('/vendas', [PdvController::class, 'finalizar']);

    Route::get('/caixa-status', [PdvController::class, 'caixaStatus']);
    Route::post('/caixa-abrir', [PdvController::class, 'caixaAbrir']);
    Route::post('/caixa-fechar', [PdvController::class, 'caixaFechar']);
    Route::post('/caixa-sangria', [PdvController::class, 'caixaSangria']);
    Route::post('/caixa-suprimento', [PdvController::class, 'caixaSuprimento']);
    Route::get('/caixa-extrato', [PdvController::class, 'caixaExtrato']);
});

/*
|--------------------------------------------------------------------------
| Dashboard administrativo (cadastros, agenda, financeiro, relatórios)
|--------------------------------------------------------------------------
| O tenant vem sempre do usuário autenticado - mesmo padrão dos demais.
*/
Route::middleware(['auth', 'tenant'])->prefix('dashboard/{empresa}')->group(function () {
    Route::get('/painel', [DashboardController::class, 'painel'])->middleware('conta_ativa');
    Route::get('/indicadores', [DashboardController::class, 'indicadores']);

    Route::get('/agenda', [DashboardController::class, 'agenda']);
    Route::post('/agenda', [DashboardController::class, 'criarAgenda']);

    Route::get('/produtos', [DashboardController::class, 'produtos']);
    Route::post('/produtos', [DashboardController::class, 'criarProduto']);
    Route::put('/produtos/{produtoId}', [DashboardController::class, 'atualizarProduto']);
    Route::get('/tab-cclasstrib', [DashboardController::class, 'tabClassTrib']);
    Route::get('/tab-ccredpres', [DashboardController::class, 'tabCredPres']);

    Route::get('/clientes', [DashboardController::class, 'clientes']);
    Route::post('/clientes', [DashboardController::class, 'criarCliente']);
    Route::put('/clientes/{clienteId}', [DashboardController::class, 'atualizarCliente']);

    Route::get('/vendedores', [DashboardController::class, 'vendedores']);
    Route::post('/vendedores', [DashboardController::class, 'criarVendedor']);

    Route::get('/atendentes', [DashboardController::class, 'atendentes']);
    Route::post('/atendentes', [DashboardController::class, 'criarAtendente']);
    Route::put('/atendentes/{atendenteId}', [DashboardController::class, 'atualizarAtendente']);
    Route::get('/atendentes-relatorio', [DashboardController::class, 'relatorioAtendentes']);

    Route::get('/fornecedores', [DashboardController::class, 'fornecedores']);
    Route::post('/fornecedores', [DashboardController::class, 'criarFornecedor']);
    Route::put('/fornecedores/{fornecedorId}', [DashboardController::class, 'atualizarFornecedor']);

    Route::get('/contas-pagar', [DashboardController::class, 'contasPagar']);
    Route::post('/contas-pagar', [DashboardController::class, 'criarContaPagar']);
    Route::put('/contas-pagar/{contaId}/pagar', [DashboardController::class, 'marcarContaPagarPaga']);

    Route::get('/contas-receber', [DashboardController::class, 'contasReceber']);
    Route::post('/contas-receber', [DashboardController::class, 'criarContaReceber']);
    Route::put('/contas-receber/{contaId}/pagar', [DashboardController::class, 'marcarContaReceberPaga']);

    Route::get('/grupos', [DashboardController::class, 'grupos']);
    Route::post('/grupos', [DashboardController::class, 'criarGrupo']);
    Route::put('/grupos/{grupoId}', [DashboardController::class, 'atualizarGrupo']);
    Route::get('/grupos-relatorio', [DashboardController::class, 'relatorioGrupos']);

    Route::get('/plano-contas', [DashboardController::class, 'planoContas']);
    Route::post('/plano-contas', [DashboardController::class, 'criarPlanoContas']);
    Route::put('/plano-contas/{planoContaId}', [DashboardController::class, 'atualizarPlanoContas']);
    Route::get('/plano-contas-relatorio', [DashboardController::class, 'relatorioPlanoContas']);

    Route::get('/bancos', [DashboardController::class, 'bancos']);
    Route::post('/bancos', [DashboardController::class, 'criarBanco']);
    Route::put('/bancos/{bancoId}', [DashboardController::class, 'atualizarBanco']);
    Route::post('/bancos/{bancoId}/movimentos', [DashboardController::class, 'lancarMovimentoBancario']);
    Route::get('/bancos/{bancoId}/extrato', [DashboardController::class, 'extratoBanco']);

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

    Route::get('/config-loja', [DashboardController::class, 'configLoja']);
    Route::put('/config-loja', [DashboardController::class, 'atualizarConfigLoja']);

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
    Route::put('/planos/{planoId}', [SuperAdminController::class, 'atualizarPlano']);
    Route::get('/assinaturas', [SuperAdminController::class, 'assinaturas']);
    Route::post('/assinaturas', [SuperAdminController::class, 'criarAssinatura']);
    Route::put('/assinaturas/{assinaturaId}', [SuperAdminController::class, 'atualizarStatusAssinatura']);
    Route::put('/assinaturas/{assinaturaId}/baixar', [SuperAdminController::class, 'baixarAssinaturaManual']);
    Route::get('/config-assinatura', [SuperAdminController::class, 'configAssinatura']);
    Route::put('/config-assinatura', [SuperAdminController::class, 'atualizarConfigAssinatura']);
});
