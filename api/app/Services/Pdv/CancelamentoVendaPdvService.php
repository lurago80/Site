<?php

namespace App\Services\Pdv;

use App\Models\AgendaVisitacao;
use App\Models\Cupom;
use App\Models\DocumentoFiscal;
use App\Models\ItemVenda;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\Venda;
use App\Services\Fiscal\EmissaoFiscalService;
use Illuminate\Support\Facades\DB;

/**
 * Cancela uma venda do PDV numa operação só: cancela a NFC-e na SEFAZ (quando
 * houver nota autorizada), devolve o estoque, libera as vagas da visita,
 * estorna o dinheiro no caixa e marca a venda como cancelada, guardando quem
 * cancelou, quando e o motivo (a mudança também cai no log de auditoria).
 *
 * Tudo roda numa transação e a SEFAZ é o ÚLTIMO passo: se ela recusar o
 * cancelamento (ex.: fora do prazo), nada do que veio antes fica gravado.
 */
class CancelamentoVendaPdvService
{
    public const MOTIVO_MINIMO = 15;

    public function __construct(
        private readonly EmissaoFiscalService $emissaoFiscalService,
        private readonly CaixaService $caixaService,
    ) {}

    public function cancelar(Venda $venda, int $usuarioId, string $motivo): Venda
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < self::MOTIVO_MINIMO) {
            throw new \InvalidArgumentException('Informe o motivo do cancelamento (mínimo de '.self::MOTIVO_MINIMO.' caracteres).');
        }

        return DB::transaction(function () use ($venda, $usuarioId, $motivo) {
            $venda = Venda::query()->with(['itens', 'formaPagamento', 'empresa'])->lockForUpdate()->findOrFail($venda->id);

            $this->validar($venda);

            $nfce = DocumentoFiscal::query()
                ->where('venda_id', $venda->id)
                ->where('modelo', 65)
                ->where('status', 'autorizada')
                ->latest('id')
                ->first();

            // Dinheiro: o estorno no caixa pode ser recusado (caixa fechado / outro turno),
            // então vem antes de qualquer alteração.
            $this->caixaService->estornarVenda($venda->empresa, $usuarioId, $venda);

            foreach ($venda->itens as $item) {
                $this->devolverItem($item);
            }

            if ($venda->cupom_id !== null) {
                Cupom::query()->whereKey($venda->cupom_id)->where('usos_realizados', '>', 0)->decrement('usos_realizados');
            }

            $venda->update([
                'status_pagamento' => 'cancelado',
                'cancelada_em' => now(),
                'cancelada_por_usuario_id' => $usuarioId,
                'motivo_cancelamento' => $motivo,
            ]);

            // SEFAZ por último: se recusar, a exceção desfaz tudo acima.
            if ($nfce !== null) {
                $this->emissaoFiscalService->cancelar($nfce, mb_substr($motivo, 0, 255));
            }

            return $venda->fresh(['itens', 'documentoFiscal', 'canceladaPor']);
        });
    }

    private function validar(Venda $venda): void
    {
        if ($venda->canal !== 'pdv') {
            throw new \RuntimeException('Só é possível cancelar vendas feitas no PDV.');
        }

        if ($venda->status_pagamento === 'cancelado') {
            throw new \RuntimeException('Esta venda já está cancelada.');
        }

        if ($venda->check_in_em !== null) {
            throw new \RuntimeException('A visita desta venda já foi confirmada na entrada e a venda não pode ser cancelada.');
        }

        $documentos = DocumentoFiscal::query()->where('venda_id', $venda->id)->get();

        if ($documentos->contains(fn (DocumentoFiscal $d) => $d->tipo_operacao === 'devolucao' && $d->status === 'autorizada')) {
            throw new \RuntimeException('Esta venda tem devolução fiscal emitida e não pode ser cancelada.');
        }

        if ($documentos->contains(fn (DocumentoFiscal $d) => $d->modelo === 55 && $d->status === 'autorizada')) {
            throw new \RuntimeException('Esta venda tem NF-e autorizada. Cancele a NF-e pela tela Fiscal antes de cancelar a venda.');
        }
    }

    private function devolverItem(ItemVenda $item): void
    {
        $quantidade = (int) $item->quantidade;

        if ($item->agenda_visitacao_id !== null) {
            $agenda = AgendaVisitacao::query()->whereKey($item->agenda_visitacao_id)->lockForUpdate()->first();

            if ($agenda !== null) {
                $agenda->update(['vagas_reservadas' => max(0, (int) $agenda->vagas_reservadas - $quantidade)]);

                if ($agenda->status === 'lotada' && $agenda->vagasDisponiveis() > 0) {
                    $agenda->update(['status' => 'aberta']);
                }
            }

            return;
        }

        if ($item->produto_variacao_id !== null) {
            $variacao = ProdutoVariacao::query()->find($item->produto_variacao_id);

            if ($variacao !== null && $variacao->produto_vinculado_id === null) {
                $variacao->increment('estoque_atual', $quantidade);

                return;
            }

            // variação vinculada: o estoque que baixou foi o do produto vinculado (= produto do item)
        }

        $produto = $item->produto_id !== null ? Produto::query()->lockForUpdate()->find($item->produto_id) : null;

        if ($produto !== null && $produto->estoque_atual !== null) {
            $produto->increment('estoque_atual', $quantidade);
        }
    }
}
