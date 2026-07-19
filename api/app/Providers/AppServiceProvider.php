<?php

namespace App\Providers;

use App\Models\Assinatura;
use App\Models\Banco;
use App\Models\Caixa;
use App\Models\CertificadoDigital;
use App\Models\Cliente;
use App\Models\ConfigAssinatura;
use App\Models\ConfigPagamento;
use App\Models\ConfigWhatsapp;
use App\Models\Empresa;
use App\Models\ConfigFiscal;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\GravaBanco;
use App\Models\NumeracaoInutilizada;
use App\Models\PlanoContas;
use App\Models\User;
use App\Models\Venda;
use App\Observers\AuditLogObserver;
use App\Services\Fiscal\FiscalGatewayInterface;
use App\Services\Fiscal\NfePhpFiscalGateway;
use App\Services\Fiscal\SimuladoFiscalGateway;
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
        NumeracaoInutilizada::class,
        Empresa::class,
        Assinatura::class,
        ConfigPagamento::class,
        ConfigWhatsapp::class,
        ConfigAssinatura::class,
        Banco::class,
        GravaBanco::class,
        Caixa::class,
        PlanoContas::class,
    ];

    public function register(): void
    {
        $this->app->bind(FiscalGatewayInterface::class, function () {
            return config('fiscal.driver') === 'nfephp'
                ? new NfePhpFiscalGateway()
                : new SimuladoFiscalGateway();
        });
    }

    public function boot(): void
    {
        foreach (self::MODELS_AUDITADOS as $model) {
            $model::observe(AuditLogObserver::class);
        }
    }
}
