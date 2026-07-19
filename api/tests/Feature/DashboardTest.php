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

    public function test_admin_cadastra_atendente(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/atendentes", [
            'nome' => 'Atendente Teste',
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Atendente Teste');
    }

    public function test_lista_atendentes_nao_exige_admin(): void
    {
        $response = $this->actingAs($this->atendente)->getJson("/dashboard/{$this->empresa->slug}/atendentes");

        $response->assertOk();
    }

    public function test_atendente_nao_pode_cadastrar_atendente(): void
    {
        $response = $this->actingAs($this->atendente)->postJson("/dashboard/{$this->empresa->slug}/atendentes", [
            'nome' => 'Outro Atendente',
        ]);

        $response->assertStatus(403);
    }

    public function test_relatorio_de_atendentes_soma_vendas(): void
    {
        $atendenteModel = \App\Models\Atendente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Atendente Relatório', 'ativo' => true,
        ]);

        \App\Models\Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendenteModel->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 100, 'data_venda' => now(),
        ]);
        \App\Models\Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendenteModel->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 50, 'data_venda' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/atendentes-relatorio");

        $response->assertOk();
        $dados = collect($response->json())->firstWhere('nome', 'Atendente Relatório');
        $this->assertSame(2, $dados['vendas_count']);
        $this->assertEquals(150.0, $dados['valor_total']);
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

    public function test_conta_a_pagar_registra_historico_e_fornecedor(): void
    {
        $fornecedor = \App\Models\Fornecedor::create([
            'empresa_id' => $this->empresa->id, 'razao_social' => 'Fornecedor Teste',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/contas-pagar", [
            'fornecedor_id' => $fornecedor->id,
            'historico' => 'Compra de insumos - NF 1234',
            'valor' => 300.00,
            'vencimento' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('historico', 'Compra de insumos - NF 1234')
            ->assertJsonPath('fornecedor_id', $fornecedor->id);

        $listagem = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/contas-pagar");
        $listagem->assertOk()->assertJsonPath('0.fornecedor.razao_social', 'Fornecedor Teste');
    }

    public function test_conta_a_receber_registra_historico_e_cliente(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cliente Teste', 'consentimento_lgpd' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/contas-receber", [
            'cliente_id' => $cliente->id,
            'historico' => 'Venda avulsa - pedido 5678',
            'valor' => 250.00,
            'vencimento' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('historico', 'Venda avulsa - pedido 5678')
            ->assertJsonPath('cliente_id', $cliente->id);

        $listagem = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/contas-receber");
        $listagem->assertOk()->assertJsonPath('0.cliente.nome', 'Cliente Teste');
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

    public function test_admin_atualiza_identidade_visual_da_loja(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-loja", [
            'logo_url' => 'https://exemplo.com/logo.png',
            'cor_primaria' => '#ff0000',
        ]);

        $response->assertOk()->assertJsonPath('logo_url', 'https://exemplo.com/logo.png');
        $this->assertSame('#ff0000', $this->empresa->fresh()->cor_primaria);
    }

    public function test_atendente_nao_pode_atualizar_identidade_visual(): void
    {
        $response = $this->actingAs($this->atendente)->putJson("/dashboard/{$this->empresa->slug}/config-loja", [
            'cor_primaria' => '#ff0000',
        ]);

        $response->assertStatus(403);
    }

    public function test_cor_primaria_invalida_e_rejeitada(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-loja", [
            'cor_primaria' => 'vermelho',
        ]);

        $response->assertStatus(422);
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

    public function test_cadastra_produto_com_campos_completos(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/produtos", [
            'codigo' => 'SKU-001',
            'nome' => 'Produto Completo',
            'categoria' => 'Bebidas',
            'tipo' => 'fisico',
            'unidade' => 'CX',
            'preco_venda' => 30.00,
            'preco_custo' => 18.00,
            'estoque_atual' => 20,
            'ncm' => '22030000',
            'cfop_padrao' => '5102',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('codigo', 'SKU-001');
        $response->assertJsonPath('unidade', 'CX');
        $response->assertJsonPath('preco_custo', '18.00');
        $response->assertJsonPath('ativo', true);
    }

    public function test_atualiza_produto_existente(): void
    {
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Original',
            'tipo' => 'fisico', 'preco_venda' => 10,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/produtos/{$produto->id}", [
            'nome' => 'Produto Renomeado',
            'preco_venda' => 15,
        ]);

        $response->assertOk()->assertJsonPath('nome', 'Produto Renomeado');
    }

    public function test_cadastra_produto_vinculado_a_fornecedor(): void
    {
        $fornecedor = \App\Models\Fornecedor::create([
            'empresa_id' => $this->empresa->id, 'razao_social' => 'Fornecedor Teste',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/produtos", [
            'nome' => 'Produto com Fornecedor',
            'tipo' => 'fisico',
            'preco_venda' => 10,
            'fornecedor_id' => $fornecedor->id,
        ]);

        $response->assertCreated();
        $this->assertSame($fornecedor->id, $response->json('fornecedor_id'));
    }

    public function test_cadastra_cliente_completo(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/clientes", [
            'nome' => 'Cliente Completo',
            'cpf_cnpj' => '111.111.111-11',
            'telefone' => '19999999999',
            'email' => 'cliente@teste.com',
            'uf' => 'SP',
            'municipio' => 'Socorro',
            'codigo_ibge_municipio' => '3552106',
            'cep' => '13960-000',
            'logradouro' => 'Rua Teste',
            'numero' => '100',
            'bairro' => 'Centro',
            'consentimento_lgpd' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('nome', 'Cliente Completo');
        $response->assertJsonPath('consentimento_lgpd', true);
        $this->assertNotNull($response->json('consentimento_lgpd_data'));
    }

    public function test_cadastra_cliente_minimo_sem_endereco(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/clientes", [
            'nome' => 'Cliente Simples',
        ]);

        $response->assertCreated()->assertJsonPath('consentimento_lgpd', false);
    }

    public function test_cadastra_fornecedor_completo(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/fornecedores", [
            'razao_social' => 'Fornecedor Completo LTDA',
            'nome_fantasia' => 'Fornecedor Fantasia',
            'cnpj' => '11.111.111/0001-11',
            'contato' => 'João',
            'telefone' => '1933334444',
            'email' => 'contato@fornecedor.com',
            'endereco' => 'Rua dos Fornecedores, 50',
            'inscricao_estadual' => '123456789',
        ]);

        $response->assertCreated()->assertJsonPath('razao_social', 'Fornecedor Completo LTDA');
    }

    public function test_atualiza_fornecedor(): void
    {
        $fornecedor = \App\Models\Fornecedor::create([
            'empresa_id' => $this->empresa->id, 'razao_social' => 'Fornecedor Original',
        ]);

        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/fornecedores/{$fornecedor->id}", [
            'razao_social' => 'Fornecedor Atualizado',
            'telefone' => '1955556666',
        ]);

        $response->assertOk()->assertJsonPath('razao_social', 'Fornecedor Atualizado');
    }

    public function test_fornecedor_de_uma_empresa_nao_aparece_em_outra(): void
    {
        $this->asSuperAdmin();
        $outraEmpresa = Empresa::create([
            'razao_social' => 'Outra Empresa Fornecedor', 'cnpj' => '33.333.333/0001-33',
            'slug' => 'outra-empresa-fornecedor', 'plano_id' => $this->empresa->plano_id, 'status' => 'ativa',
        ]);
        $this->asEmpresa($outraEmpresa->id);
        \App\Models\Fornecedor::create([
            'empresa_id' => $outraEmpresa->id, 'razao_social' => 'Fornecedor da Outra Empresa',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/fornecedores");

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_admin_visualiza_config_fiscal(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/config-fiscal");

        $response->assertOk();
        $response->assertJsonPath('empresa.cnpj', $this->empresa->cnpj);
    }

    public function test_atendente_nao_acessa_config_fiscal(): void
    {
        $response = $this->actingAs($this->atendente)->getJson("/dashboard/{$this->empresa->slug}/config-fiscal");

        $response->assertStatus(403);
    }

    public function test_admin_atualiza_config_fiscal_e_endereco_da_empresa(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-fiscal", [
            'uf' => 'SP',
            'municipio' => 'Socorro',
            'codigo_ibge_municipio' => '3552106',
            'cep' => '13960-000',
            'logradouro' => 'Rua Teste',
            'numero' => '10',
            'bairro' => 'Centro',
            'crt' => '1',
            'inscricao_estadual' => '123456789',
            'ambiente_ativo' => 'homologacao',
        ]);

        $response->assertOk();
        $response->assertJsonPath('empresa.uf', 'SP');
        $response->assertJsonPath('config_fiscal.crt', '1');
        $this->assertSame('SP', $this->empresa->fresh()->uf);
    }

    public function test_atualizar_config_fiscal_sem_ambiente_falha(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/config-fiscal", [
            'crt' => '1',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_ve_cadastrado_false_quando_nao_ha_certificado(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/certificado");

        $response->assertOk()->assertJsonPath('cadastrado', false);
    }

    public function test_atendente_nao_acessa_certificado(): void
    {
        $response = $this->actingAs($this->atendente)->getJson("/dashboard/{$this->empresa->slug}/certificado");

        $response->assertStatus(403);
    }

    public function test_upload_de_certificado_invalido_retorna_422(): void
    {
        $arquivo = \Illuminate\Http\UploadedFile::fake()->create('certificado.pfx', 10);

        $response = $this->actingAs($this->admin)->post("/dashboard/{$this->empresa->slug}/certificado", [
            'arquivo' => $arquivo,
            'senha' => 'qualquer-coisa',
            'tipo' => 'A1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Não foi possível ler o certificado - senha incorreta ou arquivo inválido.');
    }

    public function test_upload_de_certificado_sem_arquivo_falha_validacao(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/certificado", [
            'senha' => 'qualquer-coisa',
            'tipo' => 'A1',
        ]);

        $response->assertStatus(422);
    }
}
