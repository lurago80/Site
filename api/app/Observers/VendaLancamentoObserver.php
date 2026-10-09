<?php

namespace App\Observers;

use App\Models\Venda;
use App\Services\Vendas\LancamentoVendaService;

/**
 * Mantém os lançamentos da venda no plano de contas:
 *  - loja virtual: lança quando o pagamento é aprovado (PDV lança ao finalizar, no VendaPdvService);
 *  - qualquer canal: apaga os lançamentos quando a venda é cancelada.
 */
class VendaLancamentoObserver
{
    public function __construct(private readonly LancamentoVendaService $lancamentos) {}

    public function updated(Venda $venda): void
    {
        $cancelada = $venda->status_pagamento === 'cancelado' || $venda->cancelada_em !== null;

        if ($cancelada && $venda->wasChanged(['status_pagamento', 'cancelada_em'])) {
            $this->lancamentos->estornar($venda);

            return;
        }

        if ($venda->canal === 'site' && $venda->status_pagamento === 'pago' && $venda->wasChanged('status_pagamento')) {
            $this->lancamentos->registrar($venda);
        }
    }
}
