<?php

namespace Tests\Feature;

use App\Console\Commands\EnviarLembretesVisita;
use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\ConfigWhatsapp;
use App\Models\Empresa;
use App\Models\ItemVenda;
use App\Models\Notificacao;
use App\Models\Plano;
use App\Models\User;
use App\Models\Venda;
use App\Services\Notificacao\NotificacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Módulo de notificação WhatsApp (Escopo v2, decisão de 2026-07-19):
 * provedor escolhido por empresa entre Z-API (pago, real) e Baileys
 * (gratuito, ainda stub - depende de microserviço Node.js à parte).
 */
class NotificacaoTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Notificação Teste',
            'cnpj' => '22.222.222/0001-22',
            'slug' => 'notificacao-teste',
            'modulo_agendamento_ativo' => true,
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@notificacao-teste.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $this->asEmpresa($this->empresa->id);

        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cliente Teste',
            'telefone' => '11999998888', 'consentimento_lgpd' => true,
        ]);
    }

    public function test_sem_gateway_configurado_usa_simulado_e_registra_como_enviado(): void
    {
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'status' => 'aberta', 'valor_visita' => 60,
        ]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id,
            'canal' => 'site', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 60, 'data_venda' => now(),
        ]);

        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda->id,
            'agenda_visitacao_id' => $agenda->id, 'quantidade' => 1,
            'valor_unitario' => 60, 'valor_total' => 60,
        ]);

        $service = app(NotificacaoService::class);
        $notificacao = $service->enviarConfirmacaoAgendamento($venda);

        $this->assertNotNull($notificacao);
        $this->assertSame('simulado', $notificacao->provider);
        $this->assertSame('enviado', $notificacao->status);
        $this->assertSame('confirmacao_agendamento', $notificacao->tipo);
    }

    public function test_sem_telefone_do_cliente_nao_envia_nada(): void
    {
        $clienteSemTelefone = Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Sem Telefone', 'consentimento_lgpd' => true,
        ]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $clienteSemTelefone->id,
            'canal' => 'site', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 60, 'data_venda' => now(),
        ]);

        $service = app(NotificacaoService::class);
        $notificacao = $service->enviarConfirmacaoAgendamento($venda);

        $this->assertNull($notificacao);
        $this->assertSame(0, Notificacao::count());
    }

    public function test_zapi_envia_mensagem_real_via_http_fake(): void
    {
        Http::fake([
            'api.z-api.io/*' => Http::response(['zaapId' => 'ZAAP123', 'messageId' => 'MSG123'], 200),
        ]);

        ConfigWhatsapp::create([
            'empresa_id' => $this->empresa->id, 'provider' => 'zapi',
            'instance_id' => 'instancia-teste', 'token' => 'token-teste',
            'client_token' => 'client-token-teste', 'ativo' => true,
        ]);

        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'status' => 'aberta', 'valor_visita' => 60,
        ]);

        $service = app(NotificacaoService::class);
        $notificacao = $service->enviarLembreteVisita($agenda, $this->cliente, $this->cliente->telefone);

        $this->assertSame('zapi', $notificacao->provider);
        $this->assertSame('enviado', $notificacao->status);
        $this->assertSame('ZAAP123', $notificacao->referencia_externa);

        Http::assertSent(fn ($request) => $request->hasHeader('Client-Token', 'client-token-teste'));
    }

    public function test_baileys_configurado_lanca_excecao_de_pendente(): void
    {
        ConfigWhatsapp::create([
            'empresa_id' => $this->empresa->id, 'provider' => 'baileys', 'ativo' => true,
        ]);

        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'status' => 'aberta', 'valor_visita' => 60,
        ]);

        $service = app(NotificacaoService::class);

        $this->expectException(\RuntimeException::class);
        $service->enviarLembreteVisita($agenda, $this->cliente, $this->cliente->telefone);
    }

    public function test_comando_de_lembrete_envia_para_visitas_de_amanha(): void
    {
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay()->setTime(14, 0),
            'vagas_total' => 5, 'status' => 'aberta', 'valor_visita' => 60,
        ]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id,
            'canal' => 'site', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 60, 'data_venda' => now(),
        ]);

        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda->id,
            'agenda_visitacao_id' => $agenda->id, 'quantidade' => 1,
            'valor_unitario' => 60, 'valor_total' => 60,
        ]);

        $this->artisan(EnviarLembretesVisita::class)->assertSuccessful();

        $this->asSuperAdmin();
        $this->assertSame(1, Notificacao::where('tipo', 'lembrete_visita')->count());
    }

    public function test_admin_configura_provedor_de_whatsapp(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-whatsapp", [
            'provider' => 'zapi',
            'instance_id' => 'instancia-abc',
            'token' => 'meu-token-secreto',
            'client_token' => 'meu-client-token',
            'ativo' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('tem_credenciais', true);
        $response->assertJsonMissing(['token' => 'meu-token-secreto']);
    }

    public function test_config_whatsapp_nunca_devolve_o_token_salvo(): void
    {
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-whatsapp", [
            'provider' => 'zapi', 'instance_id' => 'instancia-abc',
            'token' => 'segredo-123', 'client_token' => 'segredo-456', 'ativo' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/config-whatsapp");

        $response->assertOk()->assertJsonPath('tem_credenciais', true);
        $this->assertStringNotContainsString('segredo-123', $response->getContent());
    }

    public function test_atendente_nao_pode_configurar_whatsapp(): void
    {
        $atendente = User::create([
            'name' => 'Atendente', 'email' => 'atendente@notificacao-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'atendente',
        ]);

        $response = $this->actingAs($atendente)->putJson("/dashboard/{$this->empresa->slug}/config-whatsapp", [
            'provider' => 'zapi', 'ativo' => true,
        ]);

        $response->assertStatus(403);
    }
}
