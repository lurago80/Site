<?php

namespace Tests\Feature;

use App\Models\Assinatura;
use App\Models\ConfigAssinatura;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use App\Services\Assinatura\AssinaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Cobrança de assinatura das empresas clientes (Escopo v2, decisão de
 * 2026-07-20): Asaas é a plataforma cobrando cada empresa cliente pela
 * mensalidade (diferente de ConfigPagamento, que é a empresa cliente
 * cobrando seu próprio consumidor final).
 */
class AssinaturaTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private User $superAdmin;

    private Plano $plano;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $this->plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Assinatura Teste',
            'cnpj' => '33.333.333/0001-33',
            'slug' => 'assinatura-teste',
            'plano_id' => $this->plano->id,
            'status' => 'ativa',
        ]);

        $this->superAdmin = User::create([
            'name' => 'Equipe Plataforma',
            'email' => 'super2@plataforma.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => null,
            'perfil' => 'super_admin',
        ]);
    }

    public function test_sem_asaas_configurado_cria_assinatura_manual(): void
    {
        $service = app(AssinaturaService::class);
        $assinatura = $service->criarAssinatura($this->empresa, $this->plano, 'em_dia', now()->toDateString());

        $this->assertSame('em_dia', $assinatura->status_pagamento);
        $this->assertNull($assinatura->asaas_subscription_id);
    }

    public function test_com_asaas_ativo_cria_cliente_e_assinatura_real_via_http_fake(): void
    {
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_123'], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_456', 'nextDueDate' => '2026-08-20'], 200),
        ]);

        ConfigAssinatura::create([
            'provider' => 'asaas', 'ambiente' => 'sandbox', 'api_key' => 'chave-teste', 'ativo' => true,
        ]);

        $service = app(AssinaturaService::class);
        $assinatura = $service->criarAssinatura($this->empresa, $this->plano, 'em_dia', now()->toDateString());

        $this->assertSame('sub_456', $assinatura->asaas_subscription_id);
        $this->assertSame('cus_123', $this->empresa->fresh()->asaas_customer_id);

        Http::assertSent(fn ($request) => $request->hasHeader('access_token', 'chave-teste'));
    }

    public function test_webhook_payment_overdue_suspende_empresa_automaticamente(): void
    {
        ConfigAssinatura::create([
            'provider' => 'asaas', 'ambiente' => 'sandbox', 'api_key' => 'chave-teste', 'ativo' => true,
        ]);

        $assinatura = Assinatura::create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $this->plano->id,
            'status_pagamento' => 'em_dia', 'inicio' => now(), 'asaas_subscription_id' => 'sub_789',
        ]);

        $response = $this->postJson('/api/webhooks/assinatura/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['subscription' => 'sub_789'],
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('atrasado', $assinatura->fresh()->status_pagamento);
        $this->assertSame('suspensa', $this->empresa->fresh()->status);
    }

    public function test_webhook_payment_received_reativa_empresa_suspensa(): void
    {
        $this->empresa->update(['status' => 'suspensa']);

        $assinatura = Assinatura::create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $this->plano->id,
            'status_pagamento' => 'atrasado', 'inicio' => now(), 'asaas_subscription_id' => 'sub_999',
        ]);

        $response = $this->postJson('/api/webhooks/assinatura/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['subscription' => 'sub_999'],
        ]);

        $response->assertOk();
        $this->assertSame('em_dia', $assinatura->fresh()->status_pagamento);
        $this->assertSame('ativa', $this->empresa->fresh()->status);
    }

    public function test_webhook_com_assinatura_desconhecida_nao_quebra(): void
    {
        $response = $this->postJson('/api/webhooks/assinatura/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['subscription' => 'nao-existe'],
        ]);

        $response->assertOk()->assertJsonPath('encontrado', false);
    }

    public function test_super_admin_da_baixa_manual_e_reativa_empresa_suspensa(): void
    {
        $this->empresa->update(['status' => 'suspensa']);

        $assinatura = Assinatura::create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $this->plano->id,
            'status_pagamento' => 'atrasado', 'inicio' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/superadmin/assinaturas/{$assinatura->id}/baixar", []);

        $response->assertOk()->assertJsonPath('status_pagamento', 'em_dia');
        $this->assertSame('ativa', $this->empresa->fresh()->status);
    }

    public function test_super_admin_configura_asaas_e_token_nunca_e_devolvido(): void
    {
        $response = $this->actingAs($this->superAdmin)->putJson('/superadmin/config-assinatura', [
            'provider' => 'asaas', 'ambiente' => 'sandbox', 'api_key' => 'segredo-asaas', 'ativo' => true,
        ]);

        $response->assertOk()->assertJsonPath('tem_credenciais', true);
        $this->assertStringNotContainsString('segredo-asaas', $response->getContent());
    }

    public function test_usuario_comum_nao_acessa_config_assinatura(): void
    {
        $admin = User::create([
            'name' => 'Admin Empresa', 'email' => 'admin@assinatura-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);

        $response = $this->actingAs($admin)->getJson('/superadmin/config-assinatura');

        $response->assertStatus(403);
    }
}
