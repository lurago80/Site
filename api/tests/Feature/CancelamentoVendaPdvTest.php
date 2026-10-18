<?php

namespace Tests\Feature;

use App\Models\Atendente;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Cancelamento de venda no PDV: só administrador, devolve estoque, estorna o
 * dinheiro no caixa, cancela a NFC-e e deixa tudo registrado.
 */
class CancelamentoVendaPdvTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private User $caixa;

    private Produto $produto;

    private Atendente $atendente;

    private FormaPagamento $dinheiro;

    private FormaPagamento $cartao;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Cancelamento', 'cnpj' => '11.111.111/0001-11', 'slug' => 'cancela-teste',
            'plano_id' => $plano->id, 'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@cancela.com', 'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);
        $this->caixa = User::create([
            'name' => 'Operador', 'email' => 'caixa@cancela.com', 'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id, 'perfil' => 'caixa',
        ]);

        $this->actingAs($this->admin);
        $this->asEmpresa($this->empresa->id);

        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Chopp 500ml', 'tipo' => 'fisico',
            'preco_venda' => 18.00, 'estoque_atual' => 10,
        ]);
        $this->atendente = Atendente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Atendente', 'ativo' => true]);
        $this->dinheiro = FormaPagamento::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Dinheiro', 'tipo' => 'dinheiro', 'codigo_tpag' => '01', 'ativo' => true,
        ]);
        $this->cartao = FormaPagamento::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Cartão', 'tipo' => 'cartao_credito', 'codigo_tpag' => '03', 'ativo' => true,
        ]);

        $this->base = "/pdv/{$this->empresa->slug}";
    }

    private function vender(FormaPagamento $forma, int $quantidade = 2, string $tipoDoc = 'nao_fiscal'): int
    {
        $resposta = $this->postJson("{$this->base}/vendas", [
            'tipo_doc' => $tipoDoc,
            'atendente_id' => $this->atendente->id,
            'pagamentos' => [['forma_pagamento_id' => $forma->id]],
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => $quantidade]],
        ]);
        $resposta->assertCreated();

        return $resposta->json('id');
    }

    private function cancelar(int $vendaId, string $motivo = 'Cliente desistiu da compra')
    {
        return $this->postJson("{$this->base}/vendas/{$vendaId}/cancelar", ['motivo' => $motivo]);
    }

    public function test_cancela_venda_devolve_estoque_e_registra_quem_cancelou(): void
    {
        $id = $this->vender($this->cartao, 3);
        $this->assertSame(7, $this->produto->fresh()->estoque_atual);

        $this->cancelar($id, 'Cliente desistiu da compra')
            ->assertOk()
            ->assertJsonPath('status_pagamento', 'cancelado')
            ->assertJsonPath('motivo_cancelamento', 'Cliente desistiu da compra')
            ->assertJsonPath('cancelada_por.name', 'Admin');

        $this->assertSame(10, $this->produto->fresh()->estoque_atual);

        $venda = Venda::find($id);
        $this->assertSame($this->admin->id, $venda->cancelada_por_usuario_id);
        $this->assertNotNull($venda->cancelada_em);
    }

    public function test_cancelamento_fica_no_log_de_auditoria(): void
    {
        $id = $this->vender($this->cartao);

        $this->cancelar($id)->assertOk();

        $log = DB::table('logs')
            ->where('tabela_afetada', 'vendas')->where('registro_id', $id)->where('acao', 'update')
            ->orderByDesc('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->usuario_id);
        $this->assertStringContainsString('cancelado', $log->dados_novos);
    }

    public function test_somente_administrador_pode_cancelar_e_listar(): void
    {
        $id = $this->vender($this->cartao);

        $this->actingAs($this->caixa);
        $this->cancelar($id)->assertForbidden();
        $this->getJson("{$this->base}/vendas-do-dia")->assertForbidden();

        $this->assertSame('pago', Venda::find($id)->status_pagamento);
        $this->assertSame(8, $this->produto->fresh()->estoque_atual);
    }

    public function test_botao_de_cancelar_so_aparece_para_administrador(): void
    {
        $this->get("{$this->base}/caixa")->assertOk()->assertSee('Vendas do dia / Cancelar');

        $this->actingAs($this->caixa);
        $this->get("{$this->base}/caixa")->assertOk()->assertDontSee('Vendas do dia / Cancelar');
    }

    public function test_motivo_curto_e_recusado_sem_alterar_nada(): void
    {
        $id = $this->vender($this->cartao);

        $this->cancelar($id, 'curto')->assertStatus(422);

        $this->assertSame('pago', Venda::find($id)->status_pagamento);
        $this->assertSame(8, $this->produto->fresh()->estoque_atual);
    }

    public function test_nao_cancela_duas_vezes(): void
    {
        $id = $this->vender($this->cartao);

        $this->cancelar($id)->assertOk();
        $this->cancelar($id)->assertStatus(422)->assertJsonPath('message', 'Esta venda já está cancelada.');

        $this->assertSame(10, $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_em_dinheiro_estorna_o_valor_no_caixa(): void
    {
        $this->postJson("{$this->base}/caixa-abrir", ['valor' => 100])->assertCreated();
        $id = $this->vender($this->dinheiro, 2); // R$ 36,00

        $this->assertEquals(136.0, $this->getJson("{$this->base}/caixa-status")->json('saldo'));

        $this->cancelar($id)->assertOk();

        $this->assertEquals(100.0, $this->getJson("{$this->base}/caixa-status")->json('saldo'));
        $this->assertDatabaseHas('caixas', ['tipo' => 'venda', 'observacao' => "Estorno da venda #{$id} (cancelada)"]);
    }

    public function test_venda_em_dinheiro_com_caixa_fechado_nao_pode_ser_cancelada(): void
    {
        $this->postJson("{$this->base}/caixa-abrir", ['valor' => 100])->assertCreated();
        $id = $this->vender($this->dinheiro, 1);
        $this->postJson("{$this->base}/caixa-fechar", ['valor' => 118])->assertCreated();

        $this->cancelar($id)->assertStatus(422);

        $this->assertSame('pago', Venda::find($id)->status_pagamento);
        $this->assertSame(9, $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_cancelada_sai_do_resumo_do_caixa(): void
    {
        $this->postJson("{$this->base}/caixa-abrir", ['valor' => 100])->assertCreated();
        $id = $this->vender($this->dinheiro, 1);
        $this->vender($this->cartao, 1);

        $this->cancelar($id)->assertOk();

        $resumo = app(\App\Services\Pdv\CaixaService::class)->resumoTurno($this->empresa->id);
        $this->assertSame(1, $resumo['qtd_vendas']);
        $this->assertEquals(0.0, $resumo['vendas_dinheiro']);
    }

    public function test_cancela_a_nfce_autorizada_junto_com_a_venda(): void
    {
        ConfigFiscal::create([
            'empresa_id' => $this->empresa->id, 'crt' => '1', 'serie_nfce_atual' => '1',
            'numero_nfce_atual' => 0, 'ambiente_ativo' => 'homologacao',
        ]);

        $id = $this->vender($this->cartao, 1, 'fiscal');
        $nota = DocumentoFiscal::where('venda_id', $id)->firstOrFail();
        $this->assertSame('autorizada', $nota->status);

        $this->cancelar($id, 'Erro de digitação do operador')->assertOk();

        $this->assertSame('cancelada', $nota->fresh()->status);
        $this->assertSame('cancelado', Venda::find($id)->status_pagamento);
        $this->assertSame(10, $this->produto->fresh()->estoque_atual);
    }

    public function test_lista_vendas_do_dia_com_dados_do_cancelamento(): void
    {
        $a = $this->vender($this->cartao, 1);
        $b = $this->vender($this->cartao, 1);
        $this->cancelar($b, 'Cliente desistiu da compra')->assertOk();

        $lista = collect($this->getJson("{$this->base}/vendas-do-dia")->assertOk()->json());

        $this->assertCount(2, $lista);
        $this->assertFalse($lista->firstWhere('id', $a)['cancelada']);
        $cancelada = $lista->firstWhere('id', $b);
        $this->assertTrue($cancelada['cancelada']);
        $this->assertSame('Admin', $cancelada['cancelada_por']);
        $this->assertSame('Cliente desistiu da compra', $cancelada['motivo_cancelamento']);
    }

    public function test_parametro_libera_cancelamento_para_o_perfil_caixa(): void
    {
        $id = $this->vender($this->cartao);

        // Parâmetro desligado: caixa não cancela. Ligado: cancela e o botão aparece.
        $this->empresa->update(['pdv_caixa_pode_cancelar' => true]);
        $this->actingAs($this->caixa->fresh());
        $this->get("{$this->base}/caixa")->assertOk()->assertSee('Vendas do dia / Cancelar');
        $this->getJson("{$this->base}/vendas-do-dia")->assertOk();
        $this->cancelar($id, 'Cliente desistiu da compra')->assertOk()->assertJsonPath('cancelada_por.name', 'Operador');

        $this->empresa->update(['pdv_caixa_pode_cancelar' => false]);
        $this->actingAs($this->caixa->fresh()); // relação da empresa em cache no usuário do teste
        $this->get("{$this->base}/caixa")->assertOk()->assertDontSee('Vendas do dia / Cancelar');
        $this->cancelar($id)->assertForbidden();
    }

    public function test_relatorio_de_cancelamentos_mostra_quem_quando_e_o_motivo(): void
    {
        $cancelada = $this->vender($this->cartao, 2);
        $this->vender($this->cartao, 1); // não cancelada: fora do relatório
        $this->cancelar($cancelada, 'Cliente desistiu da compra')->assertOk();

        $relatorio = $this->getJson("/dashboard/{$this->empresa->slug}/cancelamentos-relatorio")->assertOk();
        $relatorio->assertJsonCount(1, 'linhas');
        $relatorio->assertJsonPath('linhas.0.id', $cancelada);
        $relatorio->assertJsonPath('linhas.0.cancelado_por', 'Admin');
        $relatorio->assertJsonPath('linhas.0.motivo', 'Cliente desistiu da compra');
        $relatorio->assertJsonPath('linhas.0.valor_total', 36);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $relatorio->json('linhas.0.data_cancelamento'));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $relatorio->json('linhas.0.hora_cancelamento'));
    }

    public function test_gerente_cancela_vendas_sempre_e_so_faz_as_rotinas_do_dia_a_dia(): void
    {
        $gerente = User::create([
            'name' => 'Gerente', 'email' => 'gerente@cancela.com', 'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id, 'perfil' => 'gerente',
        ]);
        $this->actingAs($gerente);

        // PDV: botão e cancelamento liberados, sem depender do parâmetro do Caixa.
        $this->get("{$this->base}/caixa")->assertOk()->assertSee('Vendas do dia / Cancelar');
        $id = $this->vender($this->cartao);
        $this->cancelar($id, 'Cliente desistiu da compra')->assertOk()->assertJsonPath('cancelada_por.name', 'Gerente');

        // Painel: acessa relatórios e rotinas do dia a dia...
        $dash = "/dashboard/{$this->empresa->slug}";
        $this->getJson("$dash/cancelamentos-relatorio")->assertOk()->assertJsonPath('linhas.0.cancelado_por', 'Gerente');
        $this->postJson("$dash/atendentes", ['nome' => 'Novo atendente'])->assertSuccessful();

        // ...mas não gerencia usuários nem parâmetros da empresa.
        $this->postJson("$dash/usuarios", [
            'name' => 'Outro', 'email' => 'outro@cancela.com', 'password' => 'senha-teste-123', 'perfil' => 'admin',
        ])->assertForbidden();
        $this->putJson("$dash/config-operacional", [
            'estoque_permite_negativo' => true, 'pdv_impressao_direta' => false, 'pdv_caixa_pode_cancelar' => true,
        ])->assertForbidden();
    }
}
