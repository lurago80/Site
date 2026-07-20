<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Cfop;
use App\Models\ConfigAssinatura;
use App\Models\Empresa;
use App\Models\IbptProduto;
use App\Models\Plano;
use App\Services\Assinatura\AssinaturaService;
use App\Services\Fiscal\ImportadorCfopService;
use App\Services\Ibpt\ImportadorIbptService;
use Illuminate\Http\Request;

/**
 * Painel Super Admin (Escopo v2, seção 2.2): gestão de empresas, planos
 * e faturamento da assinatura - uso interno da equipe da plataforma,
 * não das empresas clientes.
 */
class SuperAdminController extends Controller
{
    public function __construct(
        private readonly AssinaturaService $assinaturaService,
        private readonly ImportadorIbptService $importadorIbptService,
        private readonly ImportadorCfopService $importadorCfopService,
    ) {}

    public function painel()
    {
        return view('superadmin.painel');
    }

    public function empresas()
    {
        return response()->json(
            Empresa::with(['plano', 'assinaturas' => fn ($q) => $q->latest()->limit(1)])
                ->orderBy('razao_social')
                ->get()
        );
    }

    public function criarEmpresa(Request $request)
    {
        $dados = $request->validate([
            'razao_social' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:18', 'unique:empresas,cnpj'],
            'slug' => ['required', 'string', 'max:255', 'unique:empresas,slug', 'regex:/^[a-z0-9-]+$/'],
            'segmento' => ['nullable', 'string', 'max:255'],
            'modulo_agendamento_ativo' => ['boolean'],
            'plano_id' => ['required', 'integer', 'exists:planos,id'],
        ]);

        $empresa = Empresa::create($dados + ['status' => 'ativa']);

        return response()->json($empresa, 201);
    }

    public function atualizarEmpresa(Request $request, int $empresaId)
    {
        $empresa = Empresa::findOrFail($empresaId);

        $dados = $request->validate([
            'razao_social' => ['sometimes', 'string', 'max:255'],
            'segmento' => ['nullable', 'string', 'max:255'],
            'modulo_agendamento_ativo' => ['sometimes', 'boolean'],
            'plano_id' => ['sometimes', 'integer', 'exists:planos,id'],
            'status' => ['sometimes', 'in:ativa,suspensa,cancelada'],
            'uf' => ['nullable', 'string', 'max:2'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'codigo_ibge_municipio' => ['nullable', 'string', 'max:7'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'cor_primaria' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $empresa->update($dados);

        return response()->json($empresa->fresh());
    }

    public function planos()
    {
        return response()->json(Plano::orderBy('valor_mensal')->get());
    }

    public function criarPlano(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'valor_mensal' => ['required', 'numeric', 'min:0'],
            'limites' => ['nullable', 'array'],
        ]);

        return response()->json(Plano::create($dados), 201);
    }

    public function atualizarPlano(Request $request, int $planoId)
    {
        $plano = Plano::findOrFail($planoId);

        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'valor_mensal' => ['sometimes', 'numeric', 'min:0'],
            'limites' => ['nullable', 'array'],
        ]);

        $plano->update($dados);

        return response()->json($plano->fresh());
    }

    public function assinaturas()
    {
        return response()->json(
            Assinatura::with(['empresa', 'plano'])->orderByDesc('created_at')->get()
        );
    }

    /**
     * Cria a assinatura da empresa cliente. Com o Asaas configurado e
     * ativo (ver configAssinatura()), cria a cobrança recorrente de
     * verdade lá e a empresa passa a ser cobrada automaticamente todo
     * mês; sem Asaas, cai no cadastro manual original (status digitado
     * à mão pelo super admin).
     */
    public function criarAssinatura(Request $request)
    {
        $dados = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'plano_id' => ['required', 'integer', 'exists:planos,id'],
            'status_pagamento' => ['required', 'in:em_dia,atrasado,cancelado'],
            'inicio' => ['required', 'date'],
        ]);

        $empresa = Empresa::findOrFail($dados['empresa_id']);
        $plano = Plano::findOrFail($dados['plano_id']);

        $assinatura = $this->assinaturaService->criarAssinatura(
            $empresa, $plano, $dados['status_pagamento'], $dados['inicio']
        );

        return response()->json($assinatura->load('empresa', 'plano'), 201);
    }

    /**
     * Baixa manual: marca a assinatura como paga "na mão" (transferência,
     * dinheiro, negociação fora do Asaas etc.), independente de haver
     * gateway configurado. O super admin sempre pode fazer isso - o
     * Asaas automatiza o caso comum, mas nunca é a única porta de saída.
     */
    public function baixarAssinaturaManual(Request $request, int $assinaturaId)
    {
        $dados = $request->validate([
            'proxima_cobranca' => ['nullable', 'date'],
        ]);

        $assinatura = Assinatura::findOrFail($assinaturaId);
        $assinatura->update([
            'status_pagamento' => 'em_dia',
            'proxima_cobranca' => $dados['proxima_cobranca'] ?? now()->addMonth()->toDateString(),
        ]);

        // Reativa a empresa se ela tinha sido suspensa por inadimplência.
        if ($assinatura->empresa->status === 'suspensa') {
            $assinatura->empresa->update(['status' => 'ativa']);
        }

        return response()->json($assinatura->fresh()->load('empresa', 'plano'));
    }

    public function atualizarStatusAssinatura(Request $request, int $assinaturaId)
    {
        $dados = $request->validate([
            'status_pagamento' => ['required', 'in:em_dia,atrasado,cancelado'],
        ]);

        $assinatura = Assinatura::findOrFail($assinaturaId);
        $assinatura->update($dados);

        return response()->json($assinatura->fresh()->load('empresa', 'plano'));
    }

    // ---- Configuração de cobrança de assinatura (Asaas) ----

    /**
     * Configuração ÚNICA e global (não por empresa) - só o super admin
     * gerencia, é a plataforma cobrando cada empresa cliente.
     */
    public function configAssinatura()
    {
        $config = ConfigAssinatura::first();

        return response()->json($config ? [
            'provider' => $config->provider,
            'ambiente' => $config->ambiente,
            'ativo' => $config->ativo,
            'tem_credenciais' => ! empty($config->api_key),
        ] : null);
    }

    public function atualizarConfigAssinatura(Request $request)
    {
        $dados = $request->validate([
            'provider' => ['required', 'in:asaas'],
            'ambiente' => ['required', 'in:sandbox,producao'],
            'api_key' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        if (empty($dados['api_key'])) {
            unset($dados['api_key']);
        }

        $config = ConfigAssinatura::first();
        $config = $config ? tap($config)->update($dados) : ConfigAssinatura::create($dados);

        return response()->json([
            'provider' => $config->provider,
            'ambiente' => $config->ambiente,
            'ativo' => $config->ativo,
            'tem_credenciais' => ! empty($config->api_key),
        ]);
    }

    // ---- Tabela IBPT (Lei da Transparência Fiscal, 12.741/2012) ----

    /**
     * Tabela oficial e global (mesma lógica de tab_cclasstrib/
     * tab_ccredpres) - por isso a importação fica no Super Admin, não
     * no dashboard de cada empresa: evita que 50 empresas importem a
     * mesma tabela nacional separadamente, com risco de uma sobrescrever
     * com uma versão desatualizada.
     */
    public function ibptStatus()
    {
        $total = IbptProduto::count();
        $ultimo = IbptProduto::orderByDesc('updated_at')->first();

        return response()->json([
            'total' => $total,
            'versao' => $ultimo?->versao,
            'atualizado_em' => $ultimo?->updated_at,
        ]);
    }

    public function importarIbpt(Request $request)
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        try {
            $resultado = $this->importadorIbptService->importar($request->file('arquivo')->getRealPath());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    public function buscarIbpt(Request $request)
    {
        $dados = $request->validate(['ncm' => ['required', 'string', 'max:10']]);

        return response()->json(
            IbptProduto::where('codigo', 'like', $dados['ncm'].'%')->limit(20)->get()
        );
    }

    /**
     * CFOP (Ajuste SINIEF s/nº de 15/12/1970): tabela oficial e global,
     * mesma lógica do IBPT acima - fica no Super Admin para não deixar
     * cada empresa cadastrar/duplicar a mesma tabela nacional.
     */
    public function cfops(Request $request)
    {
        $busca = $request->query('busca');

        $query = Cfop::query()->orderBy('codigo');

        if ($busca) {
            $query->where(function ($q) use ($busca) {
                $q->where('codigo', 'like', $busca.'%')
                    ->orWhere('descricao', 'like', '%'.$busca.'%');
            });
        }

        return response()->json($query->limit(500)->get());
    }

    public function criarCfop(Request $request)
    {
        $dados = $request->validate([
            'codigo' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/', 'unique:cfops,codigo'],
            'descricao' => ['required', 'string', 'max:500'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        return response()->json(Cfop::create($dados), 201);
    }

    public function atualizarCfop(Request $request, int $cfopId)
    {
        $cfop = Cfop::findOrFail($cfopId);

        $dados = $request->validate([
            'descricao' => ['sometimes', 'string', 'max:500'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $cfop->update($dados);

        return response()->json($cfop->fresh());
    }

    public function importarCfopPadrao()
    {
        return response()->json($this->importadorCfopService->importarPadrao());
    }
}
