<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\ItemCompra;
use App\Services\Compras\CompraService;
use Illuminate\Http\Request;

class ComprasController extends Controller
{
    public function __construct(private readonly CompraService $compraService) {}

    public function index(Request $request, string $empresa)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        return response()->json(
            Compra::where('empresa_id', $empresaAtual->id)
                ->with('fornecedor')
                ->orderByDesc('data_entrada')
                ->get()
        );
    }

    public function show(Request $request, string $empresa, int $compraId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $compra = Compra::where('empresa_id', $empresaAtual->id)
            ->with(['itens.produto', 'fornecedor'])
            ->findOrFail($compraId);

        return response()->json($compra);
    }

    public function store(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'fornecedor_id' => ['required', 'integer'],
            'numero_nota' => ['nullable', 'string', 'max:255'],
            'serie_nota' => ['nullable', 'string', 'max:255'],
            'data_emissao' => ['nullable', 'date'],
            'data_entrada' => ['nullable', 'date'],
            'valor_frete' => ['nullable', 'numeric', 'min:0'],
            'valor_desconto' => ['nullable', 'numeric', 'min:0'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.001'],
            'itens.*.valor_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $compra = $this->compraService->registrarManual($empresaAtual, $dados);

        return response()->json($compra, 201);
    }

    public function importarXml(Request $request, string $empresa)
    {
        $request->validate(['xml' => ['required', 'file', 'mimes:xml']]);

        $empresaAtual = $request->attributes->get('empresaAtual');
        $conteudo = file_get_contents($request->file('xml')->getRealPath());

        $compra = $this->compraService->importarXml($empresaAtual, $conteudo);

        return response()->json($compra, 201);
    }

    public function vincularItem(Request $request, string $empresa, int $compraId, int $itemId)
    {
        $dados = $request->validate([
            'produto_id' => ['nullable', 'integer'],
            'novo_produto' => ['nullable', 'array'],
            'novo_produto.nome' => ['required_with:novo_produto', 'string', 'max:255'],
            'novo_produto.codigo_barras' => ['nullable', 'string', 'max:255'],
            'novo_produto.unidade' => ['nullable', 'string', 'max:50'],
            'novo_produto.preco_venda' => ['nullable', 'numeric', 'min:0'],
        ]);

        $empresaAtual = $request->attributes->get('empresaAtual');

        $item = ItemCompra::where('empresa_id', $empresaAtual->id)
            ->where('compra_id', $compraId)
            ->findOrFail($itemId);

        $item = $this->compraService->vincularItem($item, $dados['produto_id'] ?? null, $dados['novo_produto'] ?? null);

        return response()->json($item);
    }

    public function confirmar(Request $request, string $empresa, int $compraId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $compra = Compra::where('empresa_id', $empresaAtual->id)->findOrFail($compraId);

        $compra = $this->compraService->confirmar($compra, $request->user()->id);

        return response()->json($compra);
    }

    public function cancelar(Request $request, string $empresa, int $compraId)
    {
        $empresaAtual = $request->attributes->get('empresaAtual');

        $compra = Compra::where('empresa_id', $empresaAtual->id)->findOrFail($compraId);

        $compra = $this->compraService->cancelar($compra);

        return response()->json($compra);
    }
}
