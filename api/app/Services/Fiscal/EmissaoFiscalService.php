<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use App\Models\Compra;
use App\Models\ConfigFiscal;
use App\Models\DocumentoFiscal;
use App\Models\DocumentoFiscalItem;
use App\Models\NumeracaoInutilizada;
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

        return $itens->map(function (array $selecao) use ($venda, $documento, $ufEmpresa, $ufCliente, $tipoOperacao) {
            $itemVenda = $selecao['item_venda'];
            $quantidade = $selecao['quantidade'];

            $cfop = $ufEmpresa
                ? $this->cfopResolver->resolver($ufEmpresa, $ufCliente, $itemVenda->produto?->cfop_padrao, $tipoOperacao)
                : null;

            return new DocumentoFiscalItem([
                'empresa_id' => $venda->empresa_id,
                'documento_fiscal_id' => $documento->id,
                'item_venda_id' => $itemVenda->id,
                'produto_id' => $itemVenda->produto_id,
                'ncm' => $itemVenda->produto?->ncm,
                'cfop' => $cfop,
                'quantidade' => $quantidade,
                'valor_unitario' => $itemVenda->valor_unitario,
                'valor_total' => round($quantidade * (float) $itemVenda->valor_unitario, 2),
            ]);
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
