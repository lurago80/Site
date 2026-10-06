<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Compra;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\Empresa;
use App\Models\NumeracaoInutilizada;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Models\Venda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestra a emissão, cancelamento e inutilização de documentos fiscais
 * (NFe/NFC-e): reserva o próximo número da série (com lock, mesmo
 * princípio anti-concorrência do ReservaVagaService), delega a operação
 * em si ao FiscalGatewayInterface configurado, e persiste o resultado.
 *
 * Modelo 55 = NFe, 65 = NFC-e (ver Escopo v2, seção 4.2).
 */
class EmissaoFiscalService
{
    public function __construct(
        private readonly FiscalGatewayInterface $gateway,
        private readonly CfopResolver $cfopResolver = new CfopResolver(),
    ) {}

    public function emitir(Venda $venda, int $modelo, ?int $documentoOrigemId = null): DocumentoFiscal
    {
        if (! in_array($modelo, [55, 65], true)) {
            throw new \InvalidArgumentException('Modelo fiscal inválido: use 55 (NFe) ou 65 (NFC-e).');
        }

        return DB::transaction(function () use ($venda, $modelo, $documentoOrigemId) {
            $configFiscal = ConfigFiscal::query()
                ->where('empresa_id', $venda->empresa_id)
                ->lockForUpdate()
                ->first();

            if ($configFiscal === null) {
                throw new \RuntimeException('Empresa não possui configuração fiscal cadastrada (config_fiscal).');
            }

            [$serie, $numero] = $this->proximoNumero($configFiscal, $modelo);

            $tipoOperacao = $documentoOrigemId !== null
                ? CfopResolver::TIPO_REGULARIZACAO_NFCE
                : CfopResolver::TIPO_VENDA;

            $documento = DocumentoFiscal::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'documento_fiscal_origem_id' => $documentoOrigemId,
                'tipo_operacao' => $tipoOperacao,
                'modelo' => $modelo,
                'serie' => $serie,
                'numero' => $numero,
                'ambiente' => $configFiscal->ambiente_ativo,
                'status' => 'contingencia',
                'total' => $venda->valor_total,
                'valor_produtos' => $venda->valor_total,
            ]);

            $certificado = CertificadoDigital::query()
                ->where('empresa_id', $venda->empresa_id)
                ->first();

            $itensEmMemoria = $this->montarItensEmMemoria($venda, $documento, $tipoOperacao);

            $resultado = $this->gateway->emitir(
                $documento,
                $itensEmMemoria,
                $venda->empresa,
                $configFiscal,
                $certificado,
            );

            $documento->update([
                'status' => $resultado->status,
                'chave_acesso' => $resultado->chaveAcesso,
                'protocolo_autorizacao' => $resultado->protocoloAutorizacao,
                'xml_path' => $this->salvarXml($venda->empresa_id, $resultado->chaveAcesso, $documento->id, $resultado->xml),
                'motivo_cancelamento' => $resultado->motivoRejeicao,
            ]);

            foreach ($itensEmMemoria as $item) {
                $item->documento_fiscal_id = $documento->id;
                $item->save();
            }

            if ($venda->tipo_doc !== 'fiscal') {
                $venda->update(['tipo_doc' => 'fiscal']);
            }

            return $documento->fresh('itens');
        });
    }

    /**
     * Converte uma venda não fiscal (ex.: registrada só no PDV sem nota)
     * em uma venda fiscal, emitindo o documento correspondente agora.
     */
    public function importarVendaNaoFiscal(Venda $venda, int $modelo): DocumentoFiscal
    {
        if ($venda->tipo_doc === 'fiscal') {
            throw new \RuntimeException('Esta venda já possui documento fiscal emitido.');
        }

        return $this->emitir($venda, $modelo);
    }

    /**
     * Emite uma NFe (modelo 55) "regularizando" uma venda já documentada
     * por uma NFC-e - CFOP 5929 (mesmo estado) ou 6929 (interestadual),
     * ver Fiscal\CfopResolver. Pedido do cliente em 2026-07-18: empresas
     * que vendem via NFC-e no balcão às vezes precisam de uma NFe formal
     * para o cliente pessoa jurídica usar na contabilidade dele.
     */
    public function importarVendaNfce(DocumentoFiscal $documentoNfce): DocumentoFiscal
    {
        if ($documentoNfce->modelo !== 65) {
            throw new \RuntimeException('Este documento não é uma NFC-e (modelo 65).');
        }

        if ($documentoNfce->status !== 'autorizada') {
            throw new \RuntimeException('Só é possível gerar NFe a partir de uma NFC-e autorizada.');
        }

        if ($documentoNfce->venda === null) {
            throw new \RuntimeException('NFC-e sem venda associada.');
        }

        return $this->emitir($documentoNfce->venda, 55, $documentoNfce->id);
    }

    /**
     * Emite uma NFe de devolução (modelo 55, CFOP 1202/2202, tpNF=0) para
     * o cliente devolvendo itens (parcial ou totalmente) de uma venda já
     * documentada por NFe ou NFC-e - ver Fiscal\CfopResolver e
     * NfePhpFiscalGateway::montarXmlNfe(). Usa a mesma sequência de
     * numeração/série de NFe da empresa (sem série dedicada).
     *
     * @param  array<int, array{item_venda_id: int, quantidade: float}>  $itensDevolucao
     */
    public function emitirDevolucao(DocumentoFiscal $documentoOrigem, array $itensDevolucao): DocumentoFiscal
    {
        if ($documentoOrigem->status !== 'autorizada') {
            throw new \RuntimeException('Só é possível devolver itens de um documento autorizado.');
        }

        if (! in_array($documentoOrigem->modelo, [55, 65], true)) {
            throw new \RuntimeException("Modelo fiscal não suportado: {$documentoOrigem->modelo}.");
        }

        if ($documentoOrigem->tipo_operacao === 'devolucao') {
            throw new \RuntimeException('Não é possível devolver um documento que já é uma devolução.');
        }

        if (empty($itensDevolucao)) {
            throw new \InvalidArgumentException('Informe ao menos um item a devolver.');
        }

        $venda = $documentoOrigem->venda;

        if ($venda === null) {
            throw new \RuntimeException('Documento sem venda associada.');
        }

        return DB::transaction(function () use ($documentoOrigem, $itensDevolucao, $venda) {
            $itensSelecionados = collect($itensDevolucao)->map(function (array $selecao) use ($venda) {
                $itemVenda = $venda->itens->firstWhere('id', $selecao['item_venda_id']);

                if ($itemVenda === null) {
                    throw new \InvalidArgumentException("Item de venda {$selecao['item_venda_id']} não pertence a esta venda.");
                }

                $quantidadeSolicitada = (float) $selecao['quantidade'];

                if ($quantidadeSolicitada <= 0) {
                    throw new \InvalidArgumentException("Quantidade a devolver do item {$selecao['item_venda_id']} deve ser maior que zero.");
                }

                $quantidadeJaDevolvida = (float) DocumentoFiscalItem::query()
                    ->where('item_venda_id', $itemVenda->id)
                    ->whereHas('documentoFiscal', fn ($query) => $query
                        ->where('tipo_operacao', 'devolucao')
                        ->whereNotIn('status', ['cancelada', 'rejeitada']))
                    ->sum('quantidade');

                $quantidadeDisponivel = (float) $itemVenda->quantidade - $quantidadeJaDevolvida;

                if ($quantidadeSolicitada > $quantidadeDisponivel) {
                    throw new \InvalidArgumentException(
                        "Quantidade a devolver do item {$itemVenda->id} ({$quantidadeSolicitada}) excede o saldo disponível ({$quantidadeDisponivel})."
                    );
                }

                return ['item_venda' => $itemVenda, 'quantidade' => $quantidadeSolicitada];
            });

            $configFiscal = ConfigFiscal::query()
                ->where('empresa_id', $venda->empresa_id)
                ->lockForUpdate()
                ->first();

            if ($configFiscal === null) {
                throw new \RuntimeException('Empresa não possui configuração fiscal cadastrada (config_fiscal).');
            }

            [$serie, $numero] = $this->proximoNumero($configFiscal, 55);

            $valorTotal = $itensSelecionados->sum(fn (array $s) => round($s['quantidade'] * (float) $s['item_venda']->valor_unitario, 2));

            $documento = DocumentoFiscal::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'documento_fiscal_origem_id' => $documentoOrigem->id,
                'tipo_operacao' => 'devolucao',
                'direcao_devolucao' => 'cliente_para_empresa',
                'modelo' => 55,
                'serie' => $serie,
                'numero' => $numero,
                'ambiente' => $configFiscal->ambiente_ativo,
                'status' => 'contingencia',
                'total' => $valorTotal,
                'valor_produtos' => $valorTotal,
            ]);

            $certificado = CertificadoDigital::query()
                ->where('empresa_id', $venda->empresa_id)
                ->first();

            $itensEmMemoria = $this->montarItensEmMemoria(
                $venda,
                $documento,
                CfopResolver::TIPO_DEVOLUCAO_CLIENTE,
                $itensSelecionados,
            );

            $resultado = $this->gateway->emitir(
                $documento,
                $itensEmMemoria,
                $venda->empresa,
                $configFiscal,
                $certificado,
            );

            $documento->update([
                'status' => $resultado->status,
                'chave_acesso' => $resultado->chaveAcesso,
                'protocolo_autorizacao' => $resultado->protocoloAutorizacao,
                'xml_path' => $this->salvarXml($venda->empresa_id, $resultado->chaveAcesso, $documento->id, $resultado->xml),
                'motivo_cancelamento' => $resultado->motivoRejeicao,
            ]);

            foreach ($itensEmMemoria as $item) {
                $item->documento_fiscal_id = $documento->id;
                $item->save();
            }

            return $documento->fresh('itens');
        });
    }

    /**
     * Emite uma NFe de devolução ao fornecedor (modelo 55, CFOP 5202/6202,
     * tpNF=1) para itens (parcial ou totalmente) de uma compra já
     * confirmada - ver Fiscal\CfopResolver e NfePhpFiscalGateway
     * (devolução empresa->fornecedor). Decrementa o estoque devolvido,
     * espelhando CompraService::confirmar() na direção inversa.
     *
     * @param  array<int, array{item_compra_id: int, quantidade: float}>  $itensDevolucao
     */
    public function emitirDevolucaoFornecedor(Compra $compra, array $itensDevolucao): DocumentoFiscal
    {
        if ($compra->status !== 'confirmada') {
            throw new \RuntimeException('Só é possível devolver itens de uma compra confirmada.');
        }

        if (empty($itensDevolucao)) {
            throw new \InvalidArgumentException('Informe ao menos um item a devolver.');
        }

        $compra->loadMissing('itens.produto', 'fornecedor');

        return DB::transaction(function () use ($compra, $itensDevolucao) {
            $itensSelecionados = collect($itensDevolucao)->map(function (array $selecao) use ($compra) {
                $itemCompra = $compra->itens->firstWhere('id', $selecao['item_compra_id']);

                if ($itemCompra === null) {
                    throw new \InvalidArgumentException("Item de compra {$selecao['item_compra_id']} não pertence a esta compra.");
                }

                $quantidadeSolicitada = (float) $selecao['quantidade'];

                if ($quantidadeSolicitada <= 0) {
                    throw new \InvalidArgumentException("Quantidade a devolver do item {$selecao['item_compra_id']} deve ser maior que zero.");
                }

                $quantidadeJaDevolvida = (float) DocumentoFiscalItem::query()
                    ->where('item_compra_id', $itemCompra->id)
                    ->whereHas('documentoFiscal', fn ($query) => $query
                        ->where('tipo_operacao', 'devolucao')
                        ->where('direcao_devolucao', 'empresa_para_fornecedor')
                        ->whereNotIn('status', ['cancelada', 'rejeitada']))
                    ->sum('quantidade');

                $quantidadeDisponivel = (float) $itemCompra->quantidade - $quantidadeJaDevolvida;

                if ($quantidadeSolicitada > $quantidadeDisponivel) {
                    throw new \InvalidArgumentException(
                        "Quantidade a devolver do item {$itemCompra->id} ({$quantidadeSolicitada}) excede o saldo disponível ({$quantidadeDisponivel})."
                    );
                }

                return ['item_compra' => $itemCompra, 'quantidade' => $quantidadeSolicitada];
            });

            $configFiscal = ConfigFiscal::query()
                ->where('empresa_id', $compra->empresa_id)
                ->lockForUpdate()
                ->first();

            if ($configFiscal === null) {
                throw new \RuntimeException('Empresa não possui configuração fiscal cadastrada (config_fiscal).');
            }

            [$serie, $numero] = $this->proximoNumero($configFiscal, 55);

            $valorTotal = $itensSelecionados->sum(fn (array $s) => round($s['quantidade'] * (float) $s['item_compra']->valor_unitario, 2));

            $documento = DocumentoFiscal::create([
                'empresa_id' => $compra->empresa_id,
                'compra_id' => $compra->id,
                'tipo_operacao' => 'devolucao',
                'direcao_devolucao' => 'empresa_para_fornecedor',
                'modelo' => 55,
                'serie' => $serie,
                'numero' => $numero,
                'ambiente' => $configFiscal->ambiente_ativo,
                'status' => 'contingencia',
                'total' => $valorTotal,
                'valor_produtos' => $valorTotal,
            ]);

            $empresa = $compra->empresa;
            $ufEmpresa = $empresa->uf;
            $ufFornecedor = $compra->fornecedor->uf ?: $ufEmpresa;

            $itensEmMemoria = $itensSelecionados->map(function (array $selecao) use ($compra, $documento, $ufEmpresa, $ufFornecedor) {
                $itemCompra = $selecao['item_compra'];
                $quantidade = $selecao['quantidade'];

                $cfop = $ufEmpresa
                    ? $this->cfopResolver->resolver($ufEmpresa, $ufFornecedor, $itemCompra->produto?->cfop_padrao, CfopResolver::TIPO_DEVOLUCAO_FORNECEDOR)
                    : null;

                return new DocumentoFiscalItem([
                    'empresa_id' => $compra->empresa_id,
                    'documento_fiscal_id' => $documento->id,
                    'item_compra_id' => $itemCompra->id,
                    'produto_id' => $itemCompra->produto_id,
                    'ncm' => $itemCompra->produto?->ncm,
                    'cfop' => $cfop,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $itemCompra->valor_unitario,
                    'valor_total' => round($quantidade * (float) $itemCompra->valor_unitario, 2),
                ]);
            })->values();

            $certificado = CertificadoDigital::query()
                ->where('empresa_id', $compra->empresa_id)
                ->first();

            $resultado = $this->gateway->emitir(
                $documento,
                $itensEmMemoria,
                $empresa,
                $configFiscal,
                $certificado,
            );

            $documento->update([
                'status' => $resultado->status,
                'chave_acesso' => $resultado->chaveAcesso,
                'protocolo_autorizacao' => $resultado->protocoloAutorizacao,
                'xml_path' => $this->salvarXml($compra->empresa_id, $resultado->chaveAcesso, $documento->id, $resultado->xml),
                'motivo_cancelamento' => $resultado->motivoRejeicao,
            ]);

            foreach ($itensEmMemoria as $item) {
                $item->documento_fiscal_id = $documento->id;
                $item->save();
            }

            if ($resultado->status === 'autorizada') {
                foreach ($itensSelecionados as $selecao) {
                    $selecao['item_compra']->produto?->decrement('estoque_atual', $selecao['quantidade']);
                }
            }

            return $documento->fresh('itens');
        });
    }

    /**
     * NFe (modelo 55) emitida direto na retaguarda, sem venda de PDV: venda
     * avulsa, remessa, transferência ou bonificação (ver OperacoesNfe). O
     * destinatário é um cliente cadastrado; o frete é rateado entre os itens.
     * Estoque só é baixado (quando a operação baixa) depois que a SEFAZ aceita.
     *
     * @param  array{
     *     tipo: string, cliente_id: int, natureza_operacao?: ?string, cfop?: ?string,
     *     itens: array<int, array{produto_id: int, variacao_id?: ?int, quantidade: float|int|string, valor_unitario?: float|int|string|null}>,
     *     frete?: float|int|string|null, modalidade_frete?: ?int, transportadora?: ?array,
     *     informacoes_adicionais?: ?string, baixar_estoque?: ?bool, forma_pagamento_id?: ?int
     * }  $dados
     */
    public function emitirNfeAvulsa(Empresa $empresa, array $dados): DocumentoFiscal
    {
        $tipo = $dados['tipo'] ?? '';
        $operacao = OperacoesNfe::TIPOS[$tipo] ?? null;

        if ($operacao === null) {
            throw new \InvalidArgumentException('Tipo de operação inválido para NFe.');
        }

        if (empty($dados['itens'])) {
            throw new \InvalidArgumentException('Informe ao menos um item na NFe.');
        }

        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $baixarEstoque = (bool) ($dados['baixar_estoque'] ?? $operacao['baixa_estoque']);

        return DB::transaction(function () use ($empresa, $dados, $tipo, $operacao, $cliente, $baixarEstoque) {
            $configFiscal = $this->configFiscalParaNfe($empresa);

            if (empty($empresa->uf) || empty($cliente->uf)) {
                throw new \RuntimeException('UF da empresa e do destinatário são obrigatórias para definir o CFOP.');
            }

            $interno = strtoupper($empresa->uf) === strtoupper($cliente->uf);

            $linhas = collect($dados['itens'])->map(function (array $item) use ($dados, $operacao, $interno) {
                $produto = Produto::findOrFail($item['produto_id']);

                if ($produto->eh_kit) {
                    throw new \InvalidArgumentException("\"{$produto->nome}\" é um kit - emita a NFe pelo pedido da loja.");
                }

                $variacao = null;
                if (! empty($item['variacao_id'])) {
                    $variacao = ProdutoVariacao::where('produto_id', $produto->id)->findOrFail($item['variacao_id']);
                } elseif ($produto->variacoes()->where('ativo', true)->exists()) {
                    throw new \InvalidArgumentException("Informe o tipo/tamanho de \"{$produto->nome}\".");
                }

                // variação vinculada: a nota e o estoque são do produto real (ex.: CERVEJA PILSEN)
                if ($variacao !== null) {
                    $produto = $variacao->produtoParaFaturar($produto);
                }

                $quantidade = round((float) $item['quantidade'], 3);
                if ($quantidade <= 0) {
                    throw new \InvalidArgumentException("Quantidade de \"{$produto->nome}\" deve ser maior que zero.");
                }

                $valorUnitario = round((float) ($item['valor_unitario'] ?? $produto->preco_venda), 2);

                $cfopBase = ! empty($dados['cfop'])
                    ? $dados['cfop']
                    : ($operacao['cfop'] === '5102' ? ($produto->cfop_padrao ?: $operacao['cfop']) : $operacao['cfop']);

                return [
                    'produto' => $produto,
                    'variacao' => $variacao,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => round($quantidade * $valorUnitario, 2),
                    'cfop' => $this->cfopResolver->ajustarPorDestino($cfopBase, $interno),
                ];
            });

            if ($baixarEstoque && ! $empresa->estoque_permite_negativo) {
                foreach ($linhas as $linha) {
                    $disponivel = $linha['variacao'] !== null ? $linha['variacao']->estoqueDisponivel() : $linha['produto']->estoque_atual;

                    if ($disponivel !== null && $disponivel < $linha['quantidade']) {
                        throw new \InvalidArgumentException("Estoque insuficiente para {$linha['produto']->nome}.");
                    }
                }
            }

            $valorProdutos = round($linhas->sum('valor_total'), 2);
            $frete = round((float) ($dados['frete'] ?? 0), 2);
            $modalidade = $dados['modalidade_frete'] ?? ($frete > 0 ? 0 : 9);

            if ($frete > 0 && (int) $modalidade === 9) {
                throw new \InvalidArgumentException('Para cobrar frete, escolha quem paga o transporte (modalidade do frete).');
            }

            $tpag = $operacao['tpag'];
            if ($tpag === null && ! empty($dados['forma_pagamento_id'])) {
                $tpag = \App\Models\FormaPagamento::find($dados['forma_pagamento_id'])?->codigo_tpag;
            }

            [$serie, $numero] = $this->proximoNumero($configFiscal, 55);

            $documento = DocumentoFiscal::create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente->id,
                'tipo_operacao' => $tipo,
                'modelo' => 55,
                'serie' => $serie,
                'numero' => $numero,
                'ambiente' => $configFiscal->ambiente_ativo,
                'status' => 'contingencia',
                'natureza_operacao' => mb_substr($dados['natureza_operacao'] ?? $operacao['natureza'], 0, 60),
                'cfop_geral' => $dados['cfop'] ?? null,
                'valor_produtos' => $valorProdutos,
                'frete' => $frete,
                'total' => round($valorProdutos + $frete, 2),
                'modalidade_frete' => (int) $modalidade,
                'transportadora' => $dados['transportadora'] ?? null,
                'informacoes_adicionais' => $dados['informacoes_adicionais'] ?? null,
                'indicador_presenca' => $operacao['ind_pres'],
                'tpag' => $tpag,
            ]);

            $fretes = $this->ratearFrete($linhas->pluck('valor_total')->all(), $frete);

            $itensEmMemoria = $linhas->values()->map(fn (array $linha, int $i) => new DocumentoFiscalItem([
                'empresa_id' => $empresa->id,
                'documento_fiscal_id' => $documento->id,
                'produto_id' => $linha['produto']->id,
                'variacao_id' => $linha['variacao']?->id,
                'descricao' => $this->descricaoItem($linha['produto'], $linha['variacao']),
                'ncm' => $linha['produto']->ncm,
                'cfop' => $linha['cfop'],
                'quantidade' => $linha['quantidade'],
                'valor_unitario' => $linha['valor_unitario'],
                'valor_total' => $linha['valor_total'],
                'valor_frete' => $fretes[$i],
            ]));

            $this->enviarEPersistir($documento, $itensEmMemoria, $empresa, $configFiscal);

            if (! in_array($documento->status, ['rejeitada', 'denegada'], true) && $baixarEstoque) {
                foreach ($linhas as $linha) {
                    if ($linha['variacao'] !== null) {
                        // a SEFAZ já aceitou a nota: baixa sem abortar por saldo
                        $linha['variacao']->baixar((int) ceil($linha['quantidade']), $linha['produto']->nome, true);
                    } elseif ($linha['produto']->estoque_atual !== null) {
                        $linha['produto']->decrement('estoque_atual', $linha['quantidade']);
                    }
                }
            }

            return $documento->fresh(['itens', 'cliente']);
        });
    }

    /**
     * NFe (modelo 55) de um pedido da loja virtual já pago: só os produtos
     * (visita agendada não entra), com o frete cobrado no pedido, o endereço
     * de entrega daquele pedido e a forma de pagamento usada. Estoque já foi
     * baixado no checkout - aqui não mexe nele.
     */
    public function emitirNfePedidoLoja(Venda $venda): DocumentoFiscal
    {
        if ($venda->canal !== 'site') {
            throw new \RuntimeException('Esta ação vale só para pedidos da loja virtual.');
        }

        if ($venda->status_pagamento !== 'pago') {
            throw new \RuntimeException('Só é possível emitir a NFe de um pedido já pago.');
        }

        if ($venda->cliente === null) {
            throw new \RuntimeException('O pedido não tem cliente identificado.');
        }

        $venda->loadMissing(['itens.produto', 'itens.produtoVariacao', 'empresa']);
        $itensProduto = $venda->itens->filter(fn ($item) => $item->produto_id !== null)->values();

        if ($itensProduto->isEmpty()) {
            throw new \RuntimeException('O pedido não tem produtos para faturar.');
        }

        if (DocumentoFiscal::where('venda_id', $venda->id)->where('modelo', 55)->where('tipo_operacao', 'venda')->where('status', 'autorizada')->exists()) {
            throw new \RuntimeException('Este pedido já possui NFe autorizada.');
        }

        $empresa = $venda->empresa;

        return DB::transaction(function () use ($venda, $itensProduto, $empresa) {
            $configFiscal = $this->configFiscalParaNfe($empresa);

            $entrega = $venda->tipo_entrega === 'entrega';
            $uf = $entrega ? ($venda->endereco_entrega['uf'] ?? null) : $venda->cliente->uf;

            if (empty($empresa->uf) || empty($uf)) {
                throw new \RuntimeException('UF da empresa e do destinatário são obrigatórias para definir o CFOP.');
            }

            $interno = strtoupper($empresa->uf) === strtoupper($uf);
            $frete = $entrega ? round((float) $venda->valor_frete, 2) : 0.0;
            $valorProdutos = round((float) $itensProduto->sum('valor_total'), 2);

            $metodo = Cobranca::where('venda_id', $venda->id)->latest('id')->value('metodo');
            $tpag = match ($metodo) {
                'pix' => '17',
                'cartao_credito' => '03',
                'cartao_debito' => '04',
                default => null,
            };

            [$serie, $numero] = $this->proximoNumero($configFiscal, 55);

            $documento = DocumentoFiscal::create([
                'empresa_id' => $empresa->id,
                'venda_id' => $venda->id,
                'cliente_id' => $venda->cliente_id,
                'tipo_operacao' => 'venda',
                'modelo' => 55,
                'serie' => $serie,
                'numero' => $numero,
                'ambiente' => $configFiscal->ambiente_ativo,
                'status' => 'contingencia',
                'natureza_operacao' => 'Venda de mercadoria',
                'valor_produtos' => $valorProdutos,
                'frete' => $frete,
                'total' => round($valorProdutos + $frete, 2),
                // Frete cobrado do cliente e contratado pela loja = por conta do remetente;
                // na retirada o destinatário leva a mercadoria.
                'modalidade_frete' => $entrega ? 0 : 1,
                'informacoes_adicionais' => "Pedido #{$venda->id} da loja virtual.",
                'indicador_presenca' => 2,
                'tpag' => $tpag,
            ]);

            // Kit vira uma linha por componente (caneca + cada cerveja escolhida),
            // com o valor do kit rateado entre eles.
            $linhas = $itensProduto->flatMap(fn ($item) => $this->linhasDoItemDoPedido($item))->values();
            $fretes = $this->ratearFrete($linhas->pluck('valor_total')->map(fn ($v) => (float) $v)->all(), $frete);

            $itensEmMemoria = $linhas->map(fn (array $linha, int $i) => new DocumentoFiscalItem([
                'empresa_id' => $empresa->id,
                'documento_fiscal_id' => $documento->id,
                'item_venda_id' => $linha['item_venda_id'],
                'produto_id' => $linha['produto']?->id,
                'variacao_id' => $linha['variacao_id'],
                'descricao' => $linha['descricao'],
                'ncm' => $linha['produto']?->ncm,
                'cfop' => $this->cfopResolver->ajustarPorDestino($linha['produto']?->cfop_padrao ?: '5102', $interno),
                'quantidade' => $linha['quantidade'],
                'valor_unitario' => $linha['valor_unitario'],
                'valor_total' => $linha['valor_total'],
                'valor_frete' => $fretes[$i],
            ]));

            $this->enviarEPersistir($documento, $itensEmMemoria, $empresa, $configFiscal);

            if ($documento->status === 'autorizada') {
                $venda->update(['tipo_doc' => 'fiscal']);
            }

            return $documento->fresh(['itens', 'cliente']);
        });
    }

    /**
     * Linhas de NFe de um item do pedido. Item comum: uma linha. Kit: uma linha
     * por componente gravado em `composicao`, com o valor do kit rateado na
     * proporção do preço de tabela de cada componente (sem preço, na proporção
     * das quantidades); a soma das linhas fecha exatamente com o valor do kit.
     *
     * @return array<int, array{item_venda_id: int, produto: ?Produto, variacao_id: ?int, descricao: string, quantidade: float, valor_unitario: float, valor_total: float}>
     */
    private function linhasDoItemDoPedido(\App\Models\ItemVenda $item): array
    {
        if (empty($item->composicao)) {
            return [[
                'item_venda_id' => $item->id,
                'produto' => $item->produto,
                'variacao_id' => $item->produto_variacao_id,
                'descricao' => $this->descricaoItem($item->produto, $item->produtoVariacao),
                'quantidade' => (float) $item->quantidade,
                'valor_unitario' => (float) $item->valor_unitario,
                'valor_total' => (float) $item->valor_total,
            ]];
        }

        $componentes = collect($item->composicao);
        $produtos = Produto::whereIn('id', $componentes->pluck('produto_id'))->get()->keyBy('id');

        $pesos = $componentes->map(fn (array $c) => (float) ($produtos[$c['produto_id']]->preco_venda ?? 0) * $c['quantidade'])->all();

        if (array_sum($pesos) <= 0) {
            $pesos = $componentes->map(fn (array $c) => (float) $c['quantidade'])->all();
        }

        $totais = $this->ratearFrete($pesos, round((float) $item->valor_total, 2));

        return $componentes->values()->map(function (array $c, int $i) use ($item, $produtos, $totais) {
            $quantidade = (float) $c['quantidade'];
            $nome = $c['nome'];

            return [
                'item_venda_id' => $item->id,
                'produto' => $produtos[$c['produto_id']] ?? null,
                'variacao_id' => $c['variacao_id'] ?? null,
                'descricao' => ! empty($c['tamanho']) ? "{$nome} ({$c['tamanho']})" : $nome,
                'quantidade' => $quantidade,
                'valor_unitario' => $quantidade > 0 ? round($totais[$i] / $quantidade, 2) : 0.0,
                'valor_total' => $totais[$i],
            ];
        })->all();
    }

    private function configFiscalParaNfe(Empresa $empresa): ConfigFiscal
    {
        $configFiscal = ConfigFiscal::query()->where('empresa_id', $empresa->id)->lockForUpdate()->first();

        if ($configFiscal === null) {
            throw new \RuntimeException('Empresa não possui configuração fiscal cadastrada (config_fiscal).');
        }

        // O gerador de impostos da NFe cobre só o Simples Nacional (CSOSN);
        // emitir em outro regime geraria nota com tributação errada.
        if (! in_array((string) $configFiscal->crt, OperacoesNfe::CRT_SUPORTADOS, true)) {
            throw new \RuntimeException('A emissão de NFe por aqui está disponível apenas para empresas do Simples Nacional (CRT 1 ou 2).');
        }

        return $configFiscal;
    }

    /**
     * Manda o documento ao gateway e grava o retorno (status, chave, protocolo,
     * XML) e os itens - mesmo passo final de todas as emissões.
     *
     * @param  Collection<int, DocumentoFiscalItem>  $itensEmMemoria
     */
    private function enviarEPersistir(DocumentoFiscal $documento, Collection $itensEmMemoria, Empresa $empresa, ConfigFiscal $configFiscal): void
    {
        $certificado = CertificadoDigital::query()->where('empresa_id', $empresa->id)->first();

        $resultado = $this->gateway->emitir($documento, $itensEmMemoria, $empresa, $configFiscal, $certificado);

        $documento->update([
            'status' => $resultado->status,
            'chave_acesso' => $resultado->chaveAcesso,
            'protocolo_autorizacao' => $resultado->protocoloAutorizacao,
            'xml_path' => $this->salvarXml($empresa->id, $resultado->chaveAcesso, $documento->id, $resultado->xml),
            'motivo_cancelamento' => $resultado->motivoRejeicao,
        ]);

        foreach ($itensEmMemoria as $item) {
            $item->documento_fiscal_id = $documento->id;
            $item->save();
        }
    }

    private function descricaoItem(?Produto $produto, ?ProdutoVariacao $variacao): string
    {
        $nome = $produto?->nome ?? 'Item';

        // variação vinculada já tem o nome do produto real: não repete o tipo
        return ($variacao && $variacao->produto_vinculado_id === null) ? "{$nome} ({$variacao->tamanho})" : $nome;
    }

    /**
     * Divide o frete entre os itens na proporção do valor de cada um; o último
     * item absorve a diferença de arredondamento para a soma fechar com o frete.
     *
     * @param  array<int, float>  $valoresItens
     * @return array<int, float>
     */
    private function ratearFrete(array $valoresItens, float $frete): array
    {
        $total = array_sum($valoresItens);
        $partes = [];
        $acumulado = 0.0;
        $ultimo = count($valoresItens) - 1;

        foreach (array_values($valoresItens) as $i => $valor) {
            $parte = ($i === $ultimo || $total <= 0)
                ? round($frete - $acumulado, 2)
                : round($frete * $valor / $total, 2);

            if ($total <= 0 && $i !== $ultimo) {
                $parte = 0.0;
            }

            $partes[$i] = $parte;
            $acumulado += $parte;
        }

        return $partes;
    }

    public function cancelar(DocumentoFiscal $documento, string $justificativa): DocumentoFiscal
    {
        if (mb_strlen($justificativa) < 15) {
            throw new \InvalidArgumentException('Justificativa do cancelamento deve ter ao menos 15 caracteres.');
        }

        if ($documento->status !== 'autorizada') {
            throw new \RuntimeException('Só é possível cancelar um documento autorizado.');
        }

        return DB::transaction(function () use ($documento, $justificativa) {
            $configFiscal = ConfigFiscal::where('empresa_id', $documento->empresa_id)->firstOrFail();
            $certificado = CertificadoDigital::where('empresa_id', $documento->empresa_id)->first();

            $resultado = $this->gateway->cancelar(
                $documento,
                $justificativa,
                $documento->empresa,
                $configFiscal,
                $certificado,
            );

            if ($resultado->status !== 'homologada') {
                throw new \RuntimeException("Cancelamento rejeitado pela SEFAZ: {$resultado->motivo}");
            }

            $documento->update([
                'status' => 'cancelada',
                'motivo_cancelamento' => $justificativa,
                'data_cancelamento' => now(),
            ]);

            return $documento->fresh();
        });
    }

    public function inutilizar(
        \App\Models\Empresa $empresa,
        int $modelo,
        string $serie,
        int $numeroInicial,
        int $numeroFinal,
        string $justificativa,
    ): NumeracaoInutilizada {
        if (mb_strlen($justificativa) < 15) {
            throw new \InvalidArgumentException('Justificativa da inutilização deve ter ao menos 15 caracteres.');
        }

        if ($numeroInicial > $numeroFinal) {
            throw new \InvalidArgumentException('Número inicial não pode ser maior que o final.');
        }

        return DB::transaction(function () use ($empresa, $modelo, $serie, $numeroInicial, $numeroFinal, $justificativa) {
            $configFiscal = ConfigFiscal::where('empresa_id', $empresa->id)->firstOrFail();
            $certificado = CertificadoDigital::where('empresa_id', $empresa->id)->first();

            $resultado = $this->gateway->inutilizar(
                $empresa,
                $configFiscal,
                $certificado,
                $modelo,
                $serie,
                $numeroInicial,
                $numeroFinal,
                $justificativa,
            );

            return NumeracaoInutilizada::create([
                'empresa_id' => $empresa->id,
                'modelo' => $modelo,
                'serie' => $serie,
                'numero_inicial' => $numeroInicial,
                'numero_final' => $numeroFinal,
                'justificativa' => $justificativa,
                'status' => $resultado->status,
                'protocolo' => $resultado->protocolo,
                'motivo' => $resultado->motivo,
            ]);
        });
    }

    /**
     * @return array{0: string, 1: int} [serie, numero]
     */
    private function proximoNumero(ConfigFiscal $configFiscal, int $modelo): array
    {
        if ($modelo === 55) {
            $numero = $configFiscal->numero_nfe_atual + 1;
            $configFiscal->update(['numero_nfe_atual' => $numero]);

            return [$configFiscal->serie_nfe_atual, $numero];
        }

        $numero = $configFiscal->numero_nfce_atual + 1;
        $configFiscal->update(['numero_nfce_atual' => $numero]);

        return [$configFiscal->serie_nfce_atual, $numero];
    }

    /**
     * @param  Collection<int, array{item_venda: \App\Models\ItemVenda, quantidade: float}>|null  $itensSelecionados  quando null, usa todos os itens da venda com a quantidade cheia (venda/regularização)
     * @return Collection<int, DocumentoFiscalItem>
     */
    private function montarItensEmMemoria(
        Venda $venda,
        DocumentoFiscal $documento,
        string $tipoOperacao,
        ?Collection $itensSelecionados = null,
    ): Collection {
        $ufEmpresa = $venda->empresa->uf;
        $ufCliente = $venda->cliente?->uf ?: $ufEmpresa;

        $itens = $itensSelecionados ?? $venda->itens->map(fn ($itemVenda) => [
            'item_venda' => $itemVenda,
            'quantidade' => $itemVenda->quantidade,
        ]);

        return $itens->flatMap(function (array $selecao) use ($venda, $documento, $ufEmpresa, $ufCliente, $tipoOperacao, $itensSelecionados) {
            $itemVenda = $selecao['item_venda'];
            $quantidade = $selecao['quantidade'];

            // Kit (venda completa): uma linha por componente, com o valor do kit rateado.
            if ($itensSelecionados === null && ! empty($itemVenda->composicao)) {
                return collect($this->linhasDoItemDoPedido($itemVenda))->map(fn (array $linha) => new DocumentoFiscalItem([
                    'empresa_id' => $venda->empresa_id,
                    'documento_fiscal_id' => $documento->id,
                    'item_venda_id' => $linha['item_venda_id'],
                    'produto_id' => $linha['produto']?->id,
                    'variacao_id' => $linha['variacao_id'],
                    'descricao' => $linha['descricao'],
                    'ncm' => $linha['produto']?->ncm,
                    'cfop' => $ufEmpresa
                        ? $this->cfopResolver->resolver($ufEmpresa, $ufCliente, $linha['produto']?->cfop_padrao, $tipoOperacao)
                        : null,
                    'quantidade' => $linha['quantidade'],
                    'valor_unitario' => $linha['valor_unitario'],
                    'valor_total' => $linha['valor_total'],
                ]))->all();
            }

            $cfop = $ufEmpresa
                ? $this->cfopResolver->resolver($ufEmpresa, $ufCliente, $itemVenda->produto?->cfop_padrao, $tipoOperacao)
                : null;

            return [new DocumentoFiscalItem([
                'empresa_id' => $venda->empresa_id,
                'documento_fiscal_id' => $documento->id,
                'item_venda_id' => $itemVenda->id,
                'produto_id' => $itemVenda->produto_id,
                'ncm' => $itemVenda->produto?->ncm,
                'cfop' => $cfop,
                'quantidade' => $quantidade,
                'valor_unitario' => $itemVenda->valor_unitario,
                'valor_total' => round($quantidade * (float) $itemVenda->valor_unitario, 2),
            ])];
        })->values();
    }

    /**
     * Grava o XML retornado pelo gateway em disco e devolve o CAMINHO do
     * arquivo (não o conteúdo) - é isso que a coluna xml_path guarda.
     */
    private function salvarXml(int $empresaId, ?string $chaveAcesso, int $documentoId, ?string $xml): ?string
    {
        if ($xml === null) {
            return null;
        }

        $nomeArquivo = ($chaveAcesso ?: "documento-{$documentoId}").'.xml';
        $caminhoRelativo = "fiscal/xmls/{$empresaId}/{$nomeArquivo}";

        Storage::put($caminhoRelativo, $xml);

        return Storage::path($caminhoRelativo);
    }
}
