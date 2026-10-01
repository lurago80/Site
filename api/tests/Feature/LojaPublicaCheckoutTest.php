<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\Cupom;
use App\Models\Empresa;
use App\Models\FreteRegra;
use App\Models\KitComponente;
use App\Models\ProdutoVariacao;
use App\Models\Venda;
use App\Models\Plano;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

class LojaPublicaCheckoutTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private AgendaVisitacao $agenda;

    private Produto $produtoFisico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Cervejaria Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'cervejaria-teste',
            'modulo_agendamento_ativo' => true,
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->asEmpresa($this->empresa->id);

        $this->agenda = AgendaVisitacao::create([
            'empresa_id' => $this->empresa->id,
            'data_hora' => now()->addDay(),
            'vagas_total' => 3,
            'vagas_reservadas' => 0,
            'status' => 'aberta',
            'valor_visita' => 60.00,
        ]);

        $this->produtoFisico = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Chopp Artesanal 500ml',
            'tipo' => 'fisico',
            'preco_venda' => 18.00,
            'estoque_atual' => 10,
        ]);
    }

    public function test_lista_agenda_de_visitas_em_aberto(): void
    {
        $response = $this->getJson("/api/loja/{$this->empresa->slug}/visitas");

        $response->assertOk()->assertJsonCount(1);
        $this->assertSame(3, $response->json('0.vagas_disponiveis'));
    }

    public function test_produto_com_loja_virtual_desativada_nao_aparece_no_catalogo_publico(): void
    {
        $produtoOculto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto Só PDV',
            'tipo' => 'fisico',
            'preco_venda' => 10.00,
            'estoque_atual' => 5,
            'loja_virtual' => false,
        ]);

        $response = $this->getJson("/api/loja/{$this->empresa->slug}/produtos");

        $response->assertOk();
        $nomes = collect($response->json())->pluck('nome');
        $this->assertTrue($nomes->contains('Chopp Artesanal 500ml'));
        $this->assertFalse($nomes->contains('Produto Só PDV'));
    }

    public function test_slug_inexistente_retorna_404(): void
    {
        $this->getJson('/api/loja/loja-que-nao-existe/produtos')->assertNotFound();
    }

    public function test_info_publica_retorna_identidade_visual_sem_dados_sensiveis(): void
    {
        $this->empresa->update(['logo_url' => 'https://exemplo.com/logo.png', 'cor_primaria' => '#394285']);

        $response = $this->getJson("/api/loja/{$this->empresa->slug}/info");

        $response->assertOk()
            ->assertJsonPath('razao_social', 'Cervejaria Teste')
            ->assertJsonPath('logo_url', 'https://exemplo.com/logo.png')
            ->assertJsonPath('cor_primaria', '#394285')
            ->assertJsonPath('modulo_agendamento_ativo', true)
            ->assertJsonMissingPath('cnpj')
            ->assertJsonMissingPath('logradouro');
    }

    public function test_config_pagamento_publica_sem_gateway_retorna_nulo(): void
    {
        $response = $this->getJson("/api/loja/{$this->empresa->slug}/config-pagamento-publica");

        $response->assertOk()->assertJsonPath('gateway', null)->assertJsonPath('public_key', null);
    }

    public function test_config_pagamento_publica_com_gateway_ativo_retorna_apenas_a_chave_publica(): void
    {
        \App\Models\ConfigPagamento::create([
            'empresa_id' => $this->empresa->id, 'gateway' => 'mercadopago', 'ambiente' => 'sandbox',
            'access_token' => 'segredo-nao-pode-vazar', 'public_key' => 'chave-publica-123', 'ativo' => true,
        ]);

        $response = $this->getJson("/api/loja/{$this->empresa->slug}/config-pagamento-publica");

        $response->assertOk()->assertJsonPath('gateway', 'mercadopago')->assertJsonPath('public_key', 'chave-publica-123');
        $this->assertStringNotContainsString('segredo-nao-pode-vazar', $response->getContent());
    }

    public function test_fluxo_completo_reserva_e_checkout_de_visita(): void
    {
        $reservaResponse = $this->postJson("/api/loja/{$this->empresa->slug}/reservas", [
            'agenda_visitacao_id' => $this->agenda->id,
            'quantidade' => 2,
        ]);

        $reservaResponse->assertCreated();
        $reservaId = $reservaResponse->json('reserva_id');

        $checkoutResponse = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'João Comprador',
                'email' => 'joao@example.com',
                'cpf_cnpj' => '123.456.789-00',
                'rg' => '11.222.333-4',
                'telefone' => '11988887777',
                'consentimento_lgpd' => true,
            ],
            'reserva_id' => $reservaId,
            'forma_pagamento' => 'pix',
        ]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('valor_total', '120.00');
        $checkoutResponse->assertJsonPath('status_pagamento', 'pago');
        $this->assertSame('joao@example.com', Cliente::first()->email);

        $this->assertSame(1, $this->agenda->fresh()->vagasDisponiveis());
    }

    public function test_checkout_falha_sem_consentimento_lgpd(): void
    {
        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'João Comprador',
                'consentimento_lgpd' => false,
            ],
            'itens' => [
                ['produto_id' => $this->produtoFisico->id, 'quantidade' => 1],
            ],
            'forma_pagamento' => 'pix',
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_de_produto_fisico_debita_estoque(): void
    {
        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'Maria Compradora',
                'email' => 'maria@example.com',
                'cpf_cnpj' => '987.654.321-00',
                'rg' => '22.333.444-5',
                'telefone' => '11977776666',
                'cep' => '01310-100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => [
                ['produto_id' => $this->produtoFisico->id, 'quantidade' => 3],
            ],
            'forma_pagamento' => 'cartao',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '54.00');
        $this->assertSame(7, $this->produtoFisico->fresh()->estoque_atual);
    }

    public function test_reserva_acima_da_capacidade_retorna_409(): void
    {
        $response = $this->postJson("/api/loja/{$this->empresa->slug}/reservas", [
            'agenda_visitacao_id' => $this->agenda->id,
            'quantidade' => 99,
        ]);

        $response->assertStatus(409);
    }

    public function test_valida_cupom_percentual_e_retorna_o_desconto(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'BEMVINDO10',
            'tipo' => 'percentual',
            'valor' => 10,
            'ativo' => true,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/cupons/validar", [
            'codigo' => 'bemvindo10',
            'subtotal_visitas' => 100,
            'quantidade_tickets' => 2,
        ]);

        $response->assertOk();
        $response->assertJson(['valido' => true, 'valor_desconto' => 10]);
    }

    public function test_cupom_percentual_desconta_no_maximo_dois_tickets(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'QM15-TESTE',
            'tipo' => 'percentual',
            'valor' => 15,
            'ativo' => true,
        ]);

        // Ticket de R$ 80: 1 ticket -> R$ 12; 2 tickets -> R$ 24; 3 tickets -> continua R$ 24.
        foreach ([1 => 12, 2 => 24, 3 => 24, 5 => 24] as $tickets => $esperado) {
            $this->postJson("/api/loja/{$this->empresa->slug}/cupons/validar", [
                'codigo' => 'QM15-TESTE',
                'subtotal_visitas' => 80 * $tickets,
                'quantidade_tickets' => $tickets,
            ])->assertOk()->assertJson(['valido' => true, 'valor_desconto' => $esperado]);
        }
    }

    public function test_cupom_com_teto_desconta_metade_com_um_unico_ticket(): void
    {
        // Exemplo do cliente: cupom de 30% com teto de R$48 (2+ tickets) ->
        // R$24 com exatamente 1 ticket (metade do teto).
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'DESC30',
            'tipo' => 'percentual',
            'valor' => 30,
            'valor_maximo_desconto' => 48,
            'ativo' => true,
        ]);

        $comUmTicket = $this->postJson("/api/loja/{$this->empresa->slug}/cupons/validar", [
            'codigo' => 'DESC30',
            'subtotal_visitas' => 100, // 30% seria R$30, mas o teto (1 ticket) é R$24
            'quantidade_tickets' => 1,
        ]);
        $comUmTicket->assertOk()->assertJson(['valido' => true, 'valor_desconto' => 24]);

        $comDoisTickets = $this->postJson("/api/loja/{$this->empresa->slug}/cupons/validar", [
            'codigo' => 'DESC30',
            'subtotal_visitas' => 200, // 30% seria R$60, teto (2+ tickets) é R$48
            'quantidade_tickets' => 2,
        ]);
        $comDoisTickets->assertOk()->assertJson(['valido' => true, 'valor_desconto' => 48]);
    }

    public function test_cupom_sem_visita_no_carrinho_e_rejeitado_na_pre_validacao(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'SOVISITA',
            'tipo' => 'percentual',
            'valor' => 10,
            'ativo' => true,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/cupons/validar", [
            'codigo' => 'SOVISITA',
            'subtotal_visitas' => 0,
            'quantidade_tickets' => 0,
        ]);

        $response->assertStatus(422)->assertJson(['valido' => false]);
    }

    public function test_cupom_expirado_e_recusado(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'VENCIDO',
            'tipo' => 'valor_fixo',
            'valor' => 5,
            'valido_ate' => now()->subDay(),
            'ativo' => true,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/cupons/validar", [
            'codigo' => 'VENCIDO',
            'subtotal_visitas' => 100,
            'quantidade_tickets' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['valido' => false]);
    }

    public function test_checkout_aplica_o_desconto_do_cupom_so_na_visita_e_registra_o_uso(): void
    {
        // Cupom nunca desconta produto (regra de negócio explícita) - só a
        // visita agendada. Teto de R$48 com 2+ tickets, ver
        // Cupom::calcularDescontoVisita.
        $cupom = Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'DESC30',
            'tipo' => 'percentual',
            'valor' => 30,
            'valor_maximo_desconto' => 48,
            'ativo' => true,
        ]);

        $reservaResponse = $this->postJson("/api/loja/{$this->empresa->slug}/reservas", [
            'agenda_visitacao_id' => $this->agenda->id,
            'quantidade' => 2,
        ]);
        $reservaId = $reservaResponse->json('reserva_id');

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'Maria Compradora',
                'email' => 'maria@example.com',
                'cpf_cnpj' => '987.654.321-00',
                'rg' => '22.333.444-5',
                'telefone' => '11977776666',
                'cep' => '01310-100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => [
                ['produto_id' => $this->produtoFisico->id, 'quantidade' => 2], // R$36, sem desconto
            ],
            'reserva_id' => $reservaId, // 2 × R$60 = R$120, 30% seria R$36, mas teto de R$48 (2+ tickets) não bate
            'forma_pagamento' => 'cartao',
            'cupom_codigo' => 'desc30',
        ]);

        $response->assertCreated();
        // 36,00 (produto, sem desconto) + 120,00 (visita) - 36,00 (30% de 120, dentro do teto de 48) = 120,00.
        $response->assertJsonPath('valor_total', '120.00');
        $response->assertJsonPath('valor_desconto', '36.00');
        $this->assertSame(1, $cupom->fresh()->usos_realizados);
    }

    public function test_checkout_cupom_sem_visita_agendada_e_rejeitado(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'DESC5',
            'tipo' => 'valor_fixo',
            'valor' => 5,
            'ativo' => true,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'Maria Compradora',
                'email' => 'maria@example.com',
                'cpf_cnpj' => '987.654.321-00',
                'rg' => '22.333.444-5',
                'telefone' => '11977776666',
                'cep' => '01310-100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => [
                ['produto_id' => $this->produtoFisico->id, 'quantidade' => 2],
            ],
            'forma_pagamento' => 'cartao',
            'cupom_codigo' => 'desc5',
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_recusa_cupom_que_atingiu_o_limite_de_usos(): void
    {
        Cupom::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'ESGOTADO',
            'tipo' => 'valor_fixo',
            'valor' => 5,
            'limite_uso' => 1,
            'usos_realizados' => 1,
            'ativo' => true,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'Maria Compradora',
                'email' => 'maria@example.com',
                'cpf_cnpj' => '987.654.321-00',
                'rg' => '22.333.444-5',
                'telefone' => '11977776666',
                'cep' => '01310-100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => [
                ['produto_id' => $this->produtoFisico->id, 'quantidade' => 1],
            ],
            'forma_pagamento' => 'cartao',
            'cupom_codigo' => 'ESGOTADO',
        ]);

        $response->assertStatus(422);
        // Estoque não pode ter sido debitado - o cupom inválido deve
        // abortar a transação inteira, não só deixar de aplicar o desconto.
        $this->assertSame(10, $this->produtoFisico->fresh()->estoque_atual);
    }

    public function test_busca_cliente_por_cpf_encontra_cadastro_existente(): void
    {
        Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'João Cliente',
            'cpf_cnpj' => '123.456.789-00',
            'email' => 'joao@example.com',
            'telefone' => '11999990000',
            'consentimento_lgpd' => true,
            'consentimento_lgpd_data' => now(),
            'consentimento_lgpd_versao' => 'v1',
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/clientes/buscar", [
            'cpf_cnpj' => '123.456.789-00',
        ]);

        $response->assertOk();
        $response->assertJson([
            'encontrado' => true,
            'nome' => 'João Cliente',
            'email' => 'joao@example.com',
            'telefone' => '11999990000',
        ]);
    }

    public function test_busca_cliente_por_cpf_inexistente_nao_encontra(): void
    {
        $response = $this->postJson("/api/loja/{$this->empresa->slug}/clientes/buscar", [
            'cpf_cnpj' => '000.000.000-00',
        ]);

        $response->assertOk();
        $response->assertJson(['encontrado' => false]);
    }

    public function test_recibo_publico_do_pedido_traz_itens_e_dados_da_empresa(): void
    {
        $this->empresa->update(['nome_fantasia' => 'Cervejaria Fantasia', 'logo_url' => 'https://exemplo.com/logo.png']);

        $reservaResponse = $this->postJson("/api/loja/{$this->empresa->slug}/reservas", [
            'agenda_visitacao_id' => $this->agenda->id,
            'quantidade' => 2,
        ]);
        $reservaId = $reservaResponse->json('reserva_id');

        $checkout = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'Ana Silva',
                'email' => 'ana@example.com',
                'cpf_cnpj' => '987.654.321-00',
                'rg' => '22.333.444-5',
                'telefone' => '11977776666',
                'cep' => '01310-100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => [['produto_id' => $this->produtoFisico->id, 'quantidade' => 1]],
            'reserva_id' => $reservaId,
            'forma_pagamento' => 'pix',
        ]);
        $vendaId = $checkout->json('id');

        $response = $this->getJson("/api/loja/{$this->empresa->slug}/pedidos/{$vendaId}");

        $response->assertOk();
        $response->assertJsonPath('cliente_primeiro_nome', 'Ana');
        $response->assertJsonPath('empresa.nome_fantasia', 'Cervejaria Fantasia');
        $response->assertJsonPath('empresa.logo_url', 'https://exemplo.com/logo.png');
        $this->assertCount(2, $response->json('itens'));
    }

    public function test_recibo_publico_de_outra_empresa_retorna_404(): void
    {
        $response = $this->getJson("/api/loja/{$this->empresa->slug}/pedidos/999999");

        $response->assertStatus(404);
    }

    private function dadosClienteCompletos(): array
    {
        return [
            'nome' => 'Maria Compradora',
            'email' => 'maria@example.com',
            'cpf_cnpj' => '987.654.321-00',
            'rg' => '22.333.444-5',
            'telefone' => '11977776666',
            'cep' => '01310-100',
            'logradouro' => 'Av. Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'municipio' => 'São Paulo',
            'uf' => 'SP',
            'codigo_ibge_municipio' => '3550308',
            'consentimento_lgpd' => true,
        ];
    }

    public function test_checkout_abaixo_da_quantidade_minima_e_recusado(): void
    {
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cerveja Pilsen 600ml',
            'tipo' => 'fisico',
            'loja_virtual' => true,
            'preco_venda' => 12.00,
            'quantidade_minima_venda' => 6,
        ]);
        $sabor1 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'Pilsen', 'estoque_atual' => 20,
        ]);
        $sabor2 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 20,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => $this->dadosClienteCompletos(),
            'itens' => [
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor1->id, 'quantidade' => 2],
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor2->id, 'quantidade' => 1],
            ],
            'forma_pagamento' => 'pix',
        ]);

        $response->assertStatus(422);
        $this->assertSame(20, $sabor1->fresh()->estoque_atual);
        $this->assertSame(20, $sabor2->fresh()->estoque_atual);
    }

    public function test_checkout_com_variacoes_misturadas_bate_a_quantidade_minima(): void
    {
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cerveja Pilsen 600ml',
            'tipo' => 'fisico',
            'loja_virtual' => true,
            'preco_venda' => 12.00,
            'quantidade_minima_venda' => 6,
        ]);
        $sabor1 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'Pilsen', 'estoque_atual' => 20,
        ]);
        $sabor2 = \App\Models\ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 20,
        ]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => $this->dadosClienteCompletos(),
            'itens' => [
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor1->id, 'quantidade' => 4],
                ['produto_id' => $cerveja->id, 'variacao_id' => $sabor2->id, 'quantidade' => 2],
            ],
            'forma_pagamento' => 'pix',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '72.00');
        $this->assertSame(16, $sabor1->fresh()->estoque_atual);
        $this->assertSame(18, $sabor2->fresh()->estoque_atual);
    }

    private function payloadProduto(array $extra = [], string $uf = 'SP'): array
    {
        return array_merge([
            'cliente' => [
                'nome' => 'Maria Compradora',
                'email' => 'maria@example.com',
                'cpf_cnpj' => '987.654.321-00',
                'rg' => '22.333.444-5',
                'telefone' => '11977776666',
                'cep' => '01310-100',
                'logradouro' => 'Av. Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo',
                'uf' => $uf,
                'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => [['produto_id' => $this->produtoFisico->id, 'quantidade' => 2]],
            'forma_pagamento' => 'cartao',
        ], $extra);
    }

    public function test_checkout_soma_frete_da_uf_e_guarda_endereco_de_entrega(): void
    {
        FreteRegra::create(['empresa_id' => $this->empresa->id, 'uf' => 'SP', 'valor' => 12.50, 'prazo_dias' => 3]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto());

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '48.50');
        $response->assertJsonPath('valor_frete', '12.50');
        $response->assertJsonPath('tipo_entrega', 'entrega');
        $this->assertSame('SP', Venda::latest('id')->first()->endereco_entrega['uf']);
    }

    public function test_frete_gratis_quando_subtotal_atinge_o_minimo(): void
    {
        FreteRegra::create(['empresa_id' => $this->empresa->id, 'uf' => 'SP', 'valor' => 12.50]);
        $this->empresa->update(['frete_gratis_acima' => 30]);

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto());

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '36.00');
        $response->assertJsonPath('valor_frete', '0.00');
    }

    public function test_uf_sem_regra_usa_demais_estados_e_sem_nenhuma_regra_recusa(): void
    {
        FreteRegra::create(['empresa_id' => $this->empresa->id, 'uf' => 'SP', 'valor' => 10]);

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([], 'RJ'))
            ->assertStatus(422);

        FreteRegra::create(['empresa_id' => $this->empresa->id, 'uf' => null, 'valor' => 25]);

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([], 'RJ'))
            ->assertCreated()
            ->assertJsonPath('valor_frete', '25.00');
    }

    public function test_retirada_nao_cobra_frete_nem_exige_endereco(): void
    {
        FreteRegra::create(['empresa_id' => $this->empresa->id, 'uf' => 'SP', 'valor' => 12.50]);
        $this->empresa->update(['permite_retirada' => true]);

        $payload = $this->payloadProduto(['tipo_entrega' => 'retirada']);
        $payload['cliente'] = array_diff_key($payload['cliente'], array_flip([
            'cep', 'logradouro', 'numero', 'bairro', 'municipio', 'uf', 'codigo_ibge_municipio',
        ]));

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $payload);

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '36.00');
        $response->assertJsonPath('tipo_entrega', 'retirada');
    }

    public function test_retirada_recusada_quando_a_loja_nao_oferece(): void
    {
        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto(['tipo_entrega' => 'retirada']))
            ->assertStatus(422);
    }

    public function test_endpoint_de_cotacao_de_frete(): void
    {
        FreteRegra::create(['empresa_id' => $this->empresa->id, 'uf' => 'SP', 'valor' => 12.50, 'prazo_dias' => 3]);
        $this->empresa->update(['permite_retirada' => true, 'frete_gratis_acima' => 100]);

        $this->getJson("/api/loja/{$this->empresa->slug}/frete?uf=SP&subtotal=40")
            ->assertOk()
            ->assertJsonPath('entrega.disponivel', true)
            ->assertJsonPath('entrega.valor', 12.5)
            ->assertJsonPath('entrega.prazo_dias', 3)
            ->assertJsonPath('permite_retirada', true);

        $this->getJson("/api/loja/{$this->empresa->slug}/frete?uf=SP&subtotal=150")
            ->assertJsonPath('entrega.gratis', true)
            ->assertJsonPath('entrega.valor', 0);

        $this->getJson("/api/loja/{$this->empresa->slug}/frete?uf=AM&subtotal=40")
            ->assertJsonPath('entrega.disponivel', false);
    }

    /**
     * Kit "Caneca + 3 cervejas": caneca fixa (estoque 5) e 3 cervejas à
     * escolha entre 3 sabores (estoque 10 cada), por R$ 80 fixo.
     *
     * @return array{kit: Produto, caneca: Produto, cerveja: Produto, sabores: array<int, ProdutoVariacao>}
     */
    private function criarKit(): array
    {
        $caneca = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Caneca', 'tipo' => 'fisico', 'preco_venda' => 30, 'estoque_atual' => 5,
        ]);
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Artesanal', 'tipo' => 'fisico', 'preco_venda' => 18,
            'quantidade_minima_venda' => 6,
        ]);
        $sabores = collect(['Pilsen', 'IPA', 'Weiss'])->map(fn ($nome) => ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => $nome, 'estoque_atual' => 10,
        ]))->all();
        $kit = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Kit Caneca + 3 Cervejas', 'tipo' => 'fisico',
            'preco_venda' => 80, 'eh_kit' => true,
        ]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $caneca->id, 'tipo' => 'fixo', 'quantidade' => 1]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $kit->id, 'produto_id' => $cerveja->id, 'tipo' => 'escolha', 'quantidade' => 3]);

        return compact('kit', 'caneca', 'cerveja', 'sabores');
    }

    public function test_catalogo_expoe_a_composicao_do_kit(): void
    {
        ['kit' => $kit] = $this->criarKit();

        $resposta = $this->getJson("/api/loja/{$this->empresa->slug}/produtos")->assertOk();
        $kitJson = collect($resposta->json())->firstWhere('id', $kit->id);

        $this->assertTrue($kitJson['eh_kit']);
        $this->assertTrue($kitJson['kit']['disponivel']);
        $this->assertSame('Caneca', $kitJson['kit']['fixos'][0]['nome']);
        $this->assertSame(3, $kitJson['kit']['escolhas'][0]['quantidade']);
        $this->assertCount(3, $kitJson['kit']['escolhas'][0]['variacoes']);
        $this->assertArrayNotHasKey('componentes', $kitJson);
    }

    public function test_checkout_de_kit_baixa_caneca_e_cervejas_escolhidas_e_guarda_a_composicao(): void
    {
        ['kit' => $kit, 'caneca' => $caneca, 'sabores' => [$pilsen, $ipa]] = $this->criarKit();

        $response = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
            'itens' => [[
                'produto_id' => $kit->id, 'quantidade' => 1,
                // pode repetir: 2 Pilsen + 1 IPA
                'escolhas' => [['variacao_id' => $pilsen->id, 'quantidade' => 2], ['variacao_id' => $ipa->id, 'quantidade' => 1]],
            ]],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('valor_total', '80.00');
        $this->assertSame(4, $caneca->fresh()->estoque_atual);
        $this->assertSame(8, $pilsen->fresh()->estoque_atual);
        $this->assertSame(9, $ipa->fresh()->estoque_atual);

        $composicao = $response->json('itens.0.composicao');
        $this->assertCount(3, $composicao);
        $this->assertSame('Pilsen', collect($composicao)->firstWhere('variacao_id', $pilsen->id)['tamanho']);
        $this->assertSame(2, collect($composicao)->firstWhere('variacao_id', $pilsen->id)['quantidade']);
    }

    public function test_kit_permite_repetir_a_mesma_cerveja_tres_vezes(): void
    {
        ['kit' => $kit, 'sabores' => [$pilsen]] = $this->criarKit();

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
            'itens' => [['produto_id' => $kit->id, 'quantidade' => 1, 'escolhas' => [['variacao_id' => $pilsen->id, 'quantidade' => 3]]]],
        ]))->assertCreated();

        $this->assertSame(7, $pilsen->fresh()->estoque_atual);
    }

    public function test_kit_exige_exatamente_a_quantidade_de_cervejas_e_nao_baixa_nada_se_recusar(): void
    {
        ['kit' => $kit, 'caneca' => $caneca, 'sabores' => [$pilsen]] = $this->criarKit();

        foreach ([[], [['variacao_id' => $pilsen->id, 'quantidade' => 2]], [['variacao_id' => $pilsen->id, 'quantidade' => 4]]] as $escolhas) {
            $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
                'itens' => [['produto_id' => $kit->id, 'quantidade' => 1, 'escolhas' => $escolhas]],
            ]))->assertStatus(422);
        }

        $this->assertSame(5, $caneca->fresh()->estoque_atual);
        $this->assertSame(10, $pilsen->fresh()->estoque_atual);
    }

    public function test_kit_recusa_variacao_de_outro_produto(): void
    {
        ['kit' => $kit] = $this->criarKit();
        $intrusa = ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produtoFisico->id, 'tamanho' => 'X', 'estoque_atual' => 5,
        ]);

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
            'itens' => [['produto_id' => $kit->id, 'quantidade' => 1, 'escolhas' => [['variacao_id' => $intrusa->id, 'quantidade' => 3]]]],
        ]))->assertStatus(422);
    }

    public function test_kit_sem_estoque_de_cerveja_retorna_409_e_devolve_a_caneca(): void
    {
        ['kit' => $kit, 'caneca' => $caneca, 'sabores' => [$pilsen]] = $this->criarKit();
        $pilsen->update(['estoque_atual' => 2]);

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
            'itens' => [['produto_id' => $kit->id, 'quantidade' => 1, 'escolhas' => [['variacao_id' => $pilsen->id, 'quantidade' => 3]]]],
        ]))->assertStatus(409);

        // a baixa da caneca (feita antes) é desfeita junto com a transação
        $this->assertSame(5, $caneca->fresh()->estoque_atual);
    }

    public function test_cerveja_avulsa_continua_exigindo_venda_minima_de_seis_misturando_sabores(): void
    {
        ['cerveja' => $cerveja, 'sabores' => [$pilsen, $ipa]] = $this->criarKit();

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
            'itens' => [
                ['produto_id' => $cerveja->id, 'variacao_id' => $pilsen->id, 'quantidade' => 2],
                ['produto_id' => $cerveja->id, 'variacao_id' => $ipa->id, 'quantidade' => 3],
            ],
        ]))->assertStatus(422);

        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto([
            'itens' => [
                ['produto_id' => $cerveja->id, 'variacao_id' => $pilsen->id, 'quantidade' => 2],
                ['produto_id' => $cerveja->id, 'variacao_id' => $ipa->id, 'quantidade' => 4],
            ],
        ]))->assertCreated()->assertJsonPath('valor_total', '108.00');
    }

    public function test_catalogo_publico_nao_expoe_campos_internos_do_produto(): void
    {
        $this->produtoFisico->update(['preco_custo' => 7.50, 'valor_atacado' => 12.00, 'codigo_barras' => '789', 'ncm' => '22030000']);

        $produto = $this->getJson("/api/loja/{$this->empresa->slug}/produtos")->assertOk()->json('0');

        foreach (['preco_custo', 'valor_atacado', 'ncm', 'cfop_padrao', 'fornecedor_id', 'empresa_id', 'codigo_barras', 'estoque_minimo'] as $campo) {
            $this->assertArrayNotHasKey($campo, $produto, "O catálogo público não pode expor {$campo}.");
        }
        // o que a loja realmente usa continua lá
        foreach (['id', 'nome', 'preco_venda', 'estoque_atual', 'imagem_url', 'quantidade_minima_venda', 'eh_kit', 'variacoes'] as $campo) {
            $this->assertArrayHasKey($campo, $produto);
        }
    }

    public function test_resposta_do_checkout_e_o_recibo_publico_nao_expoem_dados_internos_nem_cadastro_do_cliente(): void
    {
        $this->produtoFisico->update(['preco_custo' => 7.50]);

        $checkout = $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto())->assertCreated();
        $pedidoId = $checkout->json('id');

        $recibo = $this->getJson("/api/loja/{$this->empresa->slug}/pedidos/{$pedidoId}")->assertOk();

        foreach ([$checkout->json(), $recibo->json()] as $resposta) {
            $texto = json_encode($resposta);
            $this->assertStringNotContainsString('preco_custo', $texto);
            $this->assertStringNotContainsString('987.654.321-00', $texto, 'CPF do cliente não pode voltar na resposta.');
            $this->assertStringNotContainsString('maria@example.com', $texto, 'E-mail do cliente não pode voltar na resposta.');
            $this->assertArrayNotHasKey('cliente', $resposta);
            $this->assertSame('Chopp Artesanal 500ml', $resposta['itens'][0]['produto']['nome']);
        }
    }

    public function test_produto_desativado_some_da_loja_e_nao_pode_ser_comprado(): void
    {
        $this->produtoFisico->update(['ativo' => false]);

        $ids = collect($this->getJson("/api/loja/{$this->empresa->slug}/produtos")->assertOk()->json())->pluck('id')->all();
        $this->assertNotContains($this->produtoFisico->id, $ids);

        // quem já tinha o produto no carrinho recebe um aviso claro
        $this->postJson("/api/loja/{$this->empresa->slug}/checkout", $this->payloadProduto())
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'não está mais disponível'));

        $this->assertSame(10, $this->produtoFisico->fresh()->estoque_atual);
    }
}
