<?php

namespace App\Services\Compras;

use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\ItemCompra;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquestra a entrada de mercadoria (compra): registro manual ou via
 * importação de XML de NFe, baixa reversa de estoque (incremento) e
 * geração automática do lançamento em Contas a Pagar ao confirmar -
 * mesmo padrão do VendaPdvService, na direção inversa.
 */
class CompraService
{
    public function registrarManual(Empresa $empresa, array $dados): Compra
    {
        return DB::transaction(function () use ($empresa, $dados) {
            $compra = Compra::create([
                'empresa_id' => $empresa->id,
                'fornecedor_id' => $dados['fornecedor_id'],
                'numero_nota' => $dados['numero_nota'] ?? null,
                'serie_nota' => $dados['serie_nota'] ?? null,
                'tipo_entrada' => 'manual',
                'data_emissao' => $dados['data_emissao'] ?? null,
                'data_entrada' => $dados['data_entrada'] ?? now()->toDateString(),
                'valor_frete' => $dados['valor_frete'] ?? 0,
                'valor_desconto' => $dados['valor_desconto'] ?? 0,
                'status' => 'pendente',
            ]);

            $valorProdutos = 0;

            foreach ($dados['itens'] ?? [] as $item) {
                $produto = Produto::findOrFail($item['produto_id']);
                $quantidade = (float) $item['quantidade'];
                $valorUnitario = (float) $item['valor_unitario'];
                $valorItem = round($quantidade * $valorUnitario, 2);
                $valorProdutos += $valorItem;

                $compra->itens()->create([
                    'empresa_id' => $empresa->id,
                    'produto_id' => $produto->id,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $valorItem,
                ]);
            }

            $this->atualizarTotais($compra, $valorProdutos);

            return $compra->fresh('itens.produto');
        });
    }

    public function importarXml(Empresa $empresa, string $xmlConteudo): Compra
    {
        $xml = @simplexml_load_string($xmlConteudo);

        if ($xml === false) {
            throw ValidationException::withMessages(['xml' => 'Arquivo XML inválido.']);
        }

        $nfe = $xml->NFe ?? $xml;
        $infNFe = $nfe->infNFe ?? null;

        if ($infNFe === null) {
            throw ValidationException::withMessages(['xml' => 'XML não é uma NFe válida.']);
        }

        $chaveAcesso = ltrim((string) ($infNFe['Id'] ?? ''), 'NFe');

        if ($chaveAcesso !== '' && Compra::where('chave_acesso', $chaveAcesso)->exists()) {
            throw ValidationException::withMessages(['xml' => 'Esta nota já foi importada anteriormente.']);
        }

        $emit = $infNFe->emit;
        $cnpjEmitente = preg_replace('/\D/', '', (string) $emit->CNPJ);

        $fornecedor = Fornecedor::where('empresa_id', $empresa->id)
            ->whereRaw("regexp_replace(cnpj, '\\D', '', 'g') = ?", [$cnpjEmitente])
            ->first();

        if (! $fornecedor) {
            throw ValidationException::withMessages([
                'fornecedor' => "Fornecedor com CNPJ {$cnpjEmitente} não cadastrado. Cadastre o fornecedor antes de importar a nota.",
            ]);
        }

        $ide = $infNFe->ide;
        $total = (float) $infNFe->total->ICMSTot->vNF;

        return DB::transaction(function () use ($empresa, $fornecedor, $infNFe, $ide, $chaveAcesso, $total, $xmlConteudo) {
            $compra = Compra::create([
                'empresa_id' => $empresa->id,
                'fornecedor_id' => $fornecedor->id,
                'numero_nota' => (string) $ide->nNF,
                'serie_nota' => (string) $ide->serie,
                'chave_acesso' => $chaveAcesso !== '' ? $chaveAcesso : null,
                'tipo_entrada' => 'xml',
                'data_emissao' => $this->parseDataNfe((string) $ide->dhEmi),
                'data_entrada' => now()->toDateString(),
                'valor_total' => $total,
                'status' => 'pendente',
                'xml_conteudo' => $xmlConteudo,
            ]);

            $valorProdutos = 0;

            foreach ($infNFe->det as $det) {
                $prod = $det->prod;
                $ean = (string) $prod->cEAN;
                $quantidade = (float) $prod->qCom;
                $valorUnitario = (float) $prod->vUnCom;
                $valorItem = (float) $prod->vProd;
                $valorProdutos += $valorItem;

                $produto = null;
                if ($ean !== '' && $ean !== 'SEM GTIN') {
                    $produto = Produto::where('empresa_id', $empresa->id)
                        ->where('codigo_barras', $ean)
                        ->first();
                }

                $compra->itens()->create([
                    'empresa_id' => $empresa->id,
                    'produto_id' => $produto?->id,
                    'codigo_produto_fornecedor' => (string) $prod->cProd,
                    'descricao_xml' => (string) $prod->xProd,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $valorItem,
                    'ncm' => (string) $prod->NCM,
                    'cfop' => (string) $prod->CFOP,
                ]);
            }

            $compra->update(['valor_produtos' => $valorProdutos]);

            return $compra->fresh('itens.produto');
        });
    }

    public function vincularItem(ItemCompra $item, ?int $produtoId, ?array $dadosNovoProduto): ItemCompra
    {
        if ($produtoId) {
            $produto = Produto::where('empresa_id', $item->empresa_id)->findOrFail($produtoId);
        } elseif ($dadosNovoProduto) {
            $produto = Produto::create([
                'empresa_id' => $item->empresa_id,
                'fornecedor_id' => $item->compra->fornecedor_id,
                'nome' => $dadosNovoProduto['nome'] ?? $item->descricao_xml,
                'tipo' => 'fisico',
                'codigo_barras' => $dadosNovoProduto['codigo_barras'] ?? null,
                'unidade' => $dadosNovoProduto['unidade'] ?? 'UN',
                'ncm' => $item->ncm,
                'preco_custo' => $item->valor_unitario,
                'preco_venda' => $dadosNovoProduto['preco_venda'] ?? $item->valor_unitario,
                'estoque_atual' => 0,
                'ativo' => true,
            ]);
        } else {
            throw ValidationException::withMessages(['produto' => 'Informe um produto existente ou os dados para cadastrar um novo.']);
        }

        $item->update(['produto_id' => $produto->id]);

        return $item->fresh('produto');
    }

    public function confirmar(Compra $compra, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($compra) {
            $compra->loadMissing('itens');

            if ($compra->status !== 'pendente') {
                throw ValidationException::withMessages(['status' => 'Esta compra já foi confirmada ou cancelada.']);
            }

            foreach ($compra->itens as $item) {
                if (! $item->produto_id) {
                    throw ValidationException::withMessages(['itens' => "O item \"{$item->descricao_xml}\" ainda não está vinculado a um produto."]);
                }
            }

            foreach ($compra->itens as $item) {
                $produto = $item->produto;
                $produto->increment('estoque_atual', $item->quantidade);
                $produto->update(['preco_custo' => $item->valor_unitario]);
            }

            $compra->update(['status' => 'confirmada']);

            ContaPagar::create([
                'empresa_id' => $compra->empresa_id,
                'fornecedor_id' => $compra->fornecedor_id,
                'historico' => "Compra #{$compra->id}" . ($compra->numero_nota ? " - NF {$compra->numero_nota}" : ''),
                'valor' => $compra->valor_total,
                'vencimento' => $compra->data_entrada,
                'status' => 'em_aberto',
            ]);

            return $compra->fresh(['itens.produto', 'fornecedor']);
        });
    }

    public function cancelar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra) {
            if ($compra->status === 'cancelada') {
                throw ValidationException::withMessages(['status' => 'Esta compra já está cancelada.']);
            }

            if ($compra->status === 'confirmada') {
                $compra->loadMissing('itens.produto');
                foreach ($compra->itens as $item) {
                    $item->produto?->decrement('estoque_atual', $item->quantidade);
                }
            }

            $compra->update(['status' => 'cancelada']);

            return $compra->fresh('itens.produto');
        });
    }

    private function atualizarTotais(Compra $compra, float $valorProdutos): void
    {
        $valorTotal = $valorProdutos + (float) $compra->valor_frete - (float) $compra->valor_desconto;

        $compra->update([
            'valor_produtos' => $valorProdutos,
            'valor_total' => $valorTotal,
        ]);
    }

    private function parseDataNfe(string $dhEmi): ?string
    {
        if ($dhEmi === '') {
            return null;
        }

        return substr($dhEmi, 0, 10);
    }
}
