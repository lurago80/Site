<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Plano;
use Illuminate\Http\Request;

/**
 * Painel Super Admin (Escopo v2, seção 2.2): gestão de empresas, planos
 * e faturamento da assinatura - uso interno da equipe da plataforma,
 * não das empresas clientes.
 */
class SuperAdminController extends Controller
{
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

    public function criarAssinatura(Request $request)
    {
        $dados = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'plano_id' => ['required', 'integer', 'exists:planos,id'],
            'status_pagamento' => ['required', 'in:em_dia,atrasado,cancelado'],
            'inicio' => ['required', 'date'],
            'proxima_cobranca' => ['nullable', 'date'],
        ]);

        $assinatura = Assinatura::create($dados);

        return response()->json($assinatura->load('empresa', 'plano'), 201);
    }
}
