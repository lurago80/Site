<?php

namespace Tests\Unit;

use App\Models\ConfigPagamento;
use App\Services\Pagamento\CieloPagamentoGateway;
use App\Services\Pagamento\MercadoPagoPagamentoGateway;
use App\Services\Pagamento\PagamentoGatewayFactory;
use App\Services\Pagamento\PagSeguroPagamentoGateway;
use App\Services\Pagamento\SimuladoPagamentoGateway;
use App\Services\Pagamento\StonePagamentoGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagamentoGatewayFactoryTest extends TestCase
{
    private PagamentoGatewayFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PagamentoGatewayFactory();
    }

    public function test_sem_config_cai_no_gateway_simulado(): void
    {
        $this->assertInstanceOf(SimuladoPagamentoGateway::class, $this->factory->paraEmpresa(null));
    }

    public function test_config_inativa_cai_no_gateway_simulado(): void
    {
        $config = new ConfigPagamento(['gateway' => 'stone', 'ativo' => false]);

        $this->assertInstanceOf(SimuladoPagamentoGateway::class, $this->factory->paraEmpresa($config));
    }

    #[DataProvider('gatewaysAtivos')]
    public function test_resolve_o_gateway_correto_por_empresa(string $nomeGateway, string $classeEsperada): void
    {
        $config = new ConfigPagamento(['gateway' => $nomeGateway, 'ativo' => true]);

        $this->assertInstanceOf($classeEsperada, $this->factory->paraEmpresa($config));
    }

    public static function gatewaysAtivos(): array
    {
        return [
            'mercadopago' => ['mercadopago', MercadoPagoPagamentoGateway::class],
            'pagseguro' => ['pagseguro', PagSeguroPagamentoGateway::class],
            'cielo' => ['cielo', CieloPagamentoGateway::class],
            'stone' => ['stone', StonePagamentoGateway::class],
        ];
    }
}
