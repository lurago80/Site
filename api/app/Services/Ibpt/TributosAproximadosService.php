<?php

namespace App\Services\Ibpt;

use App\Models\IbptProduto;

/**
 * Valor aproximado dos tributos de um item (Lei da Transparência Fiscal,
 * 12.741/2012) a partir da tabela do IBPT importada em ibpt_produtos:
 * valor × (federal + estadual + municipal) da alíquota do NCM.
 * Mercadoria de origem estrangeira (1, 2, 3, 6, 7, 8) usa a alíquota federal
 * de importados. NCM fora da tabela resulta em 0 (a nota não deixa de sair).
 */
class TributosAproximadosService
{
    private const ORIGENS_IMPORTADAS = ['1', '2', '3', '6', '7', '8'];

    /** @var array<string, ?IbptProduto> */
    private array $cache = [];

    /**
     * @return array{valor: float, percentual: float} percentual total aplicado (ex.: 51.43)
     */
    public function calcular(?string $ncm, float $valorBase, string|int|null $origem = '0'): array
    {
        $linha = $this->buscar(preg_replace('/\D/', '', (string) $ncm));

        if ($linha === null || $valorBase <= 0) {
            return ['valor' => 0.0, 'percentual' => 0.0];
        }

        $federal = in_array((string) $origem, self::ORIGENS_IMPORTADAS, true)
            ? (float) $linha->aliquota_importados_federal
            : (float) $linha->aliquota_nacional_federal;

        $percentual = $federal + (float) $linha->aliquota_estadual + (float) $linha->aliquota_municipal;

        return [
            'valor' => round($valorBase * $percentual / 100, 2),
            'percentual' => round($percentual, 2),
        ];
    }

    private function buscar(string $ncm): ?IbptProduto
    {
        if ($ncm === '') {
            return null;
        }

        return $this->cache[$ncm] ??= IbptProduto::where('codigo', $ncm)
            ->orderByRaw('ex IS NOT NULL') // prefere a linha geral (sem exceção "ex")
            ->first();
    }
}
