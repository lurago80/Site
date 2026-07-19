<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\GravaBanco;
use App\Models\Grupo;
use App\Models\Plano;
use App\Models\PlanoContas;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Módulo financeiro expandido (Escopo v2, decisão de 2026-07-21):
 * grupos de produto, plano de contas, bancos/extrato e controle de
 * caixa físico do PDV (abertura, fechamento, sangria, suprimento).
 */
class FinanceiroExpandidoTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private User $atendente;

    private User $caixa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Financeiro Teste', 'cnpj' => '55.555.555/0001-55',
            'slug' => 'financeiro-teste', 'plano_id' => $plano->id, 'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Teste', 'email' => 'admin@financeiro-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);

        $this->atendente = User::create([
            'name' => 'Atendente Teste', 'email' => 'atendente@financeiro-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'atendente',
        ]);

        $this->caixa = User::create([
            'name' => 'Caixa Teste', 'email' => 'caixa@financeiro-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'caixa',
        ]);

        $this->asEmpresa($this->empresa->id);
    }

    // ---- Grupo ----

    public function test_admin_cadastra_grupo_de_produto(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/grupos", [
            'nome' => 'Bebidas',
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Bebidas');
    }

    public function test_atendente_nao_pode_cadastrar_grupo(): void
    {
        $response = $this->actingAs($this->atendente)->postJson("/dashboard/{$this->empresa->slug}/grupos", [
            'nome' => 'Bebidas',
        ]);

        $response->assertStatus(403);
    }

    public function test_relatorio_de_grupos_soma_valor_de_estoque(): void
    {
        $grupo = Grupo::create(['empresa_id' => $this->empresa->id, 'nome' => 'Chopps', 'ativo' => true]);
        Produto::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $grupo->id, 'nome' => 'Chopp IPA',
            'tipo' => 'fisico', 'preco_venda' => 20, 'preco_custo' => 10, 'estoque_atual' => 5,
        ]);
        Produto::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $grupo->id, 'nome' => 'Chopp Pilsen',
            'tipo' => 'fisico', 'preco_venda' => 18, 'preco_custo' => 8, 'estoque_atual' => 10,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/grupos-relatorio");

        $response->assertOk();
        $dados = collect($response->json())->firstWhere('nome', 'Chopps');
        $this->assertSame(2, $dados['produtos_count']);
        $this->assertEquals(130.0, $dados['valor_estoque']); // 10*5 + 8*10
    }

    // ---- Plano de contas ----

    public function test_admin_cadastra_plano_de_contas(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/plano-contas", [
            'codigo' => '1.1', 'nome' => 'Fornecedores', 'tipo' => 'despesa',
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Fornecedores');
    }

    public function test_relatorio_plano_de_contas_soma_contas_a_pagar_vinculadas(): void
    {
        $planoConta = PlanoContas::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '1.1', 'nome' => 'Fornecedores', 'tipo' => 'despesa', 'ativo' => true,
        ]);
        $fornecedor = Fornecedor::create(['empresa_id' => $this->empresa->id, 'razao_social' => 'Fornecedor Teste']);

        ContaPagar::create([
            'empresa_id' => $this->empresa->id, 'fornecedor_id' => $fornecedor->id, 'plano_conta_id' => $planoConta->id,
            'valor' => 100, 'vencimento' => now(), 'status' => 'em_aberto',
        ]);
        ContaPagar::create([
            'empresa_id' => $this->empresa->id, 'fornecedor_id' => $fornecedor->id, 'plano_conta_id' => $planoConta->id,
            'valor' => 50, 'vencimento' => now(), 'status' => 'pago',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/plano-contas-relatorio");

        $response->assertOk();
        $dados = collect($response->json())->firstWhere('nome', 'Fornecedores');
        $this->assertEquals(150.0, $dados['total']);
        $this->assertEquals(50.0, $dados['total_pago']);
        $this->assertEquals(100.0, $dados['total_em_aberto']);
    }

    // ---- Banco / extrato ----

    public function test_admin_cadastra_banco(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/bancos", [
            'nome' => 'Banco Teste - Conta Movimento', 'tipo_conta' => 'corrente', 'saldo_inicial' => 1000,
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Banco Teste - Conta Movimento');
    }

    public function test_marcar_conta_a_pagar_paga_com_banco_lanca_movimento_bancario(): void
    {
        $banco = Banco::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Banco Teste', 'tipo_conta' => 'corrente',
            'saldo_inicial' => 500, 'ativo' => true,
        ]);
        $fornecedor = Fornecedor::create(['empresa_id' => $this->empresa->id, 'razao_social' => 'Fornecedor Teste']);
        $conta = ContaPagar::create([
            'empresa_id' => $this->empresa->id, 'fornecedor_id' => $fornecedor->id,
            'valor' => 200, 'vencimento' => now(), 'status' => 'em_aberto',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/contas-pagar/{$conta->id}/pagar", ['banco_id' => $banco->id]);

        $response->assertOk()->assertJsonPath('status', 'pago');
        $this->assertSame(1, GravaBanco::where('banco_id', $banco->id)->where('tipo', 'debito')->count());
    }

    public function test_marcar_conta_a_receber_paga_com_banco_lanca_movimento_de_credito(): void
    {
        $banco = Banco::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Banco Teste', 'tipo_conta' => 'corrente',
            'saldo_inicial' => 0, 'ativo' => true,
        ]);
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Teste', 'consentimento_lgpd' => true]);
        $conta = ContaReceber::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id,
            'valor' => 300, 'vencimento' => now(), 'status' => 'em_aberto',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/contas-receber/{$conta->id}/pagar", ['banco_id' => $banco->id]);

        $response->assertOk()->assertJsonPath('status', 'pago');
        $this->assertSame(1, GravaBanco::where('banco_id', $banco->id)->where('tipo', 'credito')->count());
    }

    public function test_extrato_bancario_calcula_saldo_corrente(): void
    {
        $banco = Banco::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Banco Teste', 'tipo_conta' => 'corrente',
            'saldo_inicial' => 1000, 'ativo' => true,
        ]);

        GravaBanco::create([
            'empresa_id' => $this->empresa->id, 'banco_id' => $banco->id, 'data_movimento' => now()->subDay(),
            'tipo' => 'credito', 'valor' => 200, 'origem' => 'manual',
        ]);
        GravaBanco::create([
            'empresa_id' => $this->empresa->id, 'banco_id' => $banco->id, 'data_movimento' => now(),
            'tipo' => 'debito', 'valor' => 300, 'origem' => 'manual',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/dashboard/{$this->empresa->slug}/bancos/{$banco->id}/extrato");

        $response->assertOk();
        // saldo inicial 1000 + 200 credito - 300 debito = 900
        $this->assertEquals(900.0, $response->json('saldo_atual'));
        $this->assertCount(2, $response->json('movimentos'));
    }

    public function test_lancar_movimento_bancario_manual(): void
    {
        $banco = Banco::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Banco Teste', 'tipo_conta' => 'corrente', 'ativo' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/bancos/{$banco->id}/movimentos", [
            'data_movimento' => now()->toDateString(), 'tipo' => 'credito', 'valor' => 500, 'descricao' => 'Depósito',
        ]);

        $response->assertCreated()->assertJsonPath('origem', 'manual');
    }

    // ---- Caixa (PDV) ----

    public function test_abrir_caixa(): void
    {
        $response = $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", [
            'valor' => 100,
        ]);

        $response->assertCreated()->assertJsonPath('tipo', 'abertura');
    }

    public function test_nao_permite_abrir_caixa_ja_aberto(): void
    {
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 100]);

        $response = $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 50]);

        $response->assertStatus(422);
    }

    public function test_sangria_e_suprimento_alteram_saldo_do_caixa(): void
    {
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 100]);
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-suprimento", ['valor' => 50]);
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-sangria", ['valor' => 30]);

        $response = $this->actingAs($this->caixa)->getJson("/pdv/{$this->empresa->slug}/caixa-status");

        $response->assertOk()->assertJsonPath('status', 'aberto');
        // 100 abertura + 50 suprimento - 30 sangria = 120
        $this->assertEquals(120.0, $response->json('saldo'));
    }

    public function test_nao_permite_sangria_sem_caixa_aberto(): void
    {
        $response = $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-sangria", ['valor' => 30]);

        $response->assertStatus(422);
    }

    public function test_fechar_caixa_e_depois_reabrir_funciona(): void
    {
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 100]);
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-fechar", ['valor' => 100]);

        $statusApósFechar = $this->actingAs($this->caixa)->getJson("/pdv/{$this->empresa->slug}/caixa-status");
        $statusApósFechar->assertOk()->assertJsonPath('status', 'fechado');

        $reabrir = $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 80]);
        $reabrir->assertCreated();
    }

    public function test_extrato_de_caixa_lista_movimentos(): void
    {
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 100]);
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-sangria", ['valor' => 20]);

        $response = $this->actingAs($this->caixa)->getJson("/pdv/{$this->empresa->slug}/caixa-extrato");

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_isolamento_multi_tenant_do_caixa(): void
    {
        $this->actingAs($this->caixa)->postJson("/pdv/{$this->empresa->slug}/caixa-abrir", ['valor' => 100]);

        $plano = Plano::create(['nome' => 'Outro', 'valor_mensal' => 99]);
        $this->asSuperAdmin();
        $outraEmpresa = Empresa::create([
            'razao_social' => 'Outra Empresa', 'cnpj' => '66.666.666/0001-66',
            'slug' => 'outra-empresa-caixa', 'plano_id' => $plano->id, 'status' => 'ativa',
        ]);
        $outroCaixa = User::create([
            'name' => 'Outro Caixa', 'email' => 'caixa@outra-empresa-caixa.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $outraEmpresa->id, 'perfil' => 'caixa',
        ]);

        // Outra empresa não vê o caixa aberto da primeira - consegue abrir o próprio.
        $response = $this->actingAs($outroCaixa)->postJson("/pdv/{$outraEmpresa->slug}/caixa-abrir", ['valor' => 10]);
        $response->assertCreated();
    }
}
