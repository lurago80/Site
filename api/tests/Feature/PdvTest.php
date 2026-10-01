<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\Cupom;
use App\Models\DescontoPdv;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Plano;
use App\Models\Produto;
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

    public function test_kit_nao_aparece_nem_e_vendido_no_pdv(): void
    {
        $kit = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Kit Chopp Caneca',
            'tipo' => 'fisico', 'preco_venda' => 90.00, 'eh_kit' => true,
        ]);

        $this->getJson("/pdv/{$this->empresa->slug}/produtos?busca=Kit Chopp")->assertOk()->assertJsonCount(0);

        $this->postJson("/pdv/{$this->empresa->slug}/vendas", [
            'tipo_doc' => 'nao_fiscal',
            'atendente_id' => $this->atendentePadrao->id,
            'forma_pagamento_id' => $this->formaPagamentoPadrao->id,
            'itens' => [['produto_id' => $kit->id, 'quantidade' => 1]],
        ])->assertStatus(422);
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
}
