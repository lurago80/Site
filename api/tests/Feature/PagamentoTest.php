<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\ConfigPagamento;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use App\Models\Venda;
use App\Services\Pagamento\PagamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Módulo de pagamento (Escopo v2, decisão de 2026-07-18): gateway
 * escolhido por empresa, Pix real via Mercado Pago, formas de
 * pagamento cadastráveis.
 */
class PagamentoTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Venda $venda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Pagamento Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'pagamento-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@pagamento-teste.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $this->asEmpresa($this->empresa->id);

        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cliente Teste', 'consentimento_lgpd' => true,
        ]);

        $this->venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'canal' => 'site',
            'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pendente',
            'valor_total' => 100.00,
            'data_venda' => now(),
        ]);
    }

    public function test_sem_gateway_configurado_usa_simulado_e_aprova_na_hora(): void
    {
        $service = app(PagamentoService::class);

        $cobranca = $service->criarCobrancaPix($this->venda);

        $this->assertSame('simulado', $cobranca->gateway);
        $this->assertSame('aprovado', $cobranca->status);
        $this->assertSame('pago', $this->venda->fresh()->status_pagamento);
    }

    public function test_gateway_configurado_mas_inativo_tambem_usa_simulado(): void
    {
        ConfigPagamento::create([
            'empresa_id' => $this->empresa->id,
            'gateway' => 'mercadopago',
            'ambiente' => 'sandbox',
            'access_token' => 'token-teste',
            'ativo' => false,
        ]);

        $service = app(PagamentoService::class);
        $cobranca = $service->criarCobrancaPix($this->venda);

        $this->assertSame('simulado', $cobranca->gateway);
    }

    public function test_mercadopago_cria_cobranca_pix_real_via_http_fake(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments' => Http::response([
                'id' => 123456789,
                'status' => 'pending',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '00020126PIXCOPIAECOLAFAKE',
                        'qr_code_base64' => 'BASE64FAKE',
                    ],
                ],
            ], 201),
        ]);

        ConfigPagamento::create([
            'empresa_id' => $this->empresa->id,
            'gateway' => 'mercadopago',
            'ambiente' => 'sandbox',
            'access_token' => 'token-teste',
            'ativo' => true,
        ]);

        $service = app(PagamentoService::class);
        $cobranca = $service->criarCobrancaPix($this->venda);

        $this->assertSame('mercadopago', $cobranca->gateway);
        $this->assertSame('pendente', $cobranca->status);
        $this->assertSame('123456789', $cobranca->referencia_externa);
        $this->assertSame('00020126PIXCOPIAECOLAFAKE', $cobranca->qr_code);
        $this->assertSame('pendente', $this->venda->fresh()->status_pagamento);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-teste'));
    }

    public function test_mercadopago_cria_cobranca_cartao_real_via_http_fake(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments' => Http::response([
                'id' => 987654321,
                'status' => 'approved',
            ], 201),
        ]);

        ConfigPagamento::create([
            'empresa_id' => $this->empresa->id,
            'gateway' => 'mercadopago',
            'ambiente' => 'sandbox',
            'access_token' => 'token-teste',
            'ativo' => true,
        ]);

        $service = app(PagamentoService::class);
        $cobranca = $service->criarCobrancaCartao($this->venda, 'card-token-fake', 2, 'cartao_credito');

        $this->assertSame('mercadopago', $cobranca->gateway);
        $this->assertSame('cartao_credito', $cobranca->metodo);
        $this->assertSame('aprovado', $cobranca->status);
        $this->assertSame('987654321', $cobranca->referencia_externa);
        $this->assertSame('pago', $this->venda->fresh()->status_pagamento);
    }

    public function test_checkout_publico_com_token_de_cartao_usa_pagamento_service(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments' => Http::response(['id' => 555, 'status' => 'approved'], 201),
        ]);

        ConfigPagamento::create([
            'empresa_id' => $this->empresa->id,
            'gateway' => 'mercadopago',
            'ambiente' => 'sandbox',
            'access_token' => 'token-teste',
            'ativo' => true,
        ]);

        \App\Models\Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Cartão', 'tipo' => 'fisico', 'preco_venda' => 30,
        ]);
        $produto = \App\Models\Produto::where('empresa_id', $this->empresa->id)->where('nome', 'Produto Cartão')->first();

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => ['nome' => 'Cliente Cartão', 'consentimento_lgpd' => true],
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            'forma_pagamento' => 'cartao',
            'cartao_token' => 'card-token-fake',
            'cartao_parcelas' => 1,
        ]);

        $response->assertCreated();
        $this->assertSame('pago', $response->json('status_pagamento'));
        $this->assertSame('aprovado', $response->json('cobranca.status'));
    }

    public function test_webhook_mercadopago_atualiza_cobranca_para_aprovado(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/123456789' => Http::response(['status' => 'approved'], 200),
        ]);

        ConfigPagamento::create([
            'empresa_id' => $this->empresa->id,
            'gateway' => 'mercadopago',
            'ambiente' => 'sandbox',
            'access_token' => 'token-teste',
            'ativo' => true,
        ]);

        $cobranca = Cobranca::create([
            'empresa_id' => $this->empresa->id,
            'venda_id' => $this->venda->id,
            'gateway' => 'mercadopago',
            'metodo' => 'pix',
            'referencia_externa' => '123456789',
            'status' => 'pendente',
            'valor' => 100.00,
        ]);

        $response = $this->postJson('/api/webhooks/pagamento/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => '123456789'],
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('aprovado', $cobranca->fresh()->status);
        $this->assertSame('pago', $this->venda->fresh()->status_pagamento);
    }

    public function test_webhook_com_referencia_desconhecida_nao_quebra(): void
    {
        $response = $this->postJson('/api/webhooks/pagamento/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => 'nao-existe'],
        ]);

        $response->assertOk()->assertJsonPath('encontrado', false);
    }

    public function test_webhook_ignora_notificacoes_que_nao_sao_de_pagamento(): void
    {
        $response = $this->postJson('/api/webhooks/pagamento/mercadopago', [
            'type' => 'merchant_order',
        ]);

        $response->assertOk()->assertJsonPath('ignorado', true);
    }

    public function test_admin_cadastra_forma_de_pagamento(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/formas-pagamento", [
            'descricao' => 'Pix',
            'tipo' => 'pix',
            'codigo_tpag' => '17',
        ]);

        $response->assertCreated()->assertJsonPath('codigo_tpag', '17');
    }

    public function test_lista_formas_de_pagamento_nao_exige_admin(): void
    {
        $atendente = User::create([
            'name' => 'Atendente', 'email' => 'atendente@pagamento-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'atendente',
        ]);

        $response = $this->actingAs($atendente)->getJson("/dashboard/{$this->empresa->slug}/formas-pagamento");

        $response->assertOk();
    }

    public function test_atendente_nao_pode_cadastrar_forma_de_pagamento(): void
    {
        $atendente = User::create([
            'name' => 'Atendente', 'email' => 'atendente2@pagamento-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'atendente',
        ]);

        $response = $this->actingAs($atendente)->postJson("/dashboard/{$this->empresa->slug}/formas-pagamento", [
            'descricao' => 'Pix', 'tipo' => 'pix', 'codigo_tpag' => '17',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_configura_gateway_de_pagamento(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-pagamento", [
            'gateway' => 'mercadopago',
            'ambiente' => 'sandbox',
            'access_token' => 'meu-token-secreto',
            'ativo' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('tem_credenciais', true);
        $response->assertJsonMissing(['access_token' => 'meu-token-secreto']);
    }

    public function test_config_pagamento_nunca_devolve_o_token_salvo(): void
    {
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-pagamento", [
            'gateway' => 'mercadopago', 'ambiente' => 'sandbox', 'access_token' => 'segredo-123', 'ativo' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/config-pagamento");

        $response->assertOk()->assertJsonPath('tem_credenciais', true);
        $this->assertStringNotContainsString('segredo-123', $response->getContent());
    }

    public function test_atualizar_config_pagamento_sem_novo_token_preserva_o_existente(): void
    {
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-pagamento", [
            'gateway' => 'mercadopago', 'ambiente' => 'sandbox', 'access_token' => 'token-original', 'ativo' => true,
        ]);

        // reenvia sem access_token (simula o front-end não reenviando o campo)
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-pagamento", [
            'gateway' => 'mercadopago', 'ambiente' => 'producao', 'ativo' => true,
        ]);

        $config = ConfigPagamento::where('empresa_id', $this->empresa->id)->first();
        $this->assertSame('token-original', $config->access_token);
        $this->assertSame('producao', $config->ambiente);
    }

    public function test_pdv_finaliza_venda_com_forma_de_pagamento(): void
    {
        \App\Models\Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Teste', 'tipo' => 'fisico', 'preco_venda' => 20,
        ]);
        $forma = \App\Models\FormaPagamento::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Dinheiro',
            'tipo' => 'dinheiro', 'codigo_tpag' => '01', 'ativo' => true,
        ]);
        $produto = \App\Models\Produto::where('empresa_id', $this->empresa->id)->first();

        $caixa = User::create([
            'name' => 'Caixa', 'email' => 'caixa@pagamento-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'caixa',
        ]);

        $response = $this->actingAs($caixa)->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'forma_pagamento_id' => $forma->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ]);

        $response->assertCreated();
        $this->assertSame($forma->id, Venda::find($response->json('id'))->forma_pagamento_id);
    }
}
