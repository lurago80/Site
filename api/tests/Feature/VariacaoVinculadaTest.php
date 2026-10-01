<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\Empresa;
use App\Models\ItemVenda;
use App\Models\KitComponente;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * "Vitrine" CERVEJA ARTESANAL + kits: as opções são variações VINCULADAS às
 * cervejas reais - estoque, baixa e nota saem do produto real; a vitrine e o
 * kit não têm estoque próprio.
 */
class VariacaoVinculadaTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Produto $pilsen;

    private Produto $ipa;

    private Produto $vitrine;

    private ProdutoVariacao $opcaoPilsen;

    private ProdutoVariacao $opcaoIpa;

    private Produto $caneca;

    private Produto $kit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Completo', 'valor_mensal' => 299.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Cervejaria Vitrine', 'cnpj' => '11.222.333/0001-81', 'slug' => 'cervejaria-vitrine',
            'uf' => 'SP', 'municipio' => 'São Paulo', 'codigo_ibge_municipio' => '3550308', 'cep' => '01310-100',
            'logradouro' => 'Av. Paulista', 'numero' => '1000', 'bairro' => 'Bela Vista',
            'plano_id' => $plano->id, 'status' => 'ativa',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@vitrine.com', 'password' => bcrypt('senha-teste'),
            'empresa_id' => $this->empresa->id, 'perfil' => 'admin',
        ]);

        $this->asEmpresa($this->empresa->id);

        // cervejas reais: cada uma com o seu estoque e o seu cadastro fiscal
        $this->pilsen = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'CERVEJA PILSEN', 'tipo' => 'fisico', 'preco_venda' => 20,
            'estoque_atual' => 10, 'ncm' => '22030000', 'cfop_padrao' => '5102',
        ]);
        $this->ipa = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'CERVEJA IPA', 'tipo' => 'fisico', 'preco_venda' => 20,
            'estoque_atual' => null, 'ncm' => '22030000', 'cfop_padrao' => '5102', // sem controle de estoque
        ]);

        // vitrine: sem estoque próprio, só loja virtual
        $this->vitrine = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'CERVEJA ARTESANAL', 'tipo' => 'fisico', 'preco_venda' => 20,
            'estoque_atual' => null, 'quantidade_minima_venda' => 6, 'loja_virtual' => true, 'somente_loja_virtual' => true,
        ]);
        $this->opcaoPilsen = ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->vitrine->id, 'produto_vinculado_id' => $this->pilsen->id,
            'tamanho' => 'PILSEN', 'estoque_atual' => 0,
        ]);
        $this->opcaoIpa = ProdutoVariacao::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->vitrine->id, 'produto_vinculado_id' => $this->ipa->id,
            'tamanho' => 'IPA', 'estoque_atual' => 0,
        ]);

        $this->caneca = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'CANECA', 'tipo' => 'fisico', 'preco_venda' => 30,
            'estoque_atual' => 5, 'ncm' => '69111010', 'cfop_padrao' => '5102',
        ]);
        $this->kit = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'KIT CANECA + 3', 'tipo' => 'fisico', 'preco_venda' => 80,
            'eh_kit' => true, 'somente_loja_virtual' => true,
        ]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $this->kit->id, 'produto_id' => $this->caneca->id, 'tipo' => 'fixo', 'quantidade' => 1]);
        KitComponente::create(['empresa_id' => $this->empresa->id, 'kit_id' => $this->kit->id, 'produto_id' => $this->vitrine->id, 'tipo' => 'escolha', 'quantidade' => 3]);
    }

    private function checkout(array $itens): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/loja/{$this->empresa->slug}/checkout", [
            'cliente' => [
                'nome' => 'Maria Compradora', 'email' => 'maria@example.com', 'cpf_cnpj' => '987.654.321-00', 'rg' => '22.333.444-5',
                'telefone' => '11977776666', 'cep' => '01310-100', 'logradouro' => 'Av. Paulista', 'numero' => '1000',
                'bairro' => 'Bela Vista', 'municipio' => 'São Paulo', 'uf' => 'SP', 'codigo_ibge_municipio' => '3550308',
                'consentimento_lgpd' => true,
            ],
            'itens' => $itens,
            'forma_pagamento' => 'cartao',
        ]);
    }

    public function test_catalogo_mostra_o_estoque_do_produto_vinculado_e_ilimitado_quando_ele_nao_controla(): void
    {
        $vitrine = collect($this->getJson("/api/loja/{$this->empresa->slug}/produtos")->assertOk()->json())->firstWhere('id', $this->vitrine->id);
        $porNome = collect($vitrine['variacoes'])->keyBy('tamanho');

        $this->assertSame(10, $porNome['PILSEN']['estoque_atual']);
        $this->assertSame(9999, $porNome['IPA']['estoque_atual']); // sem controle de estoque = teto de exibição
    }

    public function test_vinculo_com_produto_desativado_ou_sem_estoque_tira_a_opcao(): void
    {
        $this->ipa->update(['ativo' => false]);

        $vitrine = collect($this->getJson("/api/loja/{$this->empresa->slug}/produtos")->json())->firstWhere('id', $this->vitrine->id);
        $this->assertSame(['PILSEN'], collect($vitrine['variacoes'])->pluck('tamanho')->all());

        $this->pilsen->update(['estoque_atual' => 0]);
        $vitrine = collect($this->getJson("/api/loja/{$this->empresa->slug}/produtos")->json())->firstWhere('id', $this->vitrine->id);
        $this->assertSame(0, $vitrine['variacoes'][0]['estoque_atual']);
    }

    public function test_compra_da_vitrine_baixa_o_estoque_da_cerveja_real_e_o_item_e_da_cerveja_real(): void
    {
        $resposta = $this->checkout([['produto_id' => $this->vitrine->id, 'variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 6]])
            ->assertCreated()->assertJsonPath('valor_total', '120.00');

        $this->assertSame(4, $this->pilsen->fresh()->estoque_atual);          // baixou a real
        $this->assertSame(0, $this->opcaoPilsen->fresh()->estoque_atual);     // a variação não tem estoque próprio
        $this->assertNull($this->vitrine->fresh()->estoque_atual);

        $item = ItemVenda::latest('id')->first();
        $this->assertSame($this->pilsen->id, $item->produto_id);              // vai na nota como CERVEJA PILSEN
        $this->assertSame($this->opcaoPilsen->id, $item->produto_variacao_id);

        $itemResposta = $resposta->json('itens.0');
        $this->assertSame('CERVEJA PILSEN', $itemResposta['produto']['nome']);
        $this->assertNull($itemResposta['produto_variacao']);                 // não repete "PILSEN" ao lado do nome
    }

    public function test_venda_minima_da_vitrine_continua_valendo_somando_os_tipos(): void
    {
        $this->checkout([
            ['produto_id' => $this->vitrine->id, 'variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 2],
            ['produto_id' => $this->vitrine->id, 'variacao_id' => $this->opcaoIpa->id, 'quantidade' => 3],
        ])->assertStatus(422);

        $this->assertSame(10, $this->pilsen->fresh()->estoque_atual);

        $this->checkout([
            ['produto_id' => $this->vitrine->id, 'variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 2],
            ['produto_id' => $this->vitrine->id, 'variacao_id' => $this->opcaoIpa->id, 'quantidade' => 4],
        ])->assertCreated();

        $this->assertSame(8, $this->pilsen->fresh()->estoque_atual);
        $this->assertNull($this->ipa->fresh()->estoque_atual); // sem controle: segue sem controle
    }

    public function test_sem_estoque_na_cerveja_real_retorna_409_e_nao_baixa_nada(): void
    {
        $this->pilsen->update(['estoque_atual' => 3]);

        $this->checkout([['produto_id' => $this->vitrine->id, 'variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 6]])->assertStatus(409);

        $this->assertSame(3, $this->pilsen->fresh()->estoque_atual);
    }

    public function test_kit_baixa_a_caneca_e_as_cervejas_reais_e_nunca_o_kit(): void
    {
        $resposta = $this->checkout([[
            'produto_id' => $this->kit->id, 'quantidade' => 1,
            'escolhas' => [['variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 2], ['variacao_id' => $this->opcaoIpa->id, 'quantidade' => 1]],
        ]])->assertCreated()->assertJsonPath('valor_total', '80.00');

        $this->assertSame(4, $this->caneca->fresh()->estoque_atual);
        $this->assertSame(8, $this->pilsen->fresh()->estoque_atual);
        $this->assertNull($this->ipa->fresh()->estoque_atual);
        $this->assertNull($this->kit->fresh()->estoque_atual);

        // a composição registra as cervejas reais (nome e produto), não a vitrine
        $composicao = collect($resposta->json('itens.0.composicao'));
        $this->assertEqualsCanonicalizing(['CANECA', 'CERVEJA PILSEN', 'CERVEJA IPA'], $composicao->pluck('nome')->all());
        $this->assertContains($this->pilsen->id, $composicao->pluck('produto_id')->all());
        $this->assertNotContains($this->vitrine->id, $composicao->pluck('produto_id')->all());
    }

    public function test_kit_recusa_e_desfaz_tudo_se_faltar_estoque_da_cerveja_real(): void
    {
        $this->pilsen->update(['estoque_atual' => 1]);

        $this->checkout([[
            'produto_id' => $this->kit->id, 'quantidade' => 1,
            'escolhas' => [['variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 3]],
        ]])->assertStatus(409);

        $this->assertSame(5, $this->caneca->fresh()->estoque_atual); // caneca não fica baixada
        $this->assertSame(1, $this->pilsen->fresh()->estoque_atual);
    }

    public function test_nfe_do_pedido_do_kit_usa_o_cadastro_fiscal_de_cada_cerveja_real(): void
    {
        ConfigFiscal::create([
            'empresa_id' => $this->empresa->id, 'crt' => '1', 'serie_nfe_atual' => '1', 'numero_nfe_atual' => 0,
            'serie_nfce_atual' => '1', 'numero_nfce_atual' => 0, 'ambiente_ativo' => 'homologacao',
        ]);
        $this->ipa->update(['ncm' => '22030099']); // NCM diferente para provar que a nota usa o de cada uma

        $this->checkout([[
            'produto_id' => $this->kit->id, 'quantidade' => 1,
            'escolhas' => [['variacao_id' => $this->opcaoPilsen->id, 'quantidade' => 2], ['variacao_id' => $this->opcaoIpa->id, 'quantidade' => 1]],
        ]])->assertCreated();
        $venda = Venda::latest('id')->first();
        $venda->update(['status_pagamento' => 'pago']);

        $this->actingAs($this->admin)->postJson("/fiscal/{$this->empresa->slug}/vendas/{$venda->id}/nfe-pedido-loja")->assertCreated();

        $itens = DocumentoFiscalItem::orderBy('id')->get()->keyBy('descricao');
        $this->assertSame('22030000', $itens['CERVEJA PILSEN']->ncm);
        $this->assertSame('22030099', $itens['CERVEJA IPA']->ncm);
        $this->assertSame('69111010', $itens['CANECA']->ncm);
    }

    public function test_painel_cria_variacao_vinculada_e_valida_o_vinculo(): void
    {
        $url = "/dashboard/{$this->empresa->slug}/produtos/{$this->vitrine->id}/variacoes";
        $novo = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'CERVEJA WEISS', 'tipo' => 'fisico', 'preco_venda' => 20, 'estoque_atual' => 7]);

        $this->actingAs($this->admin)->postJson($url, ['tamanho' => 'WEISS', 'produto_vinculado_id' => $novo->id])
            ->assertCreated()->assertJsonPath('produto_vinculado_id', $novo->id);

        $lista = $this->actingAs($this->admin)->getJson($url)->assertOk()->json();
        $weiss = collect($lista)->firstWhere('tamanho', 'WEISS');
        $this->assertSame(7, $weiss['produto_vinculado']['estoque_atual']);

        // regras do vínculo
        $this->actingAs($this->admin)->postJson($url, ['tamanho' => 'X', 'produto_vinculado_id' => $this->vitrine->id])->assertStatus(422);  // ele mesmo
        $this->actingAs($this->admin)->postJson($url, ['tamanho' => 'X', 'produto_vinculado_id' => $this->kit->id])->assertStatus(422);       // kit
        $this->actingAs($this->admin)->postJson($url, ['tamanho' => 'X', 'produto_vinculado_id' => 999999])->assertStatus(422);               // inexistente

        // produto que já tem variações próprias não serve de vínculo
        ProdutoVariacao::create(['empresa_id' => $this->empresa->id, 'produto_id' => $this->caneca->id, 'tamanho' => 'P', 'estoque_atual' => 1]);
        $this->actingAs($this->admin)->postJson($url, ['tamanho' => 'X', 'produto_vinculado_id' => $this->caneca->id])->assertStatus(422);

        // desvincular volta a usar o estoque próprio
        $this->actingAs($this->admin)->putJson("{$url}/{$weiss['id']}", ['produto_vinculado_id' => null, 'estoque_atual' => 4])->assertOk();
        $this->assertNull(ProdutoVariacao::find($weiss['id'])->produto_vinculado_id);
    }

    public function test_cerveja_real_vinculada_nao_pode_ser_excluida_so_desativada(): void
    {
        $resposta = $this->actingAs($this->admin)->deleteJson("/dashboard/{$this->empresa->slug}/produtos/{$this->pilsen->id}")->assertOk();

        $resposta->assertJsonPath('acao', 'desativado');
        $this->assertStringContainsString('variações vinculadas', $resposta->json('motivo'));
        $this->assertNotNull(Produto::find($this->pilsen->id));
        $this->assertSame($this->pilsen->id, $this->opcaoPilsen->fresh()->produto_vinculado_id);
    }
}
