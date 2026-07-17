<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Services\Agendamento\ReservaVagaService;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function __construct(private readonly ReservaVagaService $reservaVagaService) {}

    /**
     * Reserva temporária de vagas (checkout ainda não confirmado).
     * A vaga fica retida por alguns minutos - ver ReservaVagaService.
     */
    public function store(Request $request, string $empresa)
    {
        $dados = $request->validate([
            'agenda_visitacao_id' => ['required', 'integer'],
            'quantidade' => ['required', 'integer', 'min:1'],
        ]);

        $reserva = $this->reservaVagaService->reservar(
            $dados['agenda_visitacao_id'],
            $dados['quantidade'],
        );

        return response()->json([
            'reserva_id' => $reserva->id,
            'quantidade' => $reserva->quantidade,
            'expira_em' => $reserva->expira_em,
            'status' => $reserva->status,
        ], 201);
    }
}
