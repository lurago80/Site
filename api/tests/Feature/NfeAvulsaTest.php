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
use App\Services\Fiscal\Dto\ResultadoEmissaoFiscal;
use App\Services\Fiscal\FiscalGatewayInterface;
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

    public function test_regime_fora_do_simples_e_do_normal_e_recusado(): void
    {
        ConfigFiscal::first()->update(['crt' => '4']);

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

    /**
     * Troca o gateway simulado por um que monta o XML de verdade (calculando os
     * impostos) mas não fala com a SEFAZ: devolve "autorizada" na hora.
     */
    private function usarGeradorDeXmlReal(): void
    {
        $this->app->instance(FiscalGatewayInterface::class, new class extends NfePhpFiscalGateway {
            public function emitir($documento, $itens, $empresa, $configFiscal, $certificado): ResultadoEmissaoFiscal
            {
                $montador = (int) $documento->modelo === 65 ? 'montarXmlNfce' : 'montarXmlNfe';
                $xml = (new \ReflectionMethod(NfePhpFiscalGateway::class, $montador))
                    ->invoke($this, $documento, $itens, $empresa, $configFiscal);

                return new ResultadoEmissaoFiscal('autorizada', str_repeat('1', 44), 'PROTOCOLO-TESTE', $xml);
            }
        });
    }

    public function test_config_fiscal_guarda_a_exclusao_do_icms_da_base_de_pis_cofins(): void
    {
        $url = "/dashboard/{$this->empresa->slug}/config-fiscal";

        $this->assertTrue((bool) ConfigFiscal::first()->pis_cofins_exclui_icms); // padrão ligado

        $this->putJson($url, ['ambiente_ativo' => 'homologacao', 'crt' => '3', 'pis_cofins_exclui_icms' => false])->assertOk();

        $this->assertFalse((bool) ConfigFiscal::first()->fresh()->pis_cofins_exclui_icms);
        $this->getJson($url)->assertJsonPath('config_fiscal.pis_cofins_exclui_icms', false);
    }

    private function ativarRegimeNormal(): void
    {
        $this->usarGeradorDeXmlReal();
        ConfigFiscal::first()->update(['crt' => '3']);

        $this->produto->update([
            'cst_origem' => '0', 'cst_icms' => '00', 'aliquota_icms' => 18,
            'cst_pis' => '01', 'aliquota_pis' => 0.65, 'cst_cofins' => '01', 'aliquota_cofins' => 3,
            'cst_ipi' => '50', 'aliquota_ipi' => 10, 'codigo_enquadramento_ipi' => '999',
        ]);
    }

    public function test_regime_normal_calcula_e_grava_os_impostos_e_o_ipi_soma_ao_total(): void
    {
        $this->ativarRegimeNormal();

        // cliente contribuinte (tem IE): IPI fica fora da base do ICMS
        $resposta = $this->postJson($this->url(), $this->payload());

        $resposta->assertCreated()
            ->assertJsonPath('status', 'autorizada')
            ->assertJsonPath('valor_icms', '7.20')      // 18% de 40,00
            ->assertJsonPath('total', '44.00')          // 40,00 + IPI 4,00
            ->assertJsonPath('itens.0.cst_csosn', '00')
            ->assertJsonPath('itens.0.base_calculo_icms', '40.00')
            ->assertJsonPath('itens.0.valor_icms', '7.20');
    }

    public function test_regime_normal_recusa_produto_sem_cadastro_fiscal_sem_consumir_numeracao(): void
    {
        $this->usarGeradorDeXmlReal();
        ConfigFiscal::first()->update(['crt' => '3']); // produto sem CST de ICMS/PIS/COFINS

        $this->postJson($this->url(), $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produto "Cerveja Pilsen 600ml" sem CST de ICMS cadastrado (obrigatório no regime normal).');

        $this->assertSame(0, ConfigFiscal::first()->numero_nfe_atual);
    }

    public function test_regime_normal_recusa_st_com_mensagem_clara(): void
    {
        $this->ativarRegimeNormal();
        // CST 60 (ST já cobrada) é suportado; o 10 (ST na própria operação) ainda não.
        $this->produto->update(['cst_icms' => '10']);

        $mensagem = $this->postJson($this->url(), $this->payload())->assertStatus(422)->json('message');

        $this->assertStringContainsString('CST de ICMS 10', $mensagem);
        $this->assertStringContainsString('Substituição tributária', $mensagem);
    }

    public function test_xml_do_regime_normal_traz_icms_ipi_pis_cofins_e_e_valido_no_schema(): void
    {
        $this->ativarRegimeNormal();

        $consumidor = Cliente::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Maria Consumidora', 'cpf_cnpj' => '12345678909',
            'uf' => 'SP', 'municipio' => 'São Paulo', 'codigo_ibge_municipio' => '3550308', 'cep' => '01000-000',
            'logradouro' => 'Rua Teste', 'numero' => '10', 'bairro' => 'Centro', 'consentimento_lgpd' => true,
        ]);

        $documento = new DocumentoFiscal([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $consumidor->id, 'tipo_operacao' => 'venda',
            'modelo' => 55, 'serie' => '1', 'numero' => 2, 'ambiente' => 'homologacao',
            'natureza_operacao' => 'Venda de mercadoria', 'valor_produtos' => 100, 'frete' => 10, 'total' => 110,
            'modalidade_frete' => 0, 'indicador_presenca' => 2, 'tpag' => '17',
        ]);
        $documento->setRelation('cliente', $consumidor);

        $item = new DocumentoFiscalItem([
            'produto_id' => $this->produto->id, 'descricao' => 'Cerveja Pilsen 600ml', 'ncm' => '22030000', 'cfop' => '5102',
            'quantidade' => 5, 'valor_unitario' => 20, 'valor_total' => 100, 'valor_frete' => 10,
        ]);
        $item->setRelation('produto', $this->produto->fresh());

        $xml = (new \ReflectionMethod(NfePhpFiscalGateway::class, 'montarXmlNfe'))
            ->invoke(new NfePhpFiscalGateway(), $documento, collect([$item]), $this->empresa, ConfigFiscal::first());

        // consumidor final: IPI (11,00) entra na base do ICMS => 100 + 10 + 11 = 121,00; ICMS 18% = 21,78
        $this->assertStringContainsString('<ICMS00>', $xml);
        $this->assertStringContainsString('<vBC>121.00</vBC>', $xml);
        $this->assertStringContainsString('<vICMS>21.78</vICMS>', $xml);
        $this->assertStringContainsString('<IPITrib>', $xml);
        $this->assertStringContainsString('<vIPI>11.00</vIPI>', $xml);
        $this->assertStringContainsString('<vNF>121.00</vNF>', $xml); // 100 + frete 10 + IPI 11
        $this->assertStringContainsString('<vPag>121.00</vPag>', $xml);
        $this->assertStringNotContainsString('<CSOSN>', $xml);
        $this->assertEquals(121.0, (float) $documento->total);

        $this->validarNoSchema($xml);
    }

    public function test_nfce_com_cst_60_e_cartao_traz_grupo_card_e_e_valida_no_schema(): void
    {
        $this->ativarRegimeNormal();
        $this->produto->update(['cst_icms' => '60', 'aliquota_icms' => null, 'cst_pis' => '04', 'cst_cofins' => '04', 'cst_ipi' => null, 'cfop_padrao' => '5405']);

        $documento = new DocumentoFiscal([
            'empresa_id' => $this->empresa->id, 'tipo_operacao' => 'venda', 'modelo' => 65, 'serie' => '1', 'numero' => 3,
            'ambiente' => 'homologacao', 'valor_produtos' => 20, 'total' => 20, 'tpag' => '03',
        ]);

        $item = new DocumentoFiscalItem([
            'produto_id' => $this->produto->id, 'ncm' => '22030000', 'cfop' => '5405',
            'quantidade' => 1, 'valor_unitario' => 20, 'valor_total' => 20,
        ]);
        $item->setRelation('produto', $this->produto->fresh());

        $xml = (new \ReflectionMethod(NfePhpFiscalGateway::class, 'montarXmlNfce'))
            ->invoke(new NfePhpFiscalGateway(), $documento, collect([$item]), $this->empresa, ConfigFiscal::first());

        // ST já cobrada: o item leva só origem e CST, sem ICMS destacado
        $this->assertStringContainsString('<ICMS><ICMS60><orig>0</orig><CST>60</CST></ICMS60></ICMS>', $xml);
        $this->assertStringContainsString('<tPag>03</tPag>', $xml);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra></card>', $xml);

        $this->validarNoSchema($xml);
    }

    /** Valida no XSD oficial; o XML ainda não está assinado, então só a falta de ds:Signature é tolerada. */
    private function validarNoSchema(string $xml): void
    {
        try {
            Validator::isValid($xml, base_path('vendor/nfephp-org/sped-nfe/schemes/PL_010_V1.30/nfe_v4.00.xsd'));
        } catch (\Throwable $e) {
            $outros = array_filter(
                preg_split('/\R/', $e->getMessage()),
                fn ($linha) => trim($linha) !== '' && ! str_contains($linha, 'Signature')
                    && ! str_contains($linha, 'XML não foi validado') && ! str_contains($linha, 'xmldsig')
            );
            $this->assertSame([], array_values($outros), 'XML da NFe inválido no schema: '.$e->getMessage());
        }
    }

    public function test_kit_na_nfe_do_pedido_abre_em_componentes_com_o_valor_rateado(): void
    {
        $caneca = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Caneca', 'tipo' => 'fisico', 'preco_venda' => 30, 'ncm' => '69111010', 'cfop_padrao' => '5102',
        ]);
        $cerveja = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Cerveja Artesanal', 'tipo' => 'fisico', 'preco_venda' => 18, 'ncm' => '22030000', 'cfop_padrao' => '5102',
        ]);
        $ipa = ProdutoVariacao::create(['empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'IPA', 'estoque_atual' => 5]);
        $pilsen = ProdutoVariacao::create(['empresa_id' => $this->empresa->id, 'produto_id' => $cerveja->id, 'tamanho' => 'Pilsen', 'estoque_atual' => 5]);
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit Caneca + 3 Cervejas', 'tipo' => 'fisico', 'preco_venda' => 80, 'eh_kit' => true]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $this->cliente->id, 'canal' => 'site',
            'tipo_doc' => 'nao_fiscal', 'status_pagamento' => 'pago', 'valor_total' => 90, 'data_venda' => now(),
            'tipo_entrega' => 'entrega', 'valor_frete' => 10, 'status_envio' => 'a_separar',
            'endereco_entrega' => [
                'cep' => '01000-000', 'logradouro' => 'Rua Teste', 'numero' => '100', 'bairro' => 'Centro',
                'municipio' => 'São Paulo', 'uf' => 'SP', 'codigo_ibge_municipio' => '3550308',
            ],
        ]);
        $itemKit = $venda->itens()->create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $kit->id, 'quantidade' => 1,
            'valor_unitario' => 80, 'valor_total' => 80,
            'composicao' => [
                ['produto_id' => $caneca->id, 'nome' => 'Caneca', 'variacao_id' => null, 'tamanho' => null, 'quantidade' => 1],
                ['produto_id' => $cerveja->id, 'nome' => 'Cerveja Artesanal', 'variacao_id' => $ipa->id, 'tamanho' => 'IPA', 'quantidade' => 2],
                ['produto_id' => $cerveja->id, 'nome' => 'Cerveja Artesanal', 'variacao_id' => $pilsen->id, 'tamanho' => 'Pilsen', 'quantidade' => 1],
            ],
        ]);

        $resposta = $this->postJson("/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/nfe-pedido-loja")->assertCreated();

        $itens = collect($resposta->json('itens'));
        $this->assertCount(3, $itens);

        // pesos pelo preço de tabela: caneca 30 + IPA 2x18 + Pilsen 18 = 84; kit = 80
        $this->assertEqualsWithDelta(80.0, $itens->sum(fn ($i) => (float) $i['valor_total']), 0.001);
        $this->assertEqualsWithDelta(10.0, $itens->sum(fn ($i) => (float) $i['valor_frete']), 0.001);
        $this->assertSame(['Caneca', 'Cerveja Artesanal (IPA)', 'Cerveja Artesanal (Pilsen)'], $itens->pluck('descricao')->all());
        $this->assertSame('28.57', $itens[0]['valor_total']);
        $this->assertSame('34.29', $itens[1]['valor_total']);
        $this->assertSame('17.14', $itens[2]['valor_total']);
        $this->assertSame([$itemKit->id], $itens->pluck('item_venda_id')->unique()->all());
        $this->assertSame('69111010', $itens[0]['ncm']);

        $resposta->assertJsonPath('total', '90.00');

        // IPA: 2 un por 34,29 => 17,145 por unidade; o XML precisa declarar o unitário exato
        $item = DocumentoFiscalItem::find($itens[1]['id']);
        $unitario = (new \ReflectionMethod(NfePhpFiscalGateway::class, 'valorUnitarioExato'))->invoke(new NfePhpFiscalGateway(), $item);
        $this->assertEqualsWithDelta(17.145, $unitario, 0.0000001);

        // XML da NFe do pedido (modelo 55, internet) com o kit aberto: precisa fechar e passar no XSD oficial
        $documento = DocumentoFiscal::findOrFail($resposta->json('id'));
        $this->assertSame(55, $documento->modelo);
        $this->assertSame(2, (int) $documento->indicador_presenca);

        $xml = (new \ReflectionMethod(NfePhpFiscalGateway::class, 'montarXmlNfe'))->invoke(
            new NfePhpFiscalGateway(),
            $documento->load('cliente'),
            $documento->itens()->with('produto')->get(),
            $this->empresa,
            ConfigFiscal::first(),
        );

        $this->assertStringContainsString('<indPres>2</indPres>', $xml);
        $this->assertStringContainsString('<vNF>90.00</vNF>', $xml);
        $this->validarNoSchema($xml);
    }

    public function test_nfce_do_kit_vendido_no_pdv_gera_xml_valido_no_schema(): void
    {
        $caneca = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Caneca', 'tipo' => 'fisico', 'preco_venda' => 10, 'ncm' => '69111010', 'cfop_padrao' => '5102',
        ]);
        $kit = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Kit Caneca + 2 Cervejas', 'tipo' => 'fisico', 'preco_venda' => 50, 'eh_kit' => true]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'canal' => 'pdv', 'tipo_doc' => 'fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 50, 'data_venda' => now(),
        ]);
        $venda->itens()->create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $kit->id, 'quantidade' => 1,
            'valor_unitario' => 50, 'valor_total' => 50,
            'composicao' => [
                ['produto_id' => $caneca->id, 'nome' => 'Caneca', 'variacao_id' => null, 'tamanho' => null, 'quantidade' => 1],
                ['produto_id' => $this->produto->id, 'nome' => 'Cerveja Pilsen 600ml', 'variacao_id' => null, 'tamanho' => null, 'quantidade' => 2],
            ],
        ]);

        $documento = app(\App\Services\Fiscal\EmissaoFiscalService::class)->emitir($venda->fresh('itens'), 65);

        $this->assertCount(2, $documento->itens);
        $this->assertEqualsWithDelta(50.0, (float) $documento->itens->sum('valor_total'), 0.001);

        $xml = (new \ReflectionMethod(NfePhpFiscalGateway::class, 'montarXmlNfce'))->invoke(
            new NfePhpFiscalGateway(),
            $documento,
            $documento->itens()->with('produto')->get(),
            $this->empresa,
            ConfigFiscal::first(),
        );

        $this->assertStringContainsString('<vNF>50.00</vNF>', $xml);
        $this->validarNoSchema($xml);
    }

    private function documentoNfce(): DocumentoFiscal
    {
        return new DocumentoFiscal([
            'empresa_id' => $this->empresa->id, 'tipo_operacao' => 'venda', 'modelo' => 65, 'serie' => '1', 'numero' => 7,
            'ambiente' => 'homologacao', 'valor_produtos' => 40, 'total' => 40,
        ]);
    }

    private function itemNfce(?Produto $produto, float $valor = 40.0): DocumentoFiscalItem
    {
        $item = new DocumentoFiscalItem([
            'produto_id' => $produto?->id, 'ncm' => '22030000', 'cfop' => '5102',
            'quantidade' => 2, 'valor_unitario' => $valor / 2, 'valor_total' => $valor,
        ]);
        $item->setRelation('produto', $produto);

        return $item;
    }

    private function xmlNfce(DocumentoFiscal $documento, DocumentoFiscalItem $item): string
    {
        return (new \ReflectionMethod(NfePhpFiscalGateway::class, 'montarXmlNfce'))
            ->invoke(new NfePhpFiscalGateway(), $documento, collect([$item]), $this->empresa, ConfigFiscal::first());
    }

    public function test_nfce_no_regime_normal_traz_icms_pis_cofins_e_e_valida_no_schema(): void
    {
        $this->ativarRegimeNormal();
        $this->produto->update(['cst_ipi' => null]); // sem IPI: venda no balcão

        $documento = $this->documentoNfce();
        $xml = $this->xmlNfce($documento, $this->itemNfce($this->produto->fresh()));

        // consumidor final no estado, sem IPI/frete: base 40,00; ICMS 18% = 7,20
        $this->assertStringContainsString('<ICMS00>', $xml);
        $this->assertStringContainsString('<vICMS>7.20</vICMS>', $xml);
        $this->assertStringContainsString('<PISAliq>', $xml);
        $this->assertStringContainsString('<COFINSAliq>', $xml);
        $this->assertStringNotContainsString('<IPI>', $xml);
        $this->assertStringNotContainsString('<CSOSN>', $xml);
        $this->assertEquals(7.20, (float) $documento->valor_icms);

        $this->validarNoSchema($xml);
    }

    public function test_nfce_no_simples_continua_com_csosn(): void
    {
        $xml = $this->xmlNfce($this->documentoNfce(), $this->itemNfce($this->produto));

        $this->assertStringContainsString('<CSOSN>102</CSOSN>', $xml);
        $this->assertStringNotContainsString('<ICMS00>', $xml);
    }

    public function test_nfce_recusa_produto_com_ipi_tributado_e_item_sem_produto_no_regime_normal(): void
    {
        $this->ativarRegimeNormal(); // o produto de teste tem IPI 10% (CST 50)

        try {
            $this->xmlNfce($this->documentoNfce(), $this->itemNfce($this->produto->fresh()));
            $this->fail('Deveria recusar produto com IPI tributado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('IPI tributado', $e->getMessage());
            $this->assertStringContainsString('Emita uma NF-e', $e->getMessage());
        }

        try {
            $this->xmlNfce($this->documentoNfce(), $this->itemNfce(null));
            $this->fail('Deveria recusar item sem produto.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('só aceita itens de produto', $e->getMessage());
        }
    }

    public function test_emissao_de_nfce_pelo_servico_grava_os_impostos_no_documento_e_no_item(): void
    {
        $this->ativarRegimeNormal(); // troca o gateway pelo que monta o XML real
        $this->produto->update(['cst_ipi' => null]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => null, 'canal' => 'pdv', 'tipo_doc' => 'nao_fiscal',
            'status_pagamento' => 'pago', 'valor_total' => 40, 'data_venda' => now(),
        ]);
        $venda->itens()->create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id,
            'quantidade' => 2, 'valor_unitario' => 20, 'valor_total' => 40,
        ]);

        $documento = app(\App\Services\Fiscal\EmissaoFiscalService::class)->emitir($venda->fresh(), 65);

        $this->assertSame('autorizada', $documento->status);
        $this->assertEquals(7.20, (float) $documento->valor_icms);
        $this->assertSame('00', $documento->itens[0]->cst_csosn);
        $this->assertEquals(7.20, (float) $documento->itens[0]->valor_icms);
    }
}
