<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * PDV (frente de caixa) - Escopo v2, seção 2.2.
 */
class PdvTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa PDV Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'pdv-teste',
            'modulo_agendamento_ativo' => true,
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->usuario = User::create([
            'name' => 'Caixa Teste',
            'email' => 'caixa@pdv-teste.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'caixa',
        ]);

        $this->actingAs($this->usuario);
        $this->asEmpresa($this->empresa->id);

        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Chopp Artesanal 500ml',
            'tipo' => 'fisico',
            'preco_venda' => 18.00,
            'estoque_atual' => 10,
        ]);
    }

    public function test_lista_produtos_por_busca(): void
    {
        Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Kit Degustação',
            'tipo' => 'fisico', 'preco_venda' => 85.00,
        ]);

        $response = $this->getJson("/pdv/{$this->empresa->slug}/produtos?busca=chopp");

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_venda_nao_fiscal_de_produto_debita_estoque(): void
    {
        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 3],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '54.00');
        $response->assertJsonPath('canal', 'pdv');
        $this->assertSame(7, $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_com_estoque_insuficiente_falha(): void
    {
        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 999],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(10, $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_sem_itens_e_sem_agenda_falha(): void
    {
        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
        ]);

        $response->assertStatus(422);
    }

    public function test_venda_com_vendedor_calcula_comissao(): void
    {
        $vendedor = Vendedor::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'João Vendedor',
            'percentual_comissao' => 10,
            'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'vendedor_id' => $vendedor->id,
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('comissao', '3.60'); // 10% de R$36,00
        $response->assertJsonPath('vendedor.nome', 'João Vendedor');
    }

    public function test_venda_de_visita_agendada_reserva_e_confirma_vaga(): void
    {
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id,
            'data_hora' => now()->addDay(),
            'vagas_total' => 5,
            'vagas_reservadas' => 0,
            'status' => 'aberta',
            'valor_visita' => 60.00,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '120.00');
        $this->assertSame(3, $agenda->fresh()->vagasDisponiveis());
    }

    public function test_venda_fiscal_emite_nfce_via_gateway_simulado(): void
    {
        \App\Models\ConfigFiscal::create([
            'empresa_id' => $this->empresa->id,
            'crt' => '1',
            'serie_nfce_atual' => '1',
            'numero_nfce_atual' => 0,
            'ambiente_ativo' => 'homologacao',
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'fiscal',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 1],
            ],
        ]);

        $response->assertCreated();

        $documento = \App\Models\DocumentoFiscal::where('venda_id', $response->json('id'))->first();
        $this->assertNotNull($documento);
        $this->assertSame('autorizada', $documento->status);
    }

    public function test_venda_fiscal_sem_config_fiscal_retorna_422(): void
    {
        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'fiscal',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_caixa_carrega(): void
    {
        $response = $this->get("/pdv/{$this->empresa->slug}/caixa");

        $response->assertOk();
        $response->assertSee('PDV — Frente de Caixa');
    }

    public function test_visitante_nao_autenticado_e_redirecionado_ao_login(): void
    {
        auth()->logout();

        $response = $this->get("/pdv/{$this->empresa->slug}/caixa");

        $response->assertRedirect('/login');
    }
}
