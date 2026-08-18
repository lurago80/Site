<?php

namespace Tests\Unit;

use App\Services\Fiscal\CfopResolver;
use Tests\TestCase;

class CfopResolverTest extends TestCase
{
    private CfopResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CfopResolver();
    }

    public function test_venda_normal_dentro_do_estado_usa_cfop_padrao_do_produto(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', '5101', CfopResolver::TIPO_VENDA);

        $this->assertSame('5101', $cfop);
    }

    public function test_venda_normal_fora_do_estado_troca_primeiro_digito_para_6(): void
    {
        $cfop = $this->resolver->resolver('SP', 'RJ', '5101', CfopResolver::TIPO_VENDA);

        $this->assertSame('6101', $cfop);
    }

    public function test_venda_normal_sem_cfop_de_produto_usa_5102_como_padrao(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', null, CfopResolver::TIPO_VENDA);

        $this->assertSame('5102', $cfop);
    }

    public function test_regularizacao_de_nfce_dentro_do_estado_usa_5929(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', '5102', CfopResolver::TIPO_REGULARIZACAO_NFCE);

        $this->assertSame('5929', $cfop);
    }

    public function test_regularizacao_de_nfce_fora_do_estado_usa_6929(): void
    {
        $cfop = $this->resolver->resolver('SP', 'MG', '5102', CfopResolver::TIPO_REGULARIZACAO_NFCE);

        $this->assertSame('6929', $cfop);
    }

    public function test_comparacao_de_uf_ignora_maiusculas_minusculas(): void
    {
        $cfop = $this->resolver->resolver('sp', 'SP', '5101', CfopResolver::TIPO_VENDA);

        $this->assertSame('5101', $cfop);
    }

    public function test_devolucao_ao_cliente_dentro_do_estado_usa_1202(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', '5101', CfopResolver::TIPO_DEVOLUCAO_CLIENTE);

        $this->assertSame('1202', $cfop);
    }

    public function test_devolucao_ao_cliente_fora_do_estado_usa_2202(): void
    {
        $cfop = $this->resolver->resolver('SP', 'RJ', '5101', CfopResolver::TIPO_DEVOLUCAO_CLIENTE);

        $this->assertSame('2202', $cfop);
    }

    public function test_devolucao_ao_fornecedor_dentro_do_estado_usa_5202(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', '5101', CfopResolver::TIPO_DEVOLUCAO_FORNECEDOR);

        $this->assertSame('5202', $cfop);
    }

    public function test_devolucao_ao_fornecedor_fora_do_estado_usa_6202(): void
    {
        $cfop = $this->resolver->resolver('SP', 'RJ', '5101', CfopResolver::TIPO_DEVOLUCAO_FORNECEDOR);

        $this->assertSame('6202', $cfop);
    }

    public function test_tipo_de_operacao_invalido_lanca_excecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver->resolver('SP', 'SP', '5101', 'tipo_inexistente');
    }
}
