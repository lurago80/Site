<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\AgendaVisitacao;
use App\Models\Cliente;
use App\Models\ConfigPagamento;
use App\Models\Cupom;
use App\Models\Produto;
use App\Models\Venda;
use App\Services\Vendas\FreteService;
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
    public function __construct(private readonly FreteService $freteService) {}

    /**
     * Cotação de frete para o checkout: entrega (pela UF informada) e se a
     * loja oferece retirada. O valor definitivo é recalculado no checkout.
     */
    public function frete(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'uf' => ['nullable', 'string', 'size:2'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json([
            'entrega' => $this->freteService->cotarEntrega($empresaAtual, $dados['uf'] ?? null, (float) ($dados['subtotal'] ?? 0)),
            'frete_gratis_acima' => $empresaAtual->frete_gratis_acima,
            'permite_retirada' => $empresaAtual->permite_retirada,
            'instrucoes_retirada' => $empresaAtual->instrucoes_retirada,
        ]);
    }

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
            'nome_fantasia' => $empresaAtual->nome_fantasia ?? $empresaAtual->razao_social,
            'segmento' => $empresaAtual->segmento,
            'logo_url' => $empresaAtual->logo_url,
            'cor_primaria' => $empresaAtual->cor_primaria,
            'modulo_agendamento_ativo' => $empresaAtual->modulo_agendamento_ativo,
        ]);
    }

    /**
     * Recibo público do pedido - o cliente acessa pelo link salvo/compartilhado
     * depois do checkout, para imprimir ou mostrar no celular na chegada da
     * visita. Só expõe o primeiro nome do cliente (não o documento nem
     * contato completo) - o link em si (id sequencial) não é um segredo
     * forte, então evitamos vazar dado pessoal sensível por ele.
     */
    public function pedido(Request $request, string $empresa, int $id)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $venda = Venda::with(['itens.produto', 'itens.produtoVariacao', 'itens.agendaVisitacao', 'cliente'])
            ->findOrFail($id);

        return response()->json([
            'id' => $venda->id,
            'status_pagamento' => $venda->status_pagamento,
            'valor_total' => $venda->valor_total,
            'valor_desconto' => $venda->valor_desconto,
            'valor_frete' => $venda->valor_frete,
            'tipo_entrega' => $venda->tipo_entrega,
            'data_venda' => $venda->data_venda,
            'cliente_primeiro_nome' => $venda->cliente ? explode(' ', trim($venda->cliente->nome))[0] : null,
            'itens' => $venda->itens,
            'empresa' => [
                'nome_fantasia' => $empresaAtual->nome_fantasia ?? $empresaAtual->razao_social,
                'logo_url' => $empresaAtual->logo_url,
                'cor_primaria' => $empresaAtual->cor_primaria,
            ],
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
            // Subtotal e quantidade só da visita agendada - o cupom nunca
            // desconta produtos (regra de negócio explícita do cliente),
            // ver Cupom::calcularDescontoVisita.
            'subtotal_visitas' => ['required', 'numeric', 'min:0'],
            'quantidade_tickets' => ['required', 'integer', 'min:0'],
        ]);

        if ($dados['quantidade_tickets'] === 0) {
            return response()->json(['valido' => false, 'message' => 'O cupom só pode ser aplicado quando há uma visita agendada no carrinho.'], 422);
        }

        $cupom = Cupom::whereRaw('lower(codigo) = ?', [mb_strtolower($dados['codigo'])])->first();

        if ($cupom === null) {
            return response()->json(['valido' => false, 'message' => 'Cupom não encontrado.'], 404);
        }

        if ($motivo = $cupom->motivoInvalido()) {
            return response()->json(['valido' => false, 'message' => $motivo], 422);
        }

        $desconto = $cupom->calcularDescontoVisita((float) $dados['subtotal_visitas'], (int) $dados['quantidade_tickets']);

        return response()->json([
            'valido' => true,
            'codigo' => $cupom->codigo,
            'tipo' => $cupom->tipo,
            'valor_desconto' => $desconto,
        ]);
    }

    /**
     * Preenchimento automático no checkout para cliente que já comprou
     * antes - evita digitar tudo de novo. Só busca por CPF/CNPJ EXATO
     * (o cliente já precisa saber o próprio documento pra acionar isso),
     * nunca por nome/e-mail parcial - impede que alguém "pesque" dados
     * de outro cliente da loja só tentando combinações.
     */
    public function buscarCliente(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'cpf_cnpj' => ['required', 'string', 'max:18'],
        ]);

        $cliente = Cliente::where('cpf_cnpj', $dados['cpf_cnpj'])->first();

        if ($cliente === null) {
            return response()->json(['encontrado' => false]);
        }

        return response()->json([
            'encontrado' => true,
            'nome' => $cliente->nome,
            'email' => $cliente->email,
            'telefone' => $cliente->telefone,
            'rg' => $cliente->rg,
            'inscricao_estadual' => $cliente->inscricao_estadual,
            'cep' => $cliente->cep,
            'logradouro' => $cliente->logradouro,
            'numero' => $cliente->numero,
            'bairro' => $cliente->bairro,
            'municipio' => $cliente->municipio,
            'uf' => $cliente->uf,
            'codigo_ibge_municipio' => $cliente->codigo_ibge_municipio,
        ]);
    }

    public function produtos(string $empresa)
    {
        return Produto::query()
            ->where('tipo', 'fisico')
            ->where('loja_virtual', true)
            ->with(['variacoes' => fn ($q) => $q->where('ativo', true)->orderBy('tamanho')])
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
