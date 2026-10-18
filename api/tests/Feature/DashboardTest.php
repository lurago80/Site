<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\ItemCompra;
use App\Models\ItemVenda;
use App\Models\KitComponente;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
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
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/visitas", [
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

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/visitas");

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

    public function test_vendedor_guarda_a_chave_pix_sem_espacos_nas_pontas(): void
    {
        $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Vendedor Pix', 'chave_pix' => '  vendedor@exemplo.com  ',
        ])->assertCreated()->assertJsonPath('chave_pix', 'vendedor@exemplo.com');

        $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Sem Pix', 'chave_pix' => '   ',
        ])->assertCreated()->assertJsonPath('chave_pix', null);
    }

    public function test_chave_pix_muito_longa_e_recusada(): void
    {
        $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Vendedor', 'chave_pix' => str_repeat('a', 78),
        ])->assertStatus(422)->assertJsonValidationErrors('chave_pix');
    }

    public function test_admin_edita_vendedor_existente_e_inclui_a_chave_pix(): void
    {
        $id = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Vendedor Antigo', 'percentual_comissao' => 5,
        ])->json('id');

        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/vendedores/{$id}", [
            'nome' => 'Vendedor Antigo', 'percentual_comissao' => 7.5, 'chave_pix' => '12345678909',
        ])->assertOk()->assertJsonPath('chave_pix', '12345678909')->assertJsonPath('percentual_comissao', '7.50');

        // salvar sem informar a chave não apaga a que já existe
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/vendedores/{$id}", ['telefone' => '19999990000'])
            ->assertOk()->assertJsonPath('chave_pix', '12345678909');
    }

    public function test_so_admin_edita_vendedor_e_so_admin_ve_a_chave_pix(): void
    {
        $id = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Vendedor Pix', 'chave_pix' => 'chave-secreta-123',
        ])->json('id');

        $this->actingAs($this->atendente)->putJson("/dashboard/{$this->empresa->slug}/vendedores/{$id}", ['nome' => 'X'])
            ->assertStatus(403);

        $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/vendedores")
            ->assertOk()->assertJsonPath('0.chave_pix', 'chave-secreta-123');

        $this->actingAs($this->atendente)->getJson("/dashboard/{$this->empresa->slug}/vendedores")
            ->assertOk()->assertJsonMissingPath('0.chave_pix');
    }

    public function test_relatorio_de_vendedores_mostra_a_chave_pix_so_para_admin(): void
    {
        $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/vendedores", [
            'nome' => 'Vendedor Pix', 'chave_pix' => 'pix@exemplo.com',
        ])->assertCreated();

        $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/vendedores-relatorio")
            ->assertOk()->assertJsonPath('0.chave_pix', 'pix@exemplo.com');

        $this->actingAs($this->atendente)->getJson("/dashboard/{$this->empresa->slug}/vendedores-relatorio")
            ->assertOk()->assertJsonMissingPath('0.chave_pix');
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
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Relatório',
            'tipo' => 'fisico', 'preco_venda' => 50,
        ]);
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 10, 'vagas_reservadas' => 0, 'status' => 'aberta', 'valor_visita' => 30,
        ]);

        $vendaProduto = Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendenteModel->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 100, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $vendaProduto->id, 'produto_id' => $produto->id,
            'quantidade' => 2, 'valor_unitario' => 50, 'valor_total' => 100,
        ]);

        $vendaVisita = Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendenteModel->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 30, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $vendaVisita->id, 'agenda_visitacao_id' => $agenda->id,
            'quantidade' => 1, 'valor_unitario' => 30, 'valor_total' => 30,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/atendentes-relatorio");
        $response->assertOk();
        $dados = collect($response->json())->firstWhere('nome', 'Atendente Relatório');
        $this->assertSame(2, $dados['vendas_count']);
        $this->assertEquals(130.0, $dados['valor_total']);

        $soProdutos = $this->actingAs($this->admin)
            ->getJson("/dashboard/{$this->empresa->slug}/atendentes-relatorio?tipo=produtos");
        $dadosProdutos = collect($soProdutos->json())->firstWhere('nome', 'Atendente Relatório');
        $this->assertSame(1, $dadosProdutos['vendas_count']);
        $this->assertEquals(100.0, $dadosProdutos['valor_total']);

        $soVisitacoes = $this->actingAs($this->admin)
            ->getJson("/dashboard/{$this->empresa->slug}/atendentes-relatorio?tipo=visitacoes");
        $dadosVisitacoes = collect($soVisitacoes->json())->firstWhere('nome', 'Atendente Relatório');
        $this->assertSame(1, $dadosVisitacoes['vendas_count']);
        $this->assertEquals(30.0, $dadosVisitacoes['valor_total']);
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

    public function test_admin_salva_e_le_configuracao_de_frete(): void
    {
        $url = "/dashboard/{$this->empresa->slug}/config-frete";

        $this->actingAs($this->admin)->putJson($url, [
            'frete_gratis_acima' => 200,
            'permite_retirada' => true,
            'instrucoes_retirada' => 'Rua A, 10 - seg a sex',
            'regras' => [
                ['uf' => 'sp', 'valor' => 15, 'prazo_dias' => 3],
                ['uf' => null, 'valor' => 40],
            ],
        ])->assertOk()->assertJsonCount(2, 'regras');

        $this->actingAs($this->admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('permite_retirada', true)
            ->assertJsonPath('regras.0.uf', 'SP')
            ->assertJsonPath('regras.1.uf', null);

        // Salvar de novo substitui a tabela inteira.
        $this->actingAs($this->admin)->putJson($url, ['permite_retirada' => false, 'regras' => []])
            ->assertOk()->assertJsonCount(0, 'regras');
    }

    public function test_frete_rejeita_uf_repetida_e_atendente_nao_altera(): void
    {
        $url = "/dashboard/{$this->empresa->slug}/config-frete";

        $this->actingAs($this->admin)->putJson($url, [
            'permite_retirada' => false,
            'regras' => [['uf' => 'SP', 'valor' => 10], ['uf' => 'sp', 'valor' => 20]],
        ])->assertStatus(422);

        $this->actingAs($this->atendente)->putJson($url, ['permite_retirada' => false, 'regras' => []])
            ->assertStatus(403);
    }

    public function test_lista_pedidos_da_loja_e_atualiza_o_envio(): void
    {
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Chopp', 'tipo' => 'fisico', 'preco_venda' => 20, 'estoque_atual' => 10,
        ]);
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Maria <b>Teste</b>', 'cpf_cnpj' => '12345678901']);

        $pedido = Venda::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id,
            'canal' => 'site', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago',
            'valor_total' => 52, 'data_venda' => now(),
            'tipo_entrega' => 'entrega', 'valor_frete' => 12, 'status_envio' => 'a_separar',
            'endereco_entrega' => ['logradouro' => 'Av. Paulista', 'numero' => '1000', 'uf' => 'SP'],
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $pedido->id, 'produto_id' => $produto->id,
            'quantidade' => 2, 'valor_unitario' => 20, 'valor_total' => 40,
        ]);

        // Venda de PDV não entra na lista.
        Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 10, 'data_venda' => now(),
        ]);

        $base = "/dashboard/{$this->empresa->slug}/pedidos-loja";

        $this->actingAs($this->admin)->getJson($base)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.endereco_entrega.logradouro', 'Av. Paulista')
            ->assertJsonPath('0.itens.0.nome', 'Chopp');

        $this->actingAs($this->admin)->getJson("{$base}?status_envio=enviado")->assertOk()->assertJsonCount(0);

        $this->actingAs($this->admin)->putJson("{$base}/{$pedido->id}/envio", [
            'status_envio' => 'enviado', 'codigo_rastreio' => 'BR123',
        ])->assertOk()->assertJsonPath('codigo_rastreio', 'BR123');

        $this->assertSame('enviado', $pedido->fresh()->status_envio);
        $this->actingAs($this->admin)->getJson("{$base}?status_envio=enviado")->assertJsonCount(1);

        $this->actingAs($this->admin)->putJson("{$base}/{$pedido->id}/envio", ['status_envio' => 'sumiu'])->assertStatus(422);
    }

    public function test_envio_de_pedido_sem_entrega_ou_de_pdv_retorna_404(): void
    {
        $pdv = Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 10, 'data_venda' => now(),
        ]);

        $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/pedidos-loja/{$pdv->id}/envio", ['status_envio' => 'enviado'])
            ->assertNotFound();
    }

    public function test_admin_configura_o_kit_e_valida_os_componentes(): void
    {
        $caneca = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Caneca', 'tipo' => 'fisico', 'preco_venda' => 30, 'estoque_atual' => 5]);
        $cerveja = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cerveja', 'tipo' => 'fisico', 'preco_venda' => 18]);
        ProdutoVariacao::create(['empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 5]);
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit', 'tipo' => 'fisico', 'preco_venda' => 80]);

        $url = "/dashboard/{$this->empresa->slug}/produtos/{$kit->id}/kit";

        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => true, 'componentes' => [
            ['tipo' => 'fixo', 'produto_id' => $caneca->id, 'quantidade' => 1],
            ['tipo' => 'escolha', 'produto_id' => $cerveja->id, 'quantidade' => 3],
        ]])->assertOk()->assertJsonPath('eh_kit', true)->assertJsonCount(2, 'componentes');

        $this->actingAs($this->admin)->getJson($url)->assertOk()->assertJsonPath('componentes.1.nome', 'Cerveja');

        // "fixo" não pode ter variações; kit dentro de kit não vale.
        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => true, 'componentes' => [['tipo' => 'fixo', 'produto_id' => $cerveja->id, 'quantidade' => 1]]])->assertStatus(422);
        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => true, 'componentes' => [['tipo' => 'fixo', 'produto_id' => $kit->id, 'quantidade' => 1]]])->assertStatus(422);
        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => true, 'componentes' => []])->assertStatus(422);

        // Desligar o kit limpa a composição.
        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => false, 'componentes' => []])->assertOk()->assertJsonCount(0, 'componentes');
        $this->assertFalse($kit->fresh()->eh_kit);
    }

    public function test_admin_monta_kit_coringa_com_itens_simples_em_um_grupo_e_total_de_escolhas(): void
    {
        $cerveja = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cerveja', 'tipo' => 'fisico', 'preco_venda' => 18]);
        ProdutoVariacao::create(['empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 5]);
        $windsor = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Copo Windsor', 'tipo' => 'fisico', 'preco_venda' => 20, 'estoque_atual' => 5]);
        $caldereta = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Copo Caldereta', 'tipo' => 'fisico', 'preco_venda' => 20, 'estoque_atual' => 5]);
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit Coringa', 'tipo' => 'fisico', 'preco_venda' => 75]);

        $url = "/dashboard/{$this->empresa->slug}/produtos/{$kit->id}/kit";

        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => true, 'kit_total_escolhas' => 3, 'componentes' => [
            ['tipo' => 'escolha', 'produto_id' => $cerveja->id, 'quantidade' => 3],
            ['tipo' => 'escolha', 'produto_id' => $windsor->id, 'quantidade' => 1, 'grupo' => 'Copo'],
            ['tipo' => 'escolha', 'produto_id' => $caldereta->id, 'quantidade' => 1, 'grupo' => 'Copo'],
        ]])->assertOk()->assertJsonPath('kit_total_escolhas', 3)->assertJsonPath('componentes.1.grupo', 'Copo');

        // o total não pode passar da soma dos limites (3 + 1 = 4)
        $this->actingAs($this->admin)->putJson($url, ['eh_kit' => true, 'kit_total_escolhas' => 5, 'componentes' => [
            ['tipo' => 'escolha', 'produto_id' => $cerveja->id, 'quantidade' => 3],
            ['tipo' => 'escolha', 'produto_id' => $windsor->id, 'quantidade' => 1, 'grupo' => 'Copo'],
            ['tipo' => 'escolha', 'produto_id' => $caldereta->id, 'quantidade' => 1, 'grupo' => 'Copo'],
        ]])->assertStatus(422);
    }

    public function test_produto_somente_loja_virtual_e_criado_ja_visivel_na_loja_e_fora_do_pdv(): void
    {
        // marcou "somente loja virtual" mesmo com "loja virtual" desmarcada: continua aparecendo na loja
        $resposta = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/produtos", [
            'nome' => 'Cerveja Artesanal', 'tipo' => 'fisico', 'preco_venda' => 20,
            'loja_virtual' => false, 'somente_loja_virtual' => true,
        ])->assertCreated();

        $this->assertTrue($resposta->json('loja_virtual'));
        $this->assertTrue($resposta->json('somente_loja_virtual'));

        $id = $resposta->json('id');

        // ao editar para somente loja virtual, também força a aparecer na loja
        $outro = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Outro', 'tipo' => 'fisico', 'preco_venda' => 5, 'loja_virtual' => false]);
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/produtos/{$outro->id}", ['somente_loja_virtual' => true])->assertOk();
        $this->assertTrue($outro->fresh()->loja_virtual);

        $catalogo = $this->getJson("/api/loja/{$this->empresa->slug}/produtos")->assertOk()->json();
        $this->assertContains($id, collect($catalogo)->pluck('id')->all());
    }

    private function produtoParaExcluir(string $nome = 'Produto Descartável'): Produto
    {
        return Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => $nome, 'tipo' => 'fisico', 'preco_venda' => 10, 'estoque_atual' => 5,
        ]);
    }

    private function urlProduto(Produto $produto): string
    {
        return "/dashboard/{$this->empresa->slug}/produtos/{$produto->id}";
    }

    public function test_produto_sem_movimento_e_excluido_com_suas_variacoes(): void
    {
        $produto = $this->produtoParaExcluir();
        $variacao = ProdutoVariacao::create(['empresa_id' => $this->empresa->id, 'produto_id' => $produto->id, 'tamanho' => 'P', 'estoque_atual' => 2]);

        $this->actingAs($this->admin)->deleteJson($this->urlProduto($produto))
            ->assertOk()->assertJsonPath('acao', 'excluido');

        $this->assertNull(Produto::find($produto->id));
        $this->assertNull(ProdutoVariacao::find($variacao->id));
    }

    public function test_produto_com_venda_nao_e_excluido_apenas_desativado(): void
    {
        $produto = $this->produtoParaExcluir();
        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 10, 'data_venda' => now(),
        ]);
        ItemVenda::create(['empresa_id' => $this->empresa->id, 'venda_id' => $venda->id, 'produto_id' => $produto->id, 'quantidade' => 1, 'valor_unitario' => 10, 'valor_total' => 10]);

        $resposta = $this->actingAs($this->admin)->deleteJson($this->urlProduto($produto))->assertOk();

        $resposta->assertJsonPath('acao', 'desativado');
        $this->assertStringContainsString('vendas', $resposta->json('motivo'));
        $this->assertFalse($produto->fresh()->ativo);
        $this->assertSame(1, ItemVenda::where('produto_id', $produto->id)->count()); // histórico intacto

        // repetir a ação continua só desativando, avisando que já estava
        $this->actingAs($this->admin)->deleteJson($this->urlProduto($produto))
            ->assertOk()->assertJsonPath('acao', 'desativado')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'já estava desativado'));
    }

    public function test_componente_de_kit_e_desativado_e_nao_excluido_para_nao_desmontar_o_kit(): void
    {
        $caneca = $this->produtoParaExcluir('Caneca');
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit', 'tipo' => 'fisico', 'preco_venda' => 50, 'eh_kit' => true]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $caneca->id, 'tipo' => 'fixo', 'quantidade' => 1]);

        $resposta = $this->actingAs($this->admin)->deleteJson($this->urlProduto($caneca))->assertOk();

        $resposta->assertJsonPath('acao', 'desativado');
        $this->assertStringContainsString('composição de kits', $resposta->json('motivo'));
        $this->assertFalse($caneca->fresh()->ativo);
        $this->assertSame(1, KitComponente::where('kit_id', $kit->id)->count());
    }

    public function test_kit_sem_movimento_e_excluido_e_seus_componentes_sao_preservados(): void
    {
        $caneca = $this->produtoParaExcluir('Caneca');
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit', 'tipo' => 'fisico', 'preco_venda' => 50, 'eh_kit' => true]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $caneca->id, 'tipo' => 'fixo', 'quantidade' => 1]);

        $this->actingAs($this->admin)->deleteJson($this->urlProduto($kit))->assertOk()->assertJsonPath('acao', 'excluido');

        $this->assertNull(Produto::find($kit->id));
        $this->assertSame(0, KitComponente::count());
        $this->assertNotNull(Produto::find($caneca->id));
        $this->assertTrue($caneca->fresh()->ativo);
    }

    public function test_apenas_admin_exclui_produto_e_produto_de_outra_empresa_nao_e_encontrado(): void
    {
        $produto = $this->produtoParaExcluir();

        $this->actingAs($this->atendente)->deleteJson($this->urlProduto($produto))->assertStatus(403);
        $this->assertNotNull(Produto::find($produto->id));

        $this->asSuperAdmin();
        $outraEmpresa = Empresa::create([
            'razao_social' => 'Outra Empresa', 'cnpj' => '22.222.222/0001-22', 'slug' => 'outra-empresa',
            'plano_id' => $this->empresa->plano_id, 'status' => 'ativa',
        ]);
        $alheio = Produto::create(['empresa_id' => $outraEmpresa->id, 'nome' => 'Alheio', 'tipo' => 'fisico', 'preco_venda' => 1]);
        $this->asEmpresa($this->empresa->id);

        $this->actingAs($this->admin)->deleteJson("/dashboard/{$this->empresa->slug}/produtos/{$alheio->id}")->assertNotFound();
        $this->asSuperAdmin();
        $this->assertNotNull(Produto::find($alheio->id));
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

    public function test_cadastra_produto_com_campos_fiscais_da_reforma_tributaria(): void
    {
        $classTrib = \App\Models\TabClassTrib::where('codigo', '000001')->first();
        $credPres = \App\Models\TabCredPres::create(['codigo' => '000001', 'descricao' => 'Crédito Presumido Teste', 'ativo' => true]);

        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/produtos", [
            'nome' => 'Produto Reforma Tributária',
            'tipo' => 'fisico',
            'preco_venda' => 40,
            'tipo_produto_fiscal' => 'consumo',
            'codigo_barras' => '7891234567890',
            'estoque_minimo' => 5,
            'valor_atacado' => 35,
            'peso_liquido' => 1.5,
            'peso_bruto' => 1.8,
            'pesavel' => true,
            'cst_origem' => '0',
            'cst_icms' => '00',
            'aliquota_icms' => 18,
            'cst_pis' => '01',
            'aliquota_pis' => 1.65,
            'cst_cofins' => '01',
            'aliquota_cofins' => 7.6,
            'cst_ipi' => '50',
            'aliquota_ipi' => 5,
            'situacao_novo_regime' => '1',
            'cst_ibs_cbs' => '000',
            'cclasstrib_id' => $classTrib->id,
            'aliquota_ibs' => 0.1,
            'aliquota_cbs' => 0.9,
            'ccredpres_id' => $credPres->id,
            'sujeito_imposto_seletivo' => true,
            'tipo_imposto_seletivo' => 'bebidas_acucaradas',
            'aliquota_is' => 2,
            'destinacao_tributaria' => 'RV',
            'tipo_credito' => 'IN',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tipo_produto_fiscal', 'consumo');
        $response->assertJsonPath('codigo_barras', '7891234567890');
        $response->assertJsonPath('situacao_novo_regime', '1');
        $response->assertJsonPath('cst_ibs_cbs', '000');
        $response->assertJsonPath('cclasstrib_id', $classTrib->id);
        $response->assertJsonPath('sujeito_imposto_seletivo', true);
        $response->assertJsonPath('destinacao_tributaria', 'RV');

        $listagem = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/produtos");
        $listagem->assertOk()->assertJsonPath('0.class_trib.codigo', '000001');
    }

    public function test_lista_tabelas_auxiliares_cclasstrib_e_ccredpres(): void
    {
        $respostaClassTrib = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/tab-cclasstrib");
        $respostaClassTrib->assertOk();
        $this->assertGreaterThanOrEqual(6, count($respostaClassTrib->json()));

        $respostaCredPres = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/tab-ccredpres");
        $respostaCredPres->assertOk();
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

    public function test_admin_define_serie_e_ultimo_numero_de_nfe_e_nfce(): void
    {
        $url = "/dashboard/{$this->empresa->slug}/config-fiscal";

        $this->actingAs($this->admin)->putJson($url, [
            'crt' => '3', 'ambiente_ativo' => 'producao',
            'serie_nfe_atual' => 2, 'numero_nfe_atual' => 150, 'serie_nfce_atual' => 1, 'numero_nfce_atual' => 37,
        ])->assertOk()
            ->assertJsonPath('config_fiscal.serie_nfe_atual', '2')
            ->assertJsonPath('config_fiscal.numero_nfe_atual', 150)
            ->assertJsonPath('config_fiscal.numero_nfce_atual', 37);

        // salvar sem os campos de numeração não mexe neles
        $this->actingAs($this->admin)->putJson($url, ['crt' => '3', 'ambiente_ativo' => 'producao'])->assertOk();
        $config = \App\Models\ConfigFiscal::where('empresa_id', $this->empresa->id)->first();
        $this->assertSame('2', $config->serie_nfe_atual);
        $this->assertSame(150, $config->numero_nfe_atual);
    }

    public function test_serie_fiscal_invalida_e_recusada(): void
    {
        $url = "/dashboard/{$this->empresa->slug}/config-fiscal";

        foreach (['serie_nfe_atual' => 900, 'serie_nfce_atual' => -1, 'numero_nfe_atual' => -5, 'numero_nfce_atual' => 'abc'] as $campo => $valor) {
            $this->actingAs($this->admin)->putJson($url, ['crt' => '1', 'ambiente_ativo' => 'homologacao', $campo => $valor])
                ->assertStatus(422)->assertJsonValidationErrors($campo);
        }
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
        $response->assertJsonPath('message', 'Não foi possível ler o certificado - verifique se o arquivo é um .pfx/.p12 válido e se a senha está correta.');
    }

    public function test_upload_de_certificado_sem_arquivo_falha_validacao(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/certificado", [
            'senha' => 'qualquer-coisa',
            'tipo' => 'A1',
        ]);

        $response->assertStatus(422);
    }

    public function test_relatorios_de_vendas_itens_mais_vendidos_curva_abc_e_lucratividade(): void
    {
        $atendente = \App\Models\Atendente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Atendente Relatório Vendas', 'ativo' => true,
        ]);

        $caro = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Caro',
            'tipo' => 'fisico', 'preco_venda' => 100, 'preco_custo' => 60,
        ]);
        $barato = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Barato',
            'tipo' => 'fisico', 'preco_venda' => 10, 'preco_custo' => 9,
        ]);

        $venda1 = Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendente->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago', 'valor_total' => 100, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda1->id, 'produto_id' => $caro->id,
            'quantidade' => 1, 'valor_unitario' => 100, 'valor_total' => 100, 'custo_total' => 60,
        ]);

        $venda2 = Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendente->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago', 'valor_total' => 30, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda2->id, 'produto_id' => $barato->id,
            'quantidade' => 3, 'valor_unitario' => 10, 'valor_total' => 30, 'custo_total' => 27,
        ]);

        // venda cancelada não deve entrar em nenhum relatório
        $cancelada = Venda::create([
            'empresa_id' => $this->empresa->id, 'atendente_id' => $atendente->id,
            'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'cancelado', 'cancelada_em' => now(),
            'valor_total' => 500, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $cancelada->id, 'produto_id' => $caro->id,
            'quantidade' => 5, 'valor_unitario' => 100, 'valor_total' => 500, 'custo_total' => 300,
        ]);

        $vendas = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/vendas-relatorio")->assertOk();
        $vendas->assertJsonCount(2, 'linhas');
        $vendas->assertJsonPath('totais.valor_total', 130);

        $itens = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/itens-vendidos-relatorio")->assertOk();
        $itens->assertJsonCount(2, 'linhas');
        $itens->assertJsonPath('totais.quantidade', 4);

        $maisVendidos = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/produtos-mais-vendidos-relatorio")->assertOk();
        $maisVendidos->assertJsonPath('linhas.0.produto', 'Produto Barato'); // 3 unidades > 1 unidade
        $maisVendidos->assertJsonPath('linhas.0.quantidade', 3);

        $abc = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/curva-abc-relatorio")->assertOk();
        $abc->assertJsonPath('linhas.0.produto', 'Produto Caro'); // maior faturamento primeiro
        $abc->assertJsonPath('linhas.0.classe', 'A'); // 100/130 = 76,9% acumulado
        $abc->assertJsonPath('linhas.1.classe', 'C'); // 130/130 = 100% acumulado

        $lucratividade = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/lucratividade-relatorio")->assertOk();
        $lucratividade->assertJsonPath('linhas.0.produto', 'Produto Caro'); // maior margem em R$ (40 > 3)
        $lucratividade->assertJsonPath('linhas.0.margem', 40);
        $lucratividade->assertJsonPath('totais.margem', 43);
    }

    /**
     * O brinde de visita (ex.: caneca - ver BrindeVisitaService) grava um
     * ItemVenda à parte com a MESMA quantidade da visita (1 por visitante).
     * A coluna "Itens" do relatório de vendas precisa contar só os
     * visitantes, mostrando o brinde separado (não somado junto).
     */
    public function test_relatorio_de_vendas_mostra_brinde_de_visita_separado_dos_itens(): void
    {
        $brinde = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Caneca de chopp',
            'tipo' => 'fisico', 'preco_venda' => 0, 'preco_custo' => 5, 'estoque_atual' => 100,
        ]);
        $this->empresa->update(['produto_brinde_visita_id' => $brinde->id]);

        $produtoVisita = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Visita guiada',
            'tipo' => 'agendamento', 'preco_venda' => 50, 'preco_custo' => 0,
        ]);
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $produtoVisita->id,
            'data_hora' => now(), 'vagas_total' => 20, 'vagas_reservadas' => 3, 'status' => 'aberta',
            'valor_visita' => 50,
        ]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 150, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda->id, 'agenda_visitacao_id' => $agenda->id,
            'quantidade' => 3, 'valor_unitario' => 50, 'valor_total' => 150,
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda->id, 'produto_id' => $brinde->id,
            'quantidade' => 3, 'valor_unitario' => 0, 'valor_total' => 0,
        ]);

        $resp = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/vendas-relatorio")->assertOk();
        $linha = collect($resp->json('linhas'))->firstWhere('id', $venda->id);

        $this->assertSame(3, $linha['itens']);
        $this->assertSame(3, $linha['brindes']);
        $resp->assertJsonPath('totais.brindes', 3);
    }

    /**
     * Relatório que "abre" os kits nos componentes reais: uma cerveja vendida
     * avulsa e também dentro de um kit precisa somar no mesmo produto, com o
     * valor do kit rateado proporcionalmente ao preço de tabela de cada
     * componente (mesmo princípio usado na NFe para kits).
     */
    public function test_relatorio_produtos_com_kits_abertos_soma_avulso_e_componentes_do_kit(): void
    {
        $ipa = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja IPA',
            'tipo' => 'fisico', 'preco_venda' => 20, 'preco_custo' => 8,
        ]);
        $pilsen = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Pilsen',
            'tipo' => 'fisico', 'preco_venda' => 10, 'preco_custo' => 4,
        ]);
        $kit = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Kit Degustação',
            'tipo' => 'fisico', 'preco_venda' => 25, 'preco_custo' => 0, 'eh_kit' => true,
        ]);

        // Venda avulsa: 2 IPA
        $venda1 = Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 40, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda1->id, 'produto_id' => $ipa->id,
            'quantidade' => 2, 'valor_unitario' => 20, 'valor_total' => 40, 'custo_total' => 16,
        ]);

        // Venda do kit: 1 IPA + 1 Pilsen dentro, kit vendido por R$ 25 com R$ 5 de desconto
        // (peso: IPA 20*1=20, Pilsen 10*1=10 -> rateio 2/3 e 1/3 de 25 = 16,67 e 8,33)
        $venda2 = Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 25, 'valor_desconto' => 5, 'data_venda' => now(),
        ]);
        ItemVenda::create([
            'empresa_id' => $this->empresa->id, 'venda_id' => $venda2->id, 'produto_id' => $kit->id,
            'quantidade' => 1, 'valor_unitario' => 25, 'valor_total' => 25,
            'composicao' => [
                ['produto_id' => $ipa->id, 'nome' => 'Cerveja IPA', 'variacao_id' => null, 'tamanho' => null, 'quantidade' => 1],
                ['produto_id' => $pilsen->id, 'nome' => 'Cerveja Pilsen', 'variacao_id' => null, 'tamanho' => null, 'quantidade' => 1],
            ],
        ]);

        $resp = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/produtos-com-kits-abertos-relatorio")->assertOk();

        $resp->assertJsonCount(3, 'linhas'); // 1 avulsa + 2 componentes do kit

        $resumo = collect($resp->json('resumo'))->keyBy('produto');

        $this->assertEquals(2, $resumo['Cerveja IPA']['quantidade_direta']);
        $this->assertEquals(1, $resumo['Cerveja IPA']['quantidade_em_kit']);
        $this->assertEquals(3, $resumo['Cerveja IPA']['quantidade_total']);
        $this->assertEqualsWithDelta(56.67, $resumo['Cerveja IPA']['valor_total'], 0.01); // 40 + 16,67
        // Custo: 16 (venda avulsa, gravado na hora da venda) + 1 x preço de custo atual (8) do componente do kit.
        $this->assertEqualsWithDelta(24.0, $resumo['Cerveja IPA']['custo_total'], 0.01);
        $this->assertEqualsWithDelta(32.67, $resumo['Cerveja IPA']['margem'], 0.01);

        $this->assertEquals(0, $resumo['Cerveja Pilsen']['quantidade_direta']);
        $this->assertEquals(1, $resumo['Cerveja Pilsen']['quantidade_em_kit']);
        $this->assertEqualsWithDelta(8.33, $resumo['Cerveja Pilsen']['valor_total'], 0.01);
        $this->assertEqualsWithDelta(4.0, $resumo['Cerveja Pilsen']['custo_total'], 0.01);

        // O kit em si não aparece como produto - só os componentes.
        $this->assertArrayNotHasKey('Kit Degustação', $resumo->all());

        // Soma das linhas fecha exatamente com o valor dos itens originais (40 + 25).
        $resp->assertJsonPath('totais.valor_total', 65);
        $resp->assertJsonPath('totais.vendas', 2);
        $resp->assertJsonPath('totais.ticket_medio', 32.5); // 65 / 2 vendas
        $resp->assertJsonPath('totais.custo_total', 28); // 16 + 8 + 4

        // Desconto do pedido aparece em cada linha daquela venda (venda2 teve R$ 5 de desconto).
        $linhaVenda1 = collect($resp->json('linhas'))->firstWhere('venda_id', $venda1->id);
        $linhaVenda2 = collect($resp->json('linhas'))->firstWhere('venda_id', $venda2->id);
        $this->assertEquals(0, $linhaVenda1['desconto_pedido']);
        $this->assertEquals(5, $linhaVenda2['desconto_pedido']);

        // Filtro por produto: Pilsen só existe dentro do kit (produto_id do item
        // original seria o do kit) - o filtro precisa achar mesmo assim.
        $filtrado = $this->actingAs($this->admin)
            ->getJson("/dashboard/{$this->empresa->slug}/produtos-com-kits-abertos-relatorio?produto_id={$pilsen->id}")
            ->assertOk();
        $filtrado->assertJsonCount(1, 'linhas');
        $filtrado->assertJsonPath('linhas.0.produto', 'Cerveja Pilsen');
        $filtrado->assertJsonPath('totais.quantidade', 1);
        $filtrado->assertJsonPath('linhas.0.valor_pedido', 25); // valor total do pedido do kit, não só a fatia do Pilsen
    }

    /** O relatório de entrada de notas precisa listar os itens de cada nota, não só os totais. */
    public function test_relatorio_de_entrada_notas_traz_os_itens_de_cada_nota(): void
    {
        $fornecedor = Fornecedor::create([
            'empresa_id' => $this->empresa->id, 'razao_social' => 'Distribuidora Teste LTDA',
        ]);
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja IPA', 'tipo' => 'fisico', 'preco_venda' => 20,
        ]);
        $compra = Compra::create([
            'empresa_id' => $this->empresa->id, 'fornecedor_id' => $fornecedor->id,
            'numero_nota' => '123', 'tipo_entrada' => 'manual', 'data_entrada' => now(),
            'valor_produtos' => 100, 'valor_total' => 100, 'status' => 'confirmada',
        ]);
        ItemCompra::create([
            'empresa_id' => $this->empresa->id, 'compra_id' => $compra->id, 'produto_id' => $produto->id,
            'codigo_produto_fornecedor' => 'FORN-001', 'quantidade' => 10, 'valor_unitario' => 10, 'valor_total' => 100,
        ]);

        $resp = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/entrada-notas-relatorio")->assertOk();

        $resp->assertJsonCount(1, 'linhas.0.itens_detalhe');
        $resp->assertJsonPath('linhas.0.itens_detalhe.0.produto', 'Cerveja IPA');
        $resp->assertJsonPath('linhas.0.itens_detalhe.0.codigo_produto_fornecedor', 'FORN-001');
        $resp->assertJsonPath('linhas.0.itens_detalhe.0.quantidade', 10);
        $resp->assertJsonPath('linhas.0.itens_detalhe.0.valor_total', 100);
    }
}
