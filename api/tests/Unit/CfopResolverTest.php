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
        $cfop = $this->resolver->resolver('SP', 'SP', '5101', false);

        $this->assertSame('5101', $cfop);
    }

    public function test_venda_normal_fora_do_estado_troca_primeiro_digito_para_6(): void
    {
        $cfop = $this->resolver->resolver('SP', 'RJ', '5101', false);

        $this->assertSame('6101', $cfop);
    }

    public function test_venda_normal_sem_cfop_de_produto_usa_5102_como_padrao(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', null, false);

        $this->assertSame('5102', $cfop);
    }

    public function test_regularizacao_de_nfce_dentro_do_estado_usa_5929(): void
    {
        $cfop = $this->resolver->resolver('SP', 'SP', '5102', true);

        $this->assertSame('5929', $cfop);
    }

    public function test_regularizacao_de_nfce_fora_do_estado_usa_6929(): void
    {
        $cfop = $this->resolver->resolver('SP', 'MG', '5102', true);

        $this->assertSame('6929', $cfop);
    }

    public function test_comparacao_de_uf_ignora_maiusculas_minusculas(): void
    {
        $cfop = $this->resolver->resolver('sp', 'SP', '5101', false);

        $this->assertSame('5101', $cfop);
    }
}
