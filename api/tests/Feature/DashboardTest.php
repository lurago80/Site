<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Dashboard administrativo (Escopo v2, seção 2.2): cadastros, agenda,
 * financeiro e relatórios da empresa.
 */
class DashboardTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private User $atendente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Dashboard Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'dashboard-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@dashboard-teste.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $this->atendente = User::create([
            'name' => 'Atendente Teste',
            'email' => 'atendente@dashboard-teste.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'atendente',
        ]);

        $this->asEmpresa($this->empresa->id);
    }

    public function test_painel_carrega(): void
    {
        $response = $this->actingAs($this->admin)->get("/dashboard/{$this->empresa->slug}/painel");

        $response->assertOk();
        $response->assertSee('Dashboard');
    }

    public function test_visitante_nao_autenticado_e_redirecionado_ao_login(): void
    {
        $response = $this->get("/dashboard/{$this->empresa->slug}/painel");

        $response->assertRedirect('/login');
    }

    public function test_indicadores_retorna_estatisticas_da_empresa(): void
    {
        Venda::create([
            'empresa_id' => $this->empresa->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 100, 'data_venda' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/indicadores");

        $response->assertOk();
        $this->assertSame('100.00', $response->json('vendas_mes'));
    }

    public function test_cadastra_horario_na_agenda(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/agenda", [
            'data_hora' => now()->addDay()->toDateTimeString(),
            'vagas_total' => 10,
            'valor_visita' => 60,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'aberta');
    }

    public function test_lista_agenda_da_propria_empresa(): void
    {
        AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'vagas_reservadas' => 0, 'status' => 'aberta', 'valor_visita' => 50,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/agenda");

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_cadastra_produto(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/produtos", [
            'nome' => 'Produto Teste',
            'tipo' => 'fisico',
            'preco_venda' => 25.00,
            'estoque_atual' => 50,
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Produto Teste');
    }

    public function test_lista_clientes_da_propria_empresa(): void
    {
        Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cliente Teste', 'consentimento_lgpd' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/clientes");

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_cadastra_vendedor(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Vendedor Teste',
            'percentual_comissao' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Vendedor Teste');
    }

    public function test_lanca_conta_a_pagar_e_marca_como_paga(): void
    {
        $criar = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/contas-pagar", [
            'valor' => 200.00,
            'vencimento' => now()->addWeek()->toDateString(),
        ]);
        $criar->assertCreated()->assertJsonPath('status', 'em_aberto');

        $contaId = $criar->json('id');

        $pagar = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/contas-pagar/{$contaId}/pagar");
        $pagar->assertOk()->assertJsonPath('status', 'pago');
    }

    public function test_lanca_conta_a_receber(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/contas-receber", [
            'valor' => 150.00,
            'vencimento' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('status', 'em_aberto');
    }

    public function test_admin_cadastra_usuario(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/usuarios", [
            'name' => 'Novo Usuário',
            'email' => 'novo@dashboard-teste.com',
            'password' => 'senha12345',
            'perfil' => 'caixa',
        ]);

        $response->assertCreated()->assertJsonPath('perfil', 'caixa');
    }

    public function test_atendente_nao_pode_gerenciar_usuarios(): void
    {
        $response = $this->actingAs($this->atendente)->getJson("/dashboard/{$this->empresa->slug}/usuarios");

        $response->assertStatus(403);
    }

    public function test_atendente_nao_pode_cadastrar_usuario(): void
    {
        $response = $this->actingAs($this->atendente)->postJson("/dashboard/{$this->empresa->slug}/usuarios", [
            'name' => 'Tentativa', 'email' => 'x@dashboard-teste.com', 'password' => 'senha12345', 'perfil' => 'admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_dashboard_de_uma_empresa_nao_mostra_dados_de_outra(): void
    {
        $this->asSuperAdmin();
        $outraEmpresa = Empresa::create([
            'razao_social' => 'Outra Empresa', 'cnpj' => '22.222.222/0001-22',
            'slug' => 'outra-empresa-dash', 'plano_id' => $this->empresa->plano_id, 'status' => 'ativa',
        ]);
        $this->asEmpresa($outraEmpresa->id);
        Produto::create([
            'empresa_id' => $outraEmpresa->id, 'nome' => 'Produto da Outra Empresa',
            'tipo' => 'fisico', 'preco_venda' => 10,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/produtos");

        $response->assertOk()->assertJsonCount(0);
    }
}
