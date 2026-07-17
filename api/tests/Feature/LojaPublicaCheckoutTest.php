<?php

namespace Tests\Feature;

use App\Models\AgendaVisitacao;
use App\Models\Empresa;
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
        $response = $this->getJson("/api/loja/{$this->empresa->slug}/agenda");

        $response->assertOk()->assertJsonCount(1);
        $this->assertSame(3, $response->json('0.vagas_disponiveis'));
    }

    public function test_slug_inexistente_retorna_404(): void
    {
        $this->getJson('/api/loja/loja-que-nao-existe/produtos')->assertNotFound();
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
                'consentimento_lgpd' => true,
            ],
            'reserva_id' => $reservaId,
            'forma_pagamento' => 'pix',
        ]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('valor_total', '120.00');
        $checkoutResponse->assertJsonPath('status_pagamento', 'pago');
        $checkoutResponse->assertJsonPath('cliente.email', 'joao@example.com');

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
}
