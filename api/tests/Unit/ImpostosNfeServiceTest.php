<?php

namespace Tests\Unit;

use App\Models\Produto;
use App\Services\Fiscal\ImpostosNfeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cálculo de ICMS/IPI/PIS/COFINS do regime normal (CRT 3). Os valores
 * esperados foram conferidos à mão - a idéia é travar a regra, para que uma
 * mudança no cálculo apareça aqui antes de aparecer numa nota.
 */
class ImpostosNfeServiceTest extends TestCase
{
    private ImpostosNfeService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new ImpostosNfeService();
    }

    private function produto(array $extra = []): Produto
    {
        $produto = new Produto();
        $produto->forceFill(array_merge([
            'nome' => 'Cerveja Pilsen 600ml',
            'cst_origem' => '0',
            'cst_icms' => '00', 'aliquota_icms' => 18,
            'cst_pis' => '01', 'aliquota_pis' => 0.65,
            'cst_cofins' => '01', 'aliquota_cofins' => 3,
            'cst_ipi' => '50', 'aliquota_ipi' => 10, 'codigo_enquadramento_ipi' => '999',
        ], $extra));

        return $produto;
    }

    private function contexto(array $extra = []): array
    {
        return array_merge([
            'interno' => true, 'uf_emitente' => 'SP', 'uf_destino' => 'SP',
            'destinatario_contribuinte' => false, 'exclui_icms_pis_cofins' => true,
        ], $extra);
    }

    public function test_consumidor_final_no_estado_ipi_e_frete_entram_na_base_do_icms(): void
    {
        $r = $this->servico->calcularItem($this->produto(), 100.00, 10.00, $this->contexto());

        $this->assertSame(11.00, $r['ipi']['vIPI']);          // 10% de (100 + 10)
        $this->assertSame(121.00, $r['icms']['vBC']);         // 100 + 10 + IPI 11
        $this->assertSame(21.78, $r['icms']['vICMS']);        // 18% de 121
        $this->assertSame(88.22, $r['pis']['vBC']);           // 110 - ICMS 21,78
        $this->assertSame(0.57, $r['pis']['valor']);
        $this->assertSame(2.65, $r['cofins']['valor']);       // 3% de 88,22
        $this->assertSame('0', $r['orig']);
    }

    public function test_contribuinte_deixa_o_ipi_fora_da_base_do_icms(): void
    {
        $r = $this->servico->calcularItem($this->produto(), 100.00, 10.00, $this->contexto(['destinatario_contribuinte' => true]));

        $this->assertSame(110.00, $r['icms']['vBC']);
        $this->assertSame(19.80, $r['icms']['vICMS']);
        $this->assertSame(90.20, $r['cofins']['vBC']);
        $this->assertSame(2.71, $r['cofins']['valor']);
    }

    public function test_pis_cofins_sem_a_exclusao_do_icms_usa_a_base_cheia(): void
    {
        $r = $this->servico->calcularItem($this->produto(), 100.00, 10.00, $this->contexto([
            'destinatario_contribuinte' => true, 'exclui_icms_pis_cofins' => false,
        ]));

        $this->assertSame(110.00, $r['cofins']['vBC']);
        $this->assertSame(3.30, $r['cofins']['valor']);
    }

    public function test_cst_20_aplica_a_reducao_de_base(): void
    {
        $r = $this->servico->calcularItem(
            $this->produto(['cst_icms' => '20', 'reducao_base_calculo_icms' => 33.33]),
            100.00, 10.00, $this->contexto(['destinatario_contribuinte' => true]),
        );

        $this->assertSame(73.34, $r['icms']['vBC']);   // 110 x (1 - 33,33%)
        $this->assertSame(13.20, $r['icms']['vICMS']);
        $this->assertSame(33.33, $r['icms']['pRedBC']);
    }

    public function test_cst_20_sem_reducao_cadastrada_e_recusado(): void
    {
        $this->expectExceptionMessage('redução de base');
        $this->servico->calcularItem($this->produto(['cst_icms' => '20']), 100, 0, $this->contexto());
    }

    public function test_icms_isento_e_pis_cofins_nao_tributados_so_informam_o_cst(): void
    {
        $r = $this->servico->calcularItem(
            $this->produto(['cst_icms' => '40', 'cst_pis' => '07', 'cst_cofins' => '07', 'cst_ipi' => '52']),
            100.00, 0, $this->contexto(),
        );

        $this->assertSame(['cst' => '40', 'vBC' => 0.0, 'pICMS' => 0.0, 'vICMS' => 0.0], $r['icms']);
        $this->assertSame(['cst' => '07'], $r['pis']);
        $this->assertSame(['cst' => '07'], $r['cofins']);
        $this->assertSame(['cst' => '52', 'cEnq' => '999', 'vIPI' => 0.0], $r['ipi']);
    }

    public function test_produto_sem_ipi_nao_gera_grupo_de_ipi(): void
    {
        $r = $this->servico->calcularItem($this->produto(['cst_ipi' => null]), 100, 0, $this->contexto());

        $this->assertNull($r['ipi']);
        $this->assertSame(100.00, $r['icms']['vBC']);
    }

    public function test_aliquota_interestadual_para_contribuinte(): void
    {
        $ctx = fn (string $de, string $para) => $this->contexto([
            'interno' => false, 'uf_emitente' => $de, 'uf_destino' => $para, 'destinatario_contribuinte' => true,
        ]);

        $this->assertSame(7.0, $this->servico->calcularItem($this->produto(), 100, 0, $ctx('SP', 'BA'))['icms']['pICMS']);
        $this->assertSame(7.0, $this->servico->calcularItem($this->produto(), 100, 0, $ctx('MG', 'ES'))['icms']['pICMS']);
        $this->assertSame(12.0, $this->servico->calcularItem($this->produto(), 100, 0, $ctx('SP', 'RJ'))['icms']['pICMS']);
        $this->assertSame(12.0, $this->servico->calcularItem($this->produto(), 100, 0, $ctx('BA', 'SP'))['icms']['pICMS']);
    }

    #[DataProvider('casosRecusados')]
    public function test_casos_sem_regra_implementada_sao_recusados(array $produto, array $contexto, string $trechoDaMensagem): void
    {
        try {
            $this->servico->calcularItem($this->produto($produto), 100, 0, $this->contexto($contexto));
            $this->fail('Deveria recusar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($trechoDaMensagem, $e->getMessage());
        }
    }

    public static function casosRecusados(): array
    {
        return [
            'ST (CST 60)' => [['cst_icms' => '60'], [], 'CST de ICMS 60'],
            'ST (CST 10)' => [['cst_icms' => '10'], [], 'CST de ICMS 10'],
            'sem CST de ICMS' => [['cst_icms' => null], [], 'sem CST de ICMS'],
            'CST 00 sem alíquota' => [['aliquota_icms' => null], [], 'sem alíquota de ICMS'],
            'interestadual para consumidor final (DIFAL)' => [[], ['interno' => false, 'uf_destino' => 'RJ'], 'DIFAL'],
            'sem CST de PIS' => [['cst_pis' => null], [], 'sem CST de PIS'],
            'sem CST de COFINS' => [['cst_cofins' => null], [], 'sem CST de COFINS'],
            'PIS por unidade (CST 03)' => [['cst_pis' => '03'], [], 'CST de PIS 03'],
        ];
    }
}
