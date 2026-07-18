<?php

use App\Http\Controllers\Fiscal\GestaoFiscalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Painel de gestão fiscal - páginas navegáveis (a página em si, a
| reimpressão do cupom e os downloads de exportação). As ações que
| mutam dados (cancelar, inutilizar, importar) ficam em routes/api.php,
| chamadas via fetch() a partir da página aqui.
|--------------------------------------------------------------------------
| TODO: sem login ainda - ver aviso no GestaoFiscalController.
*/
Route::middleware(['tenant'])->prefix('fiscal/{empresa}')->group(function () {
    Route::get('/painel', function (string $empresa) {
        return view('fiscal.painel', ['empresaSlug' => $empresa]);
    });
    Route::get('/documentos/{documentoId}/reimprimir', [GestaoFiscalController::class, 'reimprimir']);
    Route::get('/exportar/xmls', [GestaoFiscalController::class, 'exportarXmls']);
    Route::get('/exportar/relatorio-contador', [GestaoFiscalController::class, 'exportarRelatorioContador']);
});
