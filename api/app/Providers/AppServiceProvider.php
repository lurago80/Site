<?php

namespace App\Providers;

use App\Models\CertificadoDigital;
use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\User;
use App\Models\Venda;
use App\Observers\AuditLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Models sensíveis (fiscal, financeiro, cliente, usuário) que exigem
     * log de auditoria em toda criação/alteração/exclusão - ver Escopo v2,
     * seção 4. Registrado aqui (e não via trait no boot() de cada model)
     * porque static::observe() dentro do próprio boot() do model colide
     * com o sistema de atributos #[Fillable] do Laravel 13.
     */
    private const MODELS_AUDITADOS = [
        Cliente::class,
        Venda::class,
        User::class,
        ContaPagar::class,
        ContaReceber::class,
        CertificadoDigital::class,
        ConfigFiscal::class,
        DocumentoFiscal::class,
        DocumentoFiscalItem::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::MODELS_AUDITADOS as $model) {
            $model::observe(AuditLogObserver::class);
        }
    }
}
