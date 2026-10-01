<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\User;
use App\Models\Venda;
use App\Services\Fiscal\NfePhpFiscalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NFePHP\Common\Validator;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * NFe (modelo 55) emitida direto na retaguarda: venda avulsa, remessa,
 * transferência, bonificação e NFe de pedido da loja virtual.
 */
class NfeAvulsaTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    private Produto $produto;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa NFe Avulsa',
            'cnpj' => '11.222.333/0001-81',
            'slug' => 'nfe-avulsa',
            'uf' => 'SP',
            'municipio' => 'São Paulo',
            'codigo_ibge_municipio' => '3550308',
            'cep' => '01310-100',
            'logradouro' => 'Av. Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'estoque_permite_negativo' => false,
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->usuario = User::create([
            'name' => 'Admin', 'email' => 'admin@nfe-avulsa.com', 'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);

        $this->actingAs($this->usuario);
        $this->asEmpresa($this->empresa->id);

        ConfigFiscal::create([
            'empresa_id' => $this->empresa->id, 'crt' => '1', 'inscricao_estadual' => '123456789012',
            'serie_nfe_atual' => '1', 'numero_nfe_atual' => 0, 'serie_nfce_atual' => '1', 'numero_nfce_atual' => 0,
            'ambiente_ativo' => 'homologacao',
        ]);

        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Pilsen 600ml', 'tipo' => 'fisico',
            'preco_venda' => 20.00, 'estoque_atual' => 10, 'ncm' => '22030000', 'cfop_padrao' => '5102',
        ]);

        $this->cliente = $this->criarCliente('SP');
    }

    private function criarCliente(string $uf): Cliente
    {
        return Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cliente PJ', 'cpf_cnpj' => '12345678000199',
            'inscricao_estadual' => '987654321', 'email' => 'pj@example.com',
            'uf' => $uf, 'municipio' => 'São Paulo', 'codigo_ibge_municipio' => '3550308', 'cep' => '01000-000',
            'logradouro' => 'Rua Teste', 'numero' => '100', 'bairro' => 'Centro', 'consentimento_lgpd' => true,
        ]);
    }

    private function url(string $caminho = ''): string
    {
        return "/fiscal/{$this->empresa->slug}/nfe{$caminho}";
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'venda',
            'cliente_id' => $this->cliente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ], $extra);
    }

    public function test_opcoes_informam_ambiente_regime_e_tipos(): void
    {
        $this->getJson($this->url('/opcoes'))
            ->assertOk()
            ->assertJsonPath('ambiente', 'homologacao')
            ->assertJsonPath('regime_suportado', true)
            ->assertJsonCount(4, 'tipos')
            ->assertJsonPath('tipos.1.valor', 'remessa');
    }

    public function test_venda_avulsa_emite_baixa_estoque_e_usa_cfop_de_venda(): void
    {
        $resposta = $this->postJson($this->url(), $this->payload());

        $resposta->assertCreated()
            ->assertJsonPath('modelo', 55)
            ->assertJsonPath('tipo_operacao', 'venda')
            ->assertJsonPath('status', 'autorizada')
            ->assertJsonPath('total', '40.00')
            ->assertJsonPath('itens.0.cfop', '5102')
            ->assertJsonPath('cliente.id', $this->cliente->id);

        $this->assertSame(8, $this->produto->fresh()->estoque_atual);
        $this->assertSame(1, ConfigFiscal::first()->numero_nfe_atual);
    }

    public function test_destinatario_de_outra_uf_troca_o_cfop_para_6xxx(): void
    {
        $fora = $this->criarCliente('RJ');

        $this->postJson($this->url(), $this->payload(['cliente_id' => $fora->id]))
            ->assertCreated()->assertJsonPath('itens.0.cfop', '6102');
    }

    public function test_frete_e_somado_ao_total_e_rateado_entre_os_itens_fechando_a_soma(): void
    {
        $outro = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Caneca', 'tipo' => 'fisico', 'preco_venda' => 30, 'estoque_atual' => 5,
        ]);

        $resposta = $this->postJson($this->url(), $this->payload([
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 1, 'valor_unitario' => 10],
                ['produto_id' => $outro->id, 'quantidade' => 1, 'valor_unitario' => 20],
                ['produto_id' => $outro->id, 'quantidade' => 1, 'valor_unitario' => 20],
            ],
            'frete' => 10,
            'modalidade_frete' => 0,
            'transportadora' => ['nome' => 'Transportes Rápidos', 'documento' => '11.222.333/0001-81', 'uf' => 'SP'],
        ]));

        $resposta->assertCreated()->assertJsonPath('total', '60.00')->assertJsonPath('frete', '10.00');

        $somaFrete = collect($resposta->json('itens'))->sum(fn ($i) => (float) $i['valor_frete']);
        $this->assertEqualsWithDelta(10.0, $somaFrete, 0.001);
    }

    public function test_remessa_nao_baixa_estoque_e_usa_cfop_5949_ou_o_informado(): void
    {
        $this->postJson($this->url(), $this->payload(['tipo' => 'remessa']))
            ->assertCreated()
            ->assertJsonPath('tipo_operacao', 'remessa')
            ->assertJsonPath('itens.0.cfop', '5949')
            ->assertJsonPath('tpag', '90');

        $this->assertSame(10, $this->produto->fresh()->estoque_atual);

        // CFOP escolhido pelo usuário; fora do estado vira 6xxx
        $fora = $this->criarCliente('MG');
        $this->postJson($this->url(), $this->payload(['tipo' => 'remessa', 'cfop' => '5915', 'cliente_id' => $fora->id]))
            ->assertCreated()->assertJsonPath('itens.0.cfop', '6915');
    }

    public function test_bonificacao_baixa_estoque_e_transferencia_usa_cfop_proprio(): void
    {
        $this->postJson($this->url(), $this->payload(['tipo' => 'bonificacao']))
            ->assertCreated()->assertJsonPath('itens.0.cfop', '5910')
            ->assertJsonPath('natureza_operacao', 'Remessa em bonificação, doação ou brinde');
        $this->assertSame(8, $this->produto->fresh()->estoque_atual);

        $this->postJson($this->url(), $this->payload(['tipo' => 'transferencia']))
            ->assertCreated()->assertJsonPath('itens.0.cfop', '5152');
        $this->assertSame(8, $this->produto->fresh()->estoque_atual);
    }

    public function test_variacao_vai_na_descricao_e_baixa_o_estoque_da_variacao(): void
    {
        $ipa = ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id, 'tamanho' => 'IPA', 'estoque_atual' => 6,
        ]);

        $this->postJson($this->url(), $this->payload())->assertStatus(422); // produto com variações exige a variação

        $this->postJson($this->url(), $this->payload([
            'itens' => [['produto_id' => $this->produto->id, 'variacao_id' => $ipa->id, 'quantidade' => 4]],
        ]))->assertCreated()->assertJsonPath('itens.0.descricao', 'Cerveja Pilsen 600ml (IPA)');

        $this->assertSame(2, $ipa->fresh()->estoque_atual);
    }

    public function test_validacoes_recusam_dados_invalidos_sem_consumir_numeracao(): void
    {
        // frete cobrado sem dizer quem paga o transporte
        $this->postJson($this->url(), $this->payload(['frete' => 15, 'modalidade_frete' => 9]))->assertStatus(422);
        // estoque insuficiente
        $this->postJson($this->url(), $this->payload(['itens' => [['produto_id' => $this->produto->id, 'quantidade' => 99]]]))->assertStatus(422);
        // CFOP e tipo inválidos
        $this->postJson($this->url(), $this->payload(['cfop' => '1202']))->assertStatus(422);
        $this->postJson($this->url(), $this->payload(['tipo' => 'outra']))->assertStatus(422);
        // kit não vai por aqui
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit', 'tipo' => 'fisico', 'preco_venda' => 80, 'eh_kit' => true]);
        $this->postJson($this->url(), $this->payload(['itens' => [['produto_id' => $kit->id, 'quantidade' => 1]]]))->assertStatus(422);

        $this->assertSame(0, ConfigFiscal::first()->numero_nfe_atual);
        $this->assertSame(0, DocumentoFiscal::count());
    }

    public function test_regime_normal_e_recusado_por_enquanto(): void
    {
        ConfigFiscal::first()->update(['crt' => '3']);

        $this->getJson($this->url('/opcoes'))->assertJsonPath('regime_suportado', false);
        $this->postJson($this->url(), $this->payload())->assertStatus(422);
    }

    private function pedidoDaLoja(array $extra = []): Venda
    {
        $venda = Venda::create(array_merge([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id, 'canal' => 'site',
            'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago', 'valor_total' => 52, 'data_venda' => now(),
            'tipo_entrega' => 'entrega', 'valor_frete' => 12, 'status_envio' => 'a_separar',
            'endereco_entrega' => [
                'cep' => '20000-000', 'logradouro' => 'Rua da Entrega', 'numero' => '55', 'bairro' => 'Lapa',
                'municipio' => 'Rio de Janeiro', 'uf' => 'RJ', 'codigo_ibge_municipio' => '3304557',
            ],
        ], $extra));

        $venda->itens()->create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id,
            'quantidade' => 2, 'valor_unitario' => 20, 'valor_total' => 40,
        ]);

        return $venda;
    }

    public function test_nfe_do_pedido_da_loja_inclui_frete_usa_endereco_de_entrega_e_marca_a_venda(): void
    {
        $venda = $this->pedidoDaLoja();

        $this->postJson("/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/nfe-pedido-loja")
            ->assertCreated()
            ->assertJsonPath('total', '52.00')
            ->assertJsonPath('frete', '12.00')
            ->assertJsonPath('modalidade_frete', 0)
            ->assertJsonPath('indicador_presenca', 2)
            ->assertJsonPath('itens.0.cfop', '6102'); // entrega no RJ, emitente em SP

        $this->assertSame('fiscal', $venda->fresh()->tipo_doc);
        // estoque já havia sido baixado no checkout - a NFe não mexe nele
        $this->assertSame(10, $this->produto->fresh()->estoque_atual);

        // segunda emissão do mesmo pedido é recusada
        $this->postJson("/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/nfe-pedido-loja")->assertStatus(422);
    }

    public function test_nfe_do_pedido_exige_pedido_pago_e_na_retirada_nao_tem_frete(): void
    {
        $naoPago = $this->pedidoDaLoja(['status_pagamento' => 'pendente']);
        $this->postJson("/fiscal/{$this->empresa->slug}/vendas/{$naoPago->id}/nfe-pedido-loja")->assertStatus(422);

        $retirada = $this->pedidoDaLoja(['tipo_entrega' => 'retirada', 'valor_frete' => 0, 'endereco_entrega' => null, 'valor_total' => 40]);
        $this->postJson("/fiscal/{$this->empresa->slug}/vendas/{$retirada->id}/nfe-pedido-loja")
            ->assertCreated()->assertJsonPath('total', '40.00')->assertJsonPath('modalidade_frete', 1)
            ->assertJsonPath('itens.0.cfop', '5102');
    }

    public function test_pedidos_da_loja_mostram_a_nfe_emitida(): void
    {
        $venda = $this->pedidoDaLoja();
        $this->postJson("/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/nfe-pedido-loja")->assertCreated();

        $this->getJson("/dashboard/{$this->empresa->slug}/pedidos-loja")
            ->assertOk()->assertJsonPath('0.nfe.status', 'autorizada')->assertJsonPath('0.nfe.numero', 1);
    }

    public function test_danfe_de_nfe_avulsa_abre_sem_venda(): void
    {
        $id = $this->postJson($this->url(), $this->payload(['informacoes_adicionais' => 'Pedido de compra 123']))->json('id');

        $this->get("/fiscal/{$this->empresa->slug}/documentos/{$id}/reimprimir")
            ->assertOk()->assertSee('Cliente PJ')->assertSee('Pedido de compra 123')->assertSee('HOMOLOGAÇÃO');
    }

    /**
     * Monta o XML real da NFe (com frete, transportadora e informações
     * complementares) e valida contra o XSD oficial. O XML ainda não está
     * assinado, então o único erro aceito é o da ausência de ds:Signature.
     */
    public function test_xml_da_nfe_com_frete_e_transportadora_e_valido_no_schema(): void
    {
        $documento = new DocumentoFiscal([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id, 'tipo_operacao' => 'venda',
            'modelo' => 55, 'serie' => '1', 'numero' => 1, 'ambiente' => 'homologacao',
            'natureza_operacao' => 'Venda de mercadoria', 'valor_produtos' => 40, 'frete' => 10, 'total' => 50,
            'modalidade_frete' => 0, 'indicador_presenca' => 1, 'tpag' => '17',
            'transportadora' => ['nome' => 'Transportes Rápidos', 'documento' => '11222333000181', 'ie' => '111222333', 'municipio' => 'Campinas', 'uf' => 'SP'],
            'informacoes_adicionais' => 'Pedido de compra 123',
        ]);
        $documento->setRelation('cliente', $this->cliente);

        $item = new DocumentoFiscalItem([
            'produto_id' => $this->produto->id, 'descricao' => 'Cerveja Pilsen 600ml', 'ncm' => '22030000', 'cfop' => '5102',
            'quantidade' => 2, 'valor_unitario' => 20, 'valor_total' => 40, 'valor_frete' => 10,
        ]);
        $item->setRelation('produto', $this->produto);

        $metodo = new \ReflectionMethod(NfePhpFiscalGateway::class, 'montarXmlNfe');
        $xml = $metodo->invoke(new NfePhpFiscalGateway(), $documento, collect([$item]), $this->empresa, ConfigFiscal::first());

        $this->assertStringContainsString('<vFrete>10.00</vFrete>', $xml);
        $this->assertStringContainsString('<vNF>50.00</vNF>', $xml);
        $this->assertStringContainsString('<modFrete>0</modFrete>', $xml);
        $this->assertStringContainsString('Transportes Rápidos', $xml);
        $this->assertStringContainsString('<infCpl>Pedido de compra 123</infCpl>', $xml);
        $this->assertStringContainsString('<tPag>17</tPag>', $xml);

        $xsd = base_path('vendor/nfephp-org/sped-nfe/schemes/PL_010_V1.30/nfe_v4.00.xsd');

        try {
            Validator::isValid($xml, $xsd);
        } catch (\Throwable $e) {
            $outros = array_filter(
                preg_split('/\R/', $e->getMessage()),
                fn ($linha) => trim($linha) !== '' && ! str_contains($linha, 'Signature')
                    && ! str_contains($linha, 'XML não foi validado') && ! str_contains($linha, 'xmldsig')
            );
            $this->assertSame([], array_values($outros), 'XML da NFe inválido no schema: '.$e->getMessage());
        }
    }
}
