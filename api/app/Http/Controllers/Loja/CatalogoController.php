<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\ConfigPagamento;
use App\Models\Cupom;
use App\Models\Produto;
use Illuminate\Http\Request;

/**
 * Catálogo público de uma empresa: produtos físicos e horários de
 * agendamento em aberto. Não exige login (ver Escopo v2, seção 2.1).
 * O isolamento por empresa já vem garantido pelo RLS + SetTenantContext,
 * então as queries aqui não precisam (e não devem) filtrar por empresa_id
 * manualmente.
 */
class CatalogoController extends Controller
{
    /**
     * Dados públicos da empresa para o front-end da loja montar a
     * identidade visual (logo/cor) - nunca inclui nada sensível
     * (endereço fiscal, documentos, etc. ficam de fora de propósito).
     */
    public function info(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json([
            'razao_social' => $empresaAtual->razao_social,
            'segmento' => $empresaAtual->segmento,
            'logo_url' => $empresaAtual->logo_url,
            'cor_primaria' => $empresaAtual->cor_primaria,
            'modulo_agendamento_ativo' => $empresaAtual->modulo_agendamento_ativo,
        ]);
    }

    /**
     * Chave PÚBLICA do gateway de pagamento configurado (nunca o
     * access_token/client_secret) - o front-end da loja precisa dela
     * para inicializar o SDK de tokenização de cartão no navegador do
     * cliente (ex. Mercado Pago Bricks). Sem gateway ativo, retorna
     * null - o checkout do front-end deve então só oferecer Pix.
     */
    public function configPagamentoPublica(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');
        $config = ConfigPagamento::where('empresa_id', $empresaAtual->id)->where('ativo', true)->first();

        if ($config === null || empty($config->public_key)) {
            return response()->json(['gateway' => null, 'public_key' => null]);
        }

        return response()->json([
            'gateway' => $config->gateway,
            'public_key' => $config->public_key,
        ]);
    }

    /**
     * Validação prévia do cupom antes do checkout de fato - o front-end
     * chama isso ao clicar "Aplicar" para mostrar o desconto na hora,
     * sem precisar esperar o pedido inteiro ser enviado. O checkout
     * (CheckoutController) reaplica a mesma validação por conta própria
     * na hora de fechar o pedido - nunca confia só nesta prévia.
     */
    public function validarCupom(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $cupom = Cupom::whereRaw('lower(codigo) = ?', [mb_strtolower($dados['codigo'])])->first();

        if ($cupom === null) {
            return response()->json(['valido' => false, 'message' => 'Cupom não encontrado.'], 404);
        }

        if ($motivo = $cupom->motivoInvalido()) {
            return response()->json(['valido' => false, 'message' => $motivo], 422);
        }

        $desconto = $cupom->calcularDesconto((float) $dados['subtotal']);

        return response()->json([
            'valido' => true,
            'codigo' => $cupom->codigo,
            'tipo' => $cupom->tipo,
            'valor_desconto' => $desconto,
        ]);
    }

    public function produtos(string $empresa)
    {
        return Produto::query()
            ->where('tipo', 'fisico')
            ->orderBy('nome')
            ->get();
    }

    public function agenda(Request $request, string $empresa)
    {
        $data = $request->validate([
            'produto_id' => ['nullable', 'integer'],
            'data' => ['nullable', 'date'],
        ]);

        return AgendaVisitacao::query()
            ->where('status', 'aberta')
            ->when($data['produto_id'] ?? null, fn ($q, $produtoId) => $q->where('produto_id', $produtoId))
            ->when($data['data'] ?? null, fn ($q, $dia) => $q->whereDate('data_hora', $dia))
            ->where('data_hora', '>=', now())
            ->orderBy('data_hora')
            ->get()
            ->map(fn (AgendaVisitacao $agenda) => [
                'id' => $agenda->id,
                'data_hora' => $agenda->data_hora,
                'vagas_disponiveis' => $agenda->vagasDisponiveis(),
                'valor_visita' => $agenda->valor_visita,
            ]);
    }
}
