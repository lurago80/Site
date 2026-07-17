<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\Venda;
use App\Services\Fiscal\EmissaoFiscalService;
use App\Services\Fiscal\FiscalGatewayInterface;
use App\Services\Fiscal\NfePhpFiscalGateway;
use App\Services\Fiscal\SimuladoFiscalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

class EmissaoFiscalServiceTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private Venda $venda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Cervejaria Fiscal Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'cervejaria-fiscal-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->asEmpresa($this->empresa->id);

        ConfigFiscal::create([
            'empresa_id' => $this->empresa->id,
            'crt' => '1',
            'serie_nfce_atual' => '1',
            'numero_nfce_atual' => 0,
            'ambiente_ativo' => 'homologacao',
        ]);

        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cliente Fiscal',
            'consentimento_lgpd' => true,
        ]);

        $produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Chopp Artesanal',
            'tipo' => 'fisico',
            'preco_venda' => 18.00,
        ]);

        $this->venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'canal' => 'pdv',
            'tipo_doc' => 'fiscal',
            'status_pagamento' => 'pago',
            'valor_total' => 36.00,
            'data_venda' => now(),
        ]);

        $this->venda->itens()->create([
            'empresa_id' => $this->empresa->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'valor_unitario' => 18.00,
            'valor_total' => 36.00,
        ]);
    }

    public function test_emite_nfce_via_gateway_simulado_por_padrao(): void
    {
        $service = new EmissaoFiscalService(new SimuladoFiscalGateway());

        $documento = $service->emitir($this->venda->fresh('itens'), 65);

        $this->assertSame('autorizada', $documento->status);
        $this->assertSame(65, $documento->modelo);
        $this->assertSame(1, $documento->numero);
        $this->assertSame(44, strlen($documento->chave_acesso));
        $this->assertNotNull($documento->protocolo_autorizacao);
        $this->assertCount(1, $documento->itens);
    }

    public function test_numeracao_incrementa_a_cada_emissao(): void
    {
        $service = new EmissaoFiscalService(new SimuladoFiscalGateway());

        $doc1 = $service->emitir($this->venda->fresh('itens'), 65);
        $doc2 = $service->emitir($this->venda->fresh('itens'), 65);

        $this->assertSame(1, $doc1->numero);
        $this->assertSame(2, $doc2->numero);

        $configFiscal = ConfigFiscal::where('empresa_id', $this->empresa->id)->first();
        $this->assertSame(2, $configFiscal->numero_nfce_atual);
    }

    public function test_modelo_invalido_lanca_excecao(): void
    {
        $service = new EmissaoFiscalService(new SimuladoFiscalGateway());

        $this->expectException(\InvalidArgumentException::class);

        $service->emitir($this->venda->fresh('itens'), 99);
    }

    public function test_sem_config_fiscal_lanca_excecao(): void
    {
        ConfigFiscal::where('empresa_id', $this->empresa->id)->delete();

        $service = new EmissaoFiscalService(new SimuladoFiscalGateway());

        $this->expectException(\RuntimeException::class);

        $service->emitir($this->venda->fresh('itens'), 65);
    }

    public function test_gateway_nfephp_sinaliza_implementacao_pendente_sem_certificado(): void
    {
        $service = new EmissaoFiscalService(new NfePhpFiscalGateway());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/certificado digital/');

        $service->emitir($this->venda->fresh('itens'), 65);
    }

    public function test_service_container_resolve_gateway_simulado_por_padrao(): void
    {
        $this->assertInstanceOf(SimuladoFiscalGateway::class, app(FiscalGatewayInterface::class));
    }
}
