<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\ConfigAssinatura;
use App\Models\Empresa;
use App\Models\Plano;
use App\Services\Assinatura\AssinaturaService;
use Illuminate\Http\Request;

/**
 * Painel Super Admin (Escopo v2, seção 2.2): gestão de empresas, planos
 * e faturamento da assinatura - uso interno da equipe da plataforma,
 * não das empresas clientes.
 */
class SuperAdminController extends Controller
{
    public function __construct(private readonly AssinaturaService $assinaturaService) {}

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
}
