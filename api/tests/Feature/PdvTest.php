<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\ConfigFiscal;
use App\Models\Cupom;
use App\Models\DescontoPdv;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\KitComponente;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\User;
use App\Models\Atendente;
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

    private Atendente $atendentePadrao;

    private FormaPagamento $formaPagamentoPadrao;

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

        $this->atendentePadrao = Atendente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Atendente Padrão', 'ativo' => true,
        ]);
        $this->formaPagamentoPadrao = FormaPagamento::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Dinheiro',
            'tipo' => 'dinheiro', 'codigo_tpag' => '01', 'ativo' => true,
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

    /**
     * Kit "Caneca (fixa) + 2 cervejas à escolha" por R$ 50 (soma dos itens: 10 + 2 x 18 = 46).
     *
     * @return array{kit: Produto, caneca: Produto, sabores: array<int, ProdutoVariacao>}
     */
    private function criarKit(): array
    {
        $caneca = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Caneca', 'tipo' => 'fisico', 'preco_venda' => 10, 'estoque_atual' => 5,
        ]);
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Artesanal', 'tipo' => 'fisico', 'preco_venda' => 18,
        ]);
        $sabores = collect(['Pilsen', 'IPA'])->map(fn ($nome) => ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => $nome, 'estoque_atual' => 10,
        ]))->all();
        $kit = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Kit Caneca + 2 Cervejas',
            'tipo' => 'fisico', 'preco_venda' => 50.00, 'eh_kit' => true,
        ]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $caneca->id, 'tipo' => 'fixo', 'quantidade' => 1]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $cerveja->id, 'tipo' => 'escolha', 'quantidade' => 2]);

        return compact('kit', 'caneca', 'sabores');
    }

    private function venderKit(Produto $kit, array $escolhas, string $tipoDoc = 'nao_fiscal')
    {
        return $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => $tipoDoc,
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $kit->id, 'quantidade' => 1, 'escolhas' => $escolhas]],
        ]);
    }

    public function test_kit_aparece_no_pdv_com_a_composicao(): void
    {
        ['kit' => $kit] = $this->criarKit();

        $json = collect($this->getJson("/pdv/{$this->empresa->slug}/produtos?busca=Kit Caneca")->assertOk()->json())->firstWhere('id', $kit->id);

        $this->assertTrue($json['kit']['disponivel']);
        $this->assertSame('Caneca', $json['kit']['fixos'][0]['nome']);
        $this->assertSame(2, $json['kit']['escolhas'][0]['quantidade']);
        $this->assertCount(2, $json['kit']['escolhas'][0]['variacoes']);
    }

    public function test_venda_de_kit_cobra_o_preco_do_kit_e_baixa_cada_componente(): void
    {
        ['kit' => $kit, 'caneca' => $caneca, 'sabores' => [$pilsen, $ipa]] = $this->criarKit();

        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 1], ['variacao_id' => $ipa->id, 'quantidade' => 1]])
            ->assertCreated()
            ->assertJsonPath('valor_total', '50.00')
            ->assertJsonCount(3, 'itens.0.composicao');

        $this->assertSame(4, $caneca->fresh()->estoque_atual);
        $this->assertSame(9, $pilsen->fresh()->estoque_atual);
        $this->assertSame(9, $ipa->fresh()->estoque_atual);
    }

    public function test_venda_de_kit_exige_a_quantidade_de_escolhas_do_kit(): void
    {
        ['kit' => $kit, 'caneca' => $caneca, 'sabores' => [$pilsen]] = $this->criarKit();

        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 1]])->assertStatus(422);

        $this->assertSame(5, $caneca->fresh()->estoque_atual);
        $this->assertSame(10, $pilsen->fresh()->estoque_atual);
    }

    /**
     * Kit Coringa: 3 itens à escolha - 3 cervejas OU 2 cervejas + 1 copo (Windsor ou Caldereta).
     * Cerveja entra com teto 3 e Copo com teto 1.
     *
     * @return array{kit: Produto, cervejas: array<int, ProdutoVariacao>, copos: array<int, ProdutoVariacao>}
     */
    private function criarKitCoringa(): array
    {
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Artesanal', 'tipo' => 'fisico', 'preco_venda' => 18,
        ]);
        $cervejas = collect(['Pilsen', 'IPA'])->map(fn ($nome) => ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => $nome, 'estoque_atual' => 10,
        ]))->all();
        $copo = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Copo', 'tipo' => 'fisico', 'preco_venda' => 20,
        ]);
        $copos = collect(['Windsor', 'Caldereta'])->map(fn ($nome) => ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $copo->id, 'tamanho' => $nome, 'estoque_atual' => 5,
        ]))->all();
        $kit = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Kit Coringa', 'tipo' => 'fisico',
            'preco_venda' => 60.00, 'eh_kit' => true, 'kit_total_escolhas' => 3,
        ]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $cerveja->id, 'tipo' => 'escolha', 'quantidade' => 3]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $copo->id, 'tipo' => 'escolha', 'quantidade' => 1]);

        return compact('kit', 'cervejas', 'copos');
    }

    public function test_kit_coringa_aceita_tres_cervejas(): void
    {
        ['kit' => $kit, 'cervejas' => [$pilsen, $ipa]] = $this->criarKitCoringa();

        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 2], ['variacao_id' => $ipa->id, 'quantidade' => 1]])
            ->assertCreated()->assertJsonPath('valor_total', '60.00');

        $this->assertSame(8, $pilsen->fresh()->estoque_atual);
        $this->assertSame(9, $ipa->fresh()->estoque_atual);
    }

    public function test_kit_coringa_aceita_duas_cervejas_e_um_copo_windsor_ou_caldereta(): void
    {
        ['kit' => $kit, 'cervejas' => [$pilsen], 'copos' => [$windsor, $caldereta]] = $this->criarKitCoringa();

        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 2], ['variacao_id' => $caldereta->id, 'quantidade' => 1]])
            ->assertCreated()->assertJsonCount(2, 'itens.0.composicao');

        $this->assertSame(8, $pilsen->fresh()->estoque_atual);
        $this->assertSame(4, $caldereta->fresh()->estoque_atual);
        $this->assertSame(5, $windsor->fresh()->estoque_atual);
    }

    public function test_kit_coringa_recusa_mais_de_um_copo_e_total_diferente_de_tres(): void
    {
        ['kit' => $kit, 'cervejas' => [$pilsen], 'copos' => [$windsor, $caldereta]] = $this->criarKitCoringa();

        // 1 cerveja + 2 copos: passa do máximo de copos
        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 1], ['variacao_id' => $windsor->id, 'quantidade' => 1], ['variacao_id' => $caldereta->id, 'quantidade' => 1]])
            ->assertStatus(422);
        // só 2 itens
        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 2]])->assertStatus(422);
        // 4 itens
        $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 4]])->assertStatus(422);

        $this->assertSame(10, $pilsen->fresh()->estoque_atual);
        $this->assertSame(5, $windsor->fresh()->estoque_atual);
    }

    public function test_kit_coringa_vai_para_o_pdv_com_o_total_de_escolhas(): void
    {
        ['kit' => $kit] = $this->criarKitCoringa();

        $json = collect($this->getJson("/pdv/{$this->empresa->slug}/produtos?busca=Coringa")->assertOk()->json())->firstWhere('id', $kit->id);

        $this->assertSame(3, $json['kit']['total_escolhas']);
        $this->assertTrue($json['kit']['disponivel']);
        $this->assertCount(2, $json['kit']['escolhas']);
    }

    public function test_nfce_do_kit_sai_com_um_item_por_componente_e_o_valor_do_kit_rateado(): void
    {
        ConfigFiscal::create([
            'empresa_id' => $this->empresa->id, 'crt' => '1', 'serie_nfce_atual' => '1',
            'numero_nfce_atual' => 0, 'ambiente_ativo' => 'homologacao',
        ]);
        ['kit' => $kit, 'sabores' => [$pilsen, $ipa]] = $this->criarKit();

        $resposta = $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 1], ['variacao_id' => $ipa->id, 'quantidade' => 1]], 'fiscal')
            ->assertCreated();

        $itens = DocumentoFiscal::where('venda_id', $resposta->json('id'))->firstOrFail()->itens;

        $this->assertCount(3, $itens);
        $this->assertEqualsWithDelta(50.00, $itens->sum('valor_total'), 0.001);
        // caneca: 10/46 de 50 = 10,87; cervejas: 18/46 de 50 = 19,57 (a última absorve o centavo)
        $this->assertEqualsWithDelta(10.87, (float) $itens->firstWhere('descricao', 'Caneca')->valor_total, 0.001);
    }

    public function test_cancelar_venda_de_kit_devolve_o_estoque_de_cada_componente(): void
    {
        ['kit' => $kit, 'caneca' => $caneca, 'sabores' => [$pilsen, $ipa]] = $this->criarKit();
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@pdv-teste.com', 'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);

        $vendaId = $this->venderKit($kit, [['variacao_id' => $pilsen->id, 'quantidade' => 2]])->assertCreated()->json('id');
        $this->assertSame(8, $pilsen->fresh()->estoque_atual);

        $this->actingAs($admin)
            ->postJson("/pdv/{$this->empresa->slug}/vendas/{$vendaId}/cancelar", ['motivo' => 'Cliente desistiu da compra'])
            ->assertOk();

        $this->assertSame(5, $caneca->fresh()->estoque_atual);
        $this->assertSame(10, $pilsen->fresh()->estoque_atual);
        $this->assertSame(10, $ipa->fresh()->estoque_atual);
    }

    public function test_produto_somente_loja_virtual_nao_aparece_nem_e_vendido_no_pdv(): void
    {
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Exclusiva da Loja',
            'tipo' => 'fisico', 'preco_venda' => 20.00, 'estoque_atual' => 10,
            'loja_virtual' => true, 'somente_loja_virtual' => true,
        ]);

        $this->getJson("/pdv/{$this->empresa->slug}/produtos?busca=Exclusiva")->assertOk()->assertJsonCount(0);

        $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertStatus(422);

        $this->assertSame(10, $produto->fresh()->estoque_atual);
    }

    public function test_produto_desativado_some_do_pdv_e_nao_pode_ser_vendido(): void
    {
        $this->produto->update(['ativo' => false]);

        $this->getJson("/pdv/{$this->empresa->slug}/produtos?busca=chopp")->assertOk()->assertJsonCount(0);

        $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->assertStatus(422);

        $this->assertSame(10, $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_nao_fiscal_de_produto_debita_estoque(): void
    {
        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
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
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('comissao', '3.60'); // 10% de R$36,00
        $response->assertJsonPath('vendedor.nome', 'João Vendedor');
    }

    public function test_venda_registra_vendedor_e_atendente_separadamente(): void
    {
        $vendedor = Vendedor::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Guia da Visita', 'percentual_comissao' => 10, 'ativo' => true,
        ]);
        $atendente = Atendente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Quem Operou o Caixa', 'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'vendedor_id' => $vendedor->id,
            'atendente_id' => $atendente->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('vendedor.nome', 'Guia da Visita');
        $response->assertJsonPath('atendente.nome', 'Quem Operou o Caixa');
    }

    public function test_lista_de_vendedores_do_pdv_nao_expoe_a_chave_pix(): void
    {
        Vendedor::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Vendedor Pix', 'percentual_comissao' => 5,
            'ativo' => true, 'chave_pix' => 'pix@exemplo.com',
        ]);

        $this->getJson("/pdv/{$this->empresa->slug}/vendedores")
            ->assertOk()->assertJsonPath('0.nome', 'Vendedor Pix')->assertJsonMissingPath('0.chave_pix');
    }

    public function test_pdv_cadastra_vendedor_com_comissao_fixa_de_5_por_cento(): void
    {
        $this->postJson("/pdv/{$this->empresa->slug}/vendedores", [
            'nome' => 'Guia Novo', 'telefone' => '(54) 99999-0000', 'chave_pix' => 'guia@exemplo.com',
            'percentual_comissao' => 30, // ignorado: o PDV não define comissão
        ])->assertCreated()->assertJsonPath('nome', 'Guia Novo')->assertJsonMissingPath('chave_pix');

        $vendedor = Vendedor::where('nome', 'Guia Novo')->firstOrFail();
        $this->assertSame('5.00', $vendedor->percentual_comissao);
        $this->assertSame('guia@exemplo.com', $vendedor->chave_pix);
        $this->assertSame('(54) 99999-0000', $vendedor->telefone);
        $this->assertTrue($vendedor->ativo);
        $this->assertSame($this->empresa->id, $vendedor->empresa_id);
    }

    public function test_pdv_exige_nome_celular_e_chave_pix_ao_cadastrar_vendedor(): void
    {
        $this->postJson("/pdv/{$this->empresa->slug}/vendedores", ['nome' => 'Sem Dados'])
            ->assertUnprocessable()->assertJsonValidationErrors(['telefone', 'chave_pix']);
    }

    public function test_lista_atendentes_ativos_para_o_pdv(): void
    {
        Atendente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Ativo', 'ativo' => true]);
        Atendente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Inativo', 'ativo' => false]);

        $response = $this->getJson("/pdv/{$this->empresa->slug}/atendentes");

        $nomes = collect($response->json())->pluck('nome');
        $response->assertOk();
        $this->assertTrue($nomes->contains('Ativo'));
        $this->assertFalse($nomes->contains('Inativo'));
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
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '120.00');
        $this->assertSame(3, $agenda->fresh()->vagasDisponiveis());
    }

    public function test_venda_falha_sem_atendente_ou_sem_forma_pagamento(): void
    {
        $semAtendente = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ]);
        $semAtendente->assertStatus(422)->assertJsonValidationErrors('atendente_id');

        $semFormaPagamento = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ]);
        $semFormaPagamento->assertStatus(422)->assertJsonValidationErrors('forma_pagamento_id');
    }

    public function test_cupom_de_desconto_aplica_so_no_valor_da_visita(): void
    {
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'vagas_reservadas' => 0, 'status' => 'aberta', 'valor_visita' => 100.00,
        ]);
        $cupom = Cupom::create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'DESCONTO10',
            'tipo' => 'percentual', 'valor' => 10, 'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]], // R$18, sem desconto
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1, // R$100, com 10% de desconto = R$90
            'cupom_codigo' => 'desconto10',
        ]);

        $response->assertCreated();
        // 18 (produto, sem desconto) + 90 (visita com 10% off) = 108
        $response->assertJsonPath('valor_total', '108.00');
        $response->assertJsonPath('valor_desconto', '10.00');
        $this->assertSame(1, $cupom->fresh()->usos_realizados);
    }

    public function test_cupom_com_teto_desconta_metade_com_um_unico_ticket_no_pdv(): void
    {
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'vagas_reservadas' => 0, 'status' => 'aberta', 'valor_visita' => 100.00,
        ]);
        // Exemplo do cliente: 15% com teto de R$24 (2+ tickets) -> R$12 com 1 ticket.
        Cupom::create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'DESC15',
            'tipo' => 'percentual', 'valor' => 15, 'valor_maximo_desconto' => 24, 'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1, // 15% de R$100 seria R$15, mas o teto (1 ticket) é R$12
            'cupom_codigo' => 'DESC15',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_desconto', '12.00');
        $response->assertJsonPath('valor_total', '88.00');
    }

    private function agendaComTicketDe(float $valor, int $vagas = 10): AgendaVisitacao
    {
        return AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => $vagas, 'vagas_reservadas' => 0, 'status' => 'aberta', 'valor_visita' => $valor,
        ]);
    }

    private function descontoPdv(string $descricao, float $percentual, string $aplicaEm, bool $ativo = true): DescontoPdv
    {
        return DescontoPdv::create([
            'empresa_id' => $this->empresa->id, 'descricao' => $descricao,
            'percentual' => $percentual, 'aplica_em' => $aplicaEm, 'ativo' => $ativo,
        ]);
    }

    public function test_desconto_de_produtos_incide_so_nos_produtos(): void
    {
        $agenda = $this->agendaComTicketDe(100);
        $desconto = $this->descontoPdv('10% Produtos', 10, 'produtos');

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]], // 2 x 18 = 36
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1, // 100, sem desconto
            'desconto_pdv_id' => $desconto->id,
        ]);

        // 10% de 36 = 3,60 -> 136 - 3,60 = 132,40 (visita intacta)
        $response->assertCreated();
        $response->assertJsonPath('valor_desconto', '3.60');
        $response->assertJsonPath('valor_total', '132.40');
        $response->assertJsonPath('desconto_pdv_id', $desconto->id);
    }

    public function test_desconto_de_visitas_cobre_no_maximo_dois_tickets_e_nao_toca_produtos(): void
    {
        $agenda = $this->agendaComTicketDe(100);
        $desconto = $this->descontoPdv('50% Visitas', 50, 'visitas');

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]], // 18, sem desconto
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 3, // 300; 50% só de 2 tickets = 100
            'desconto_pdv_id' => $desconto->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_desconto', '100.00');
        $response->assertJsonPath('valor_total', '218.00'); // 18 + 300 - 100
    }

    public function test_desconto_de_visitas_com_um_ticket_desconta_so_aquele_ticket(): void
    {
        $agenda = $this->agendaComTicketDe(80);
        $desconto = $this->descontoPdv('50% Visitas', 50, 'visitas');

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1,
            'desconto_pdv_id' => $desconto->id,
        ]);

        $response->assertCreated()->assertJsonPath('valor_total', '40.00');
    }

    public function test_desconto_de_visitas_nao_combina_com_cupom(): void
    {
        $agenda = $this->agendaComTicketDe(100);
        $desconto = $this->descontoPdv('50% Visitas', 50, 'visitas');
        Cupom::create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'DESC10',
            'tipo' => 'percentual', 'valor' => 10, 'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1,
            'cupom_codigo' => 'DESC10',
            'desconto_pdv_id' => $desconto->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Cupom::where('codigo', 'DESC10')->first()->usos_realizados);
    }

    public function test_desconto_de_produtos_pode_ser_usado_junto_com_cupom_da_visita(): void
    {
        $agenda = $this->agendaComTicketDe(100);
        $desconto = $this->descontoPdv('10% Produtos', 10, 'produtos');
        Cupom::create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'DESC10',
            'tipo' => 'percentual', 'valor' => 10, 'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]], // 18 -> 1,80
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1, // 100 -> cupom 10
            'cupom_codigo' => 'DESC10',
            'desconto_pdv_id' => $desconto->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_desconto', '11.80');
        $response->assertJsonPath('valor_total', '106.20'); // 118 - 11,80
    }

    public function test_desconto_sem_o_item_correspondente_e_recusado(): void
    {
        $agenda = $this->agendaComTicketDe(100);
        $soProdutos = $this->descontoPdv('10% Produtos', 10, 'produtos');
        $soVisitas = $this->descontoPdv('50% Visitas', 50, 'visitas');

        $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'agenda_visitacao_id' => $agenda->id,
            'agenda_quantidade' => 1, // só visita
            'desconto_pdv_id' => $soProdutos->id,
        ])->assertStatus(422);

        $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]], // só produto
            'desconto_pdv_id' => $soVisitas->id,
        ])->assertStatus(422);

        $this->assertSame(10, $this->produto->fresh()->estoque_atual); // nada foi baixado
    }

    public function test_desconto_desativado_e_recusado_e_nao_aparece_na_lista_do_pdv(): void
    {
        $this->descontoPdv('10% Produtos', 10, 'produtos');
        $inativo = $this->descontoPdv('Antigo', 20, 'produtos', false);

        $this->getJson("/pdv/{$this->empresa->slug}/descontos")->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.descricao', '10% Produtos');

        $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
            'desconto_pdv_id' => $inativo->id,
        ])->assertStatus(422);
    }

    public function test_cupom_sem_visita_agendada_e_rejeitado(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'SOVISITA',
            'tipo' => 'percentual', 'valor' => 10, 'ativo' => true,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
            'cupom_codigo' => 'SOVISITA',
        ]);

        $response->assertStatus(422);
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
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
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

    public function test_confirma_entrada_de_ticket_pago(): void
    {
        $venda = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('id');

        $busca = $this->getJson("/pdv/{$this->empresa->slug}/verificar/{$venda}");
        $busca->assertOk()->assertJsonPath('status_pagamento', 'pago')->assertJsonPath('check_in_em', null);

        $checkIn = $this->postJson("/pdv/{$this->empresa->slug}/verificar/{$venda}/check-in", [
            'atendente_id' => $this->atendentePadrao->id,
        ]);
        $checkIn->assertOk();
        $this->assertNotNull($checkIn->json('check_in_em'));

        $segundaTentativa = $this->postJson("/pdv/{$this->empresa->slug}/verificar/{$venda}/check-in", [
            'atendente_id' => $this->atendentePadrao->id,
        ]);
        $segundaTentativa->assertStatus(422);
    }

    public function test_ticket_pendente_nao_pode_confirmar_entrada(): void
    {
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'data_hora' => now()->addDay(),
            'vagas_total' => 5, 'vagas_reservadas' => 0, 'status' => 'aberta', 'valor_visita' => 60.00,
        ]);
        $venda = \App\Models\Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'site', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pendente', 'valor_total' => 60, 'data_venda' => now(),
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/verificar/{$venda->id}/check-in");

        $response->assertStatus(422);
    }

    public function test_venda_abaixo_da_quantidade_minima_e_recusada(): void
    {
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cerveja Pilsen 600ml',
            'tipo' => 'fisico',
            'preco_venda' => 12.00,
            'quantidade_minima_venda' => 6,
        ]);
        $sabor1 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'Pilsen', 'estoque_atual' => 20,
        ]);
        $sabor2 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 20,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor1->id, 'quantidade' => 2],
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor2->id, 'quantidade' => 3],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(20, $sabor1->fresh()->estoque_atual);
        $this->assertSame(20, $sabor2->fresh()->estoque_atual);
    }

    public function test_venda_com_variacoes_misturadas_bate_a_quantidade_minima(): void
    {
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cerveja Pilsen 600ml',
            'tipo' => 'fisico',
            'preco_venda' => 12.00,
            'quantidade_minima_venda' => 6,
        ]);
        $sabor1 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'Pilsen', 'estoque_atual' => 20,
        ]);
        $sabor2 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 20,
        ]);

        $response = $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor1->id, 'quantidade' => 4],
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor2->id, 'quantidade' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '72.00');
        $this->assertSame(16, $sabor1->fresh()->estoque_atual);
        $this->assertSame(18, $sabor2->fresh()->estoque_atual);
    }

    public function test_extrato_do_caixa_mostra_especies_abertura_saidas_e_resultado(): void
    {
        $base = "/pdv/{$this->empresa->slug}";
        $pix = FormaPagamento::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'PIX', 'tipo' => 'pix', 'codigo_tpag' => '17', 'ativo' => true,
        ]);

        $this->postJson("$base/caixa-abrir", ['valor' => 100])->assertCreated();

        // 2 x R$ 18,00 em dinheiro = 36,00 ; 1 x R$ 18,00 no PIX
        $this->postJson("$base/vendas", [
            'tipo_doc' => 'nao_fiscal', 'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ])->assertCreated();
        $this->postJson("$base/vendas", [
            'tipo_doc' => 'nao_fiscal', 'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $pix->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->assertCreated();

        $this->postJson("$base/caixa-suprimento", ['valor' => 20, 'observacao' => 'Troco extra'])->assertCreated();
        $this->postJson("$base/caixa-sangria", ['valor' => 50, 'observacao' => 'Pagamento fornecedor'])->assertCreated();

        // esperado em dinheiro: 100 + 36 + 20 - 50 = 106,00
        $this->get("$base/caixa-extrato-impressao")
            ->assertOk()
            ->assertSee('PIX')
            ->assertSee('Dinheiro')
            ->assertSee('R$ 54,00')
            ->assertSee('Pagamento fornecedor')
            ->assertSee('R$ 106,00');

        $this->postJson("$base/caixa-fechar", ['valor' => 104])->assertCreated();

        $this->get("$base/caixa-extrato-impressao")
            ->assertOk()
            ->assertSee('Valor contado no fechamento')
            ->assertSee('R$ -2,00');
    }

    public function test_lista_de_visitas_pagas_traz_so_pedidos_pagos_da_loja_com_visita_futura(): void
    {
        $visita = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Visita guiada', 'tipo' => 'agendamento', 'preco_venda' => 50.00,
        ]);
        $agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $visita->id, 'data_hora' => now()->addDays(2),
            'vagas_total' => 10, 'vagas_reservadas' => 3, 'status' => 'aberta', 'valor_visita' => 50.00,
        ]);

        $criar = function (string $canal, string $status, string $nomeCliente) use ($agenda, $visita) {
            $cli = \App\Models\Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => $nomeCliente]);
            $venda = \App\Models\Venda::create([
                'empresa_id' => $this->empresa->id, 'cliente_id' => $cli->id, 'canal' => $canal,
                'tipo_doc' => 'nao_fiscal', 'status_pagamento' => $status, 'valor_total' => 100.00,
            ]);
            \App\Models\ItemVenda::create([
                'empresa_id' => $this->empresa->id, 'venda_id' => $venda->id, 'produto_id' => $visita->id,
                'agenda_visitacao_id' => $agenda->id, 'quantidade' => 2, 'valor_unitario' => 50.00, 'valor_total' => 100.00,
            ]);

            return $venda;
        };

        $paga = $criar('site', 'pago', 'Cliente Pago Site');
        $criar('site', 'pendente', 'Cliente Pendente');
        $criar('pdv', 'pago', 'Cliente Balcao');

        $this->get("/pdv/{$this->empresa->slug}/visitas-pagas")
            ->assertOk()
            ->assertSee('Cliente Pago Site')
            ->assertSee('R$ 100,00')
            ->assertDontSee('Cliente Pendente')
            ->assertDontSee('Cliente Balcao');

        $this->assertNotNull($paga->id);
    }
}
