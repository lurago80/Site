<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Módulo de entrada de notas / compras: registro manual ou importação de
 * XML de NFe, conferência/vínculo de itens a produtos, confirmação (baixa
 * reversa de estoque + lançamento automático em contas a pagar) e
 * cancelamento (estorno de estoque se já confirmada).
 */
class ComprasTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Fornecedor $fornecedor;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Compras Teste', 'cnpj' => '66.666.666/0001-66',
            'slug' => 'compras-teste', 'plano_id' => $plano->id, 'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Teste', 'email' => 'admin@compras-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);

        $this->asEmpresa($this->empresa->id);

        $this->fornecedor = Fornecedor::create([
            'empresa_id' => $this->empresa->id, 'razao_social' => 'Fornecedor Teste', 'cnpj' => '11.111.111/0001-11',
        ]);

        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Produto Teste', 'tipo' => 'fisico',
            'preco_venda' => 20, 'preco_custo' => 10, 'estoque_atual' => 5, 'ativo' => true,
        ]);
    }

    public function test_admin_registra_entrada_manual_com_itens(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/compras", [
            'fornecedor_id' => $this->fornecedor->id,
            'numero_nota' => '1001',
            'valor_frete' => 10,
            'valor_desconto' => 5,
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 3, 'valor_unitario' => 12],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(36.0, $response->json('valor_produtos'));
        $this->assertEquals(41.0, $response->json('valor_total')); // 36 + 10 - 5
        $this->assertEquals('pendente', $response->json('status'));
    }

    public function test_confirmar_compra_manual_incrementa_estoque_e_gera_conta_a_pagar(): void
    {
        $compra = $this->registrarCompraManual();

        $response = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/compras/{$compra->id}/confirmar");

        $response->assertOk()->assertJsonPath('status', 'confirmada');

        $this->assertEquals(8.0, $this->produto->fresh()->estoque_atual); // 5 + 3
        $this->assertDatabaseHas('contas_pagar', [
            'empresa_id' => $this->empresa->id,
            'fornecedor_id' => $this->fornecedor->id,
            'valor' => $compra->fresh()->valor_total,
            'status' => 'em_aberto',
        ]);
    }

    public function test_nao_confirma_compra_ja_confirmada(): void
    {
        $compra = $this->registrarCompraManual();
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/compras/{$compra->id}/confirmar");

        $response = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/compras/{$compra->id}/confirmar");

        $response->assertStatus(422);
    }

    public function test_cancelar_compra_confirmada_estorna_estoque(): void
    {
        $compra = $this->registrarCompraManual();
        $this->actingAs($this->admin)->putJson("/dashboard/{$this->empresa->slug}/compras/{$compra->id}/confirmar");

        $response = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/compras/{$compra->id}/cancelar");

        $response->assertOk()->assertJsonPath('status', 'cancelada');
        $this->assertEquals(5.0, $this->produto->fresh()->estoque_atual); // volta ao valor original
    }

    public function test_importar_xml_recusa_nota_com_fornecedor_nao_cadastrado(): void
    {
        $xml = $this->xmlNfeFalso(cnpjEmitente: '99.999.999/0001-99');

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/{$this->empresa->slug}/compras/importar-xml",
            ['xml' => \Illuminate\Http\UploadedFile::fake()->createWithContent('nota.xml', $xml)],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422);
    }

    public function test_importar_xml_cria_compra_pendente_com_itens_sem_produto_vinculado(): void
    {
        $xml = $this->xmlNfeFalso(cnpjEmitente: '11111111000111');

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/{$this->empresa->slug}/compras/importar-xml",
            ['xml' => \Illuminate\Http\UploadedFile::fake()->createWithContent('nota.xml', $xml)],
        );

        $response->assertCreated();
        $this->assertEquals('xml', $response->json('tipo_entrada'));

        $compra = Compra::find($response->json('id'));
        $this->assertNull($compra->itens->first()->produto_id);
    }

    public function test_nao_confirma_compra_com_item_sem_produto_vinculado(): void
    {
        $xml = $this->xmlNfeFalso(cnpjEmitente: '11111111000111');
        $importacao = $this->actingAs($this->admin)->post(
            "/dashboard/{$this->empresa->slug}/compras/importar-xml",
            ['xml' => \Illuminate\Http\UploadedFile::fake()->createWithContent('nota.xml', $xml)],
        );

        $response = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/compras/{$importacao->json('id')}/confirmar");

        $response->assertStatus(422);
    }

    public function test_vincular_item_a_novo_produto_e_depois_confirmar(): void
    {
        $xml = $this->xmlNfeFalso(cnpjEmitente: '11111111000111');
        $importacao = $this->actingAs($this->admin)->post(
            "/dashboard/{$this->empresa->slug}/compras/importar-xml",
            ['xml' => \Illuminate\Http\UploadedFile::fake()->createWithContent('nota.xml', $xml)],
        );

        $compraId = $importacao->json('id');
        $itemId = $importacao->json('itens')[0]['id'];

        $vinculo = $this->actingAs($this->admin)->putJson(
            "/dashboard/{$this->empresa->slug}/compras/{$compraId}/itens/{$itemId}/vincular",
            ['novo_produto' => ['nome' => 'Produto Importado', 'preco_venda' => 15]],
        );
        $vinculo->assertOk();
        $this->assertNotNull($vinculo->json('produto_id'));

        $confirmacao = $this->actingAs($this->admin)
            ->putJson("/dashboard/{$this->empresa->slug}/compras/{$compraId}/confirmar");
        $confirmacao->assertOk()->assertJsonPath('status', 'confirmada');
    }

    private function registrarCompraManual(): Compra
    {
        $response = $this->actingAs($this->admin)->postJson("/dashboard/{$this->empresa->slug}/compras", [
            'fornecedor_id' => $this->fornecedor->id,
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 3, 'valor_unitario' => 12],
            ],
        ]);

        return Compra::find($response->json('id'));
    }

    private function xmlNfeFalso(string $cnpjEmitente): string
    {
        $chave = str_pad('', 44, '1');

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <nfeProc>
            <NFe>
                <infNFe Id="NFe{$chave}">
                    <ide>
                        <nNF>555</nNF>
                        <serie>1</serie>
                        <dhEmi>2026-07-28T10:00:00-03:00</dhEmi>
                    </ide>
                    <emit>
                        <CNPJ>{$cnpjEmitente}</CNPJ>
                    </emit>
                    <det>
                        <prod>
                            <cProd>ABC123</cProd>
                            <xProd>Produto do fornecedor</xProd>
                            <NCM>12345678</NCM>
                            <CFOP>5102</CFOP>
                            <cEAN>SEM GTIN</cEAN>
                            <qCom>10</qCom>
                            <vUnCom>7.5000</vUnCom>
                            <vProd>75.00</vProd>
                        </prod>
                    </det>
                    <total>
                        <ICMSTot>
                            <vNF>75.00</vNF>
                        </ICMSTot>
                    </total>
                </infNFe>
            </NFe>
        </nfeProc>
        XML;
    }
}
