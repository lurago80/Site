<?php

namespace App\Services\Vendas;

use App\Models\Empresa;
use App\Models\FreteRegra;

/**
 * Calcula o frete da loja pública a partir da tabela por UF da empresa
 * (regra com uf nula vale para os estados sem regra própria). Frete grátis
 * e retirada na loja são opcionais e configurados por empresa.
 */
class FreteService
{
    /**
     * @return array{disponivel: bool, valor: float, gratis: bool, prazo_dias: int|null, mensagem: string|null}
     */
    public function cotarEntrega(Empresa $empresa, ?string $uf, float $subtotalProdutos): array
    {
        $uf = $uf !== null ? strtoupper(trim($uf)) : null;

        $regra = FreteRegra::where('uf', $uf)->first() ?? FreteRegra::whereNull('uf')->first();

        // Empresa que ainda não configurou a tabela de frete segue vendendo
        // como antes (frete zero) - só passa a recusar UF sem regra depois
        // que a tabela existe.
        if ($regra === null && ! FreteRegra::exists()) {
            return ['disponivel' => true, 'valor' => 0.0, 'gratis' => false, 'prazo_dias' => null, 'mensagem' => null];
        }

        if ($regra === null) {
            return [
                'disponivel' => false,
                'valor' => 0.0,
                'gratis' => false,
                'prazo_dias' => null,
                'mensagem' => 'Não realizamos entregas para este estado.',
            ];
        }

        $gratis = $empresa->frete_gratis_acima !== null
            && $subtotalProdutos >= (float) $empresa->frete_gratis_acima;

        return [
            'disponivel' => true,
            'valor' => $gratis ? 0.0 : (float) $regra->valor,
            'gratis' => $gratis,
            'prazo_dias' => $regra->prazo_dias,
            'mensagem' => null,
        ];
    }
}
