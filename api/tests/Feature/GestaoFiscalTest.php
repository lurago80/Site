<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\Venda;
use App\Services\Fiscal\EmissaoFiscalService;
use App\Services\Fiscal\SimuladoFiscalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

class GestaoFiscalTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Gestão Fiscal Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'gestao-fiscal-teste',
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
    }

    private function criarVenda(string $tipoDoc = 'fiscal'): Venda
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cliente Teste',
            'consentimento_lgpd' => true,
        ]);

        $produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto Teste',
            'tipo' => 'fisico',
            'preco_venda' => 10.00,
        ]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'canal' => 'pdv',
            'tipo_doc' => $tipoDoc,
            'status_pagamento' => 'pago',
            'valor_total' => 10.00,
            'data_venda' => now(),
        ]);

        $venda->itens()->create([
            'empresa_id' => $this->empresa->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 10.00,
            'valor_total' => 10.00,
        ]);

        return $venda;
    }

    public function test_relatorio_lista_documentos_fiscais_da_empresa(): void
    {
        $venda = $this->criarVenda();
        (new EmissaoFiscalService(new SimuladoFiscalGateway()))->emitir($venda, 65);

        $response = $this->getJson("/api/fiscal/{$this->empresa->slug}/relatorio");

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonPath('0.status', 'autorizada');
    }

    public function test_relatorio_filtra_por_status(): void
    {
        $venda = $this->criarVenda();
        (new EmissaoFiscalService(new SimuladoFiscalGateway()))->emitir($venda, 65);

        $response = $this->getJson("/api/fiscal/{$this->empresa->slug}/relatorio?status=cancelada");

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_cancelar_documento_autorizado(): void
    {
        $venda = $this->criarVenda();
        $documento = (new EmissaoFiscalService(new SimuladoFiscalGateway()))->emitir($venda, 65);

        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/documentos/{$documento->id}/cancelar", [
            'justificativa' => 'Cliente desistiu da compra no momento da entrega',
        ]);

        $response->assertOk()->assertJsonPath('status', 'cancelada');
    }

    public function test_cancelar_com_justificativa_curta_retorna_422(): void
    {
        $venda = $this->criarVenda();
        $documento = (new EmissaoFiscalService(new SimuladoFiscalGateway()))->emitir($venda, 65);

        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/documentos/{$documento->id}/cancelar", [
            'justificativa' => 'muito curta',
        ]);

        $response->assertStatus(422);
    }

    public function test_nao_permite_cancelar_documento_ja_cancelado(): void
    {
        $venda = $this->criarVenda();
        $documento = (new EmissaoFiscalService(new SimuladoFiscalGateway()))->emitir($venda, 65);

        $this->postJson("/api/fiscal/{$this->empresa->slug}/documentos/{$documento->id}/cancelar", [
            'justificativa' => 'Primeira tentativa de cancelamento válida',
        ]);

        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/documentos/{$documento->id}/cancelar", [
            'justificativa' => 'Segunda tentativa de cancelamento válida',
        ]);

        $response->assertStatus(422);
    }

    public function test_inutilizar_numeracao(): void
    {
        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/inutilizacoes", [
            'modelo' => 65,
            'serie' => '1',
            'numero_inicial' => 100,
            'numero_final' => 105,
            'justificativa' => 'Pulo de numeração por falha no sistema',
        ]);

        $response->assertCreated()->assertJsonPath('status', 'homologada');
    }

    public function test_inutilizar_com_numero_final_menor_que_inicial_falha(): void
    {
        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/inutilizacoes", [
            'modelo' => 65,
            'serie' => '1',
            'numero_inicial' => 200,
            'numero_final' => 100,
            'justificativa' => 'Pulo de numeração por falha no sistema',
        ]);

        $response->assertStatus(422);
    }

    public function test_lista_vendas_nao_fiscais_pendentes(): void
    {
        $this->criarVenda('nao_fiscal');
        $this->criarVenda('fiscal');

        $response = $this->getJson("/api/fiscal/{$this->empresa->slug}/vendas-nao-fiscais");

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_importar_venda_nao_fiscal_emite_documento(): void
    {
        $venda = $this->criarVenda('nao_fiscal');

        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/importar", [
            'modelo' => 65,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'autorizada');
        $this->assertSame('fiscal', $venda->fresh()->tipo_doc);
    }

    public function test_importar_venda_ja_fiscal_falha(): void
    {
        $venda = $this->criarVenda('fiscal');

        $response = $this->postJson("/api/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/importar", [
            'modelo' => 65,
        ]);

        $response->assertStatus(422);
    }

    public function test_reimprimir_documento_retorna_view_html(): void
    {
        $venda = $this->criarVenda();
        $documento = (new EmissaoFiscalService(new SimuladoFiscalGateway()))->emitir($venda, 65);

        $response = $this->get("/fiscal/{$this->empresa->slug}/documentos/{$documento->id}/reimprimir");

        $response->assertOk();
        $response->assertSee($documento->chave_acesso);
    }

    public function test_painel_carrega(): void
    {
        $response = $this->get("/fiscal/{$this->empresa->slug}/painel");

        $response->assertOk();
        $response->assertSee('Painel de Gestão Fiscal');
    }
}
