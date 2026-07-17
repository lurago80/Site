<?php

namespace Tests\Feature;

use App\Exceptions\VagasIndisponiveisException;
use App\Models\AgendaVisitacao;
use App\Models\Empresa;
use App\Models\Plano;
use App\Services\Agendamento\ReservaVagaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

class ReservaVagaServiceTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private ReservaVagaService $service;

    private AgendaVisitacao $agenda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReservaVagaService();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);
        $empresa = Empresa::create([
            'razao_social' => 'Cervejaria Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'cervejaria-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->asEmpresa($empresa->id);

        $this->agenda = AgendaVisitacao::create([
            'empresa_id' => $empresa->id,
            'data_hora' => now()->addDay(),
            'vagas_total' => 2,
            'vagas_reservadas' => 0,
            'status' => 'aberta',
            'valor_visita' => 60.00,
        ]);
    }

    public function test_reserva_dentro_do_limite_de_vagas(): void
    {
        $reserva = $this->service->reservar($this->agenda->id, 2);

        $this->assertSame('ativa', $reserva->status);
        $this->assertSame(0, $this->agenda->fresh()->vagasDisponiveis());
        $this->assertSame('lotada', $this->agenda->fresh()->status);
    }

    public function test_bloqueia_reserva_acima_da_capacidade_disponivel(): void
    {
        $this->service->reservar($this->agenda->id, 2);

        $this->expectException(VagasIndisponiveisException::class);

        $this->service->reservar($this->agenda->id, 1);
    }

    public function test_bloqueia_reserva_em_agenda_nao_aberta(): void
    {
        $this->agenda->update(['status' => 'cancelada']);

        $this->expectException(VagasIndisponiveisException::class);

        $this->service->reservar($this->agenda->id, 1);
    }

    public function test_liberar_reserva_devolve_vaga_e_reabre_agenda_lotada(): void
    {
        $reserva = $this->service->reservar($this->agenda->id, 2);
        $this->assertSame('lotada', $this->agenda->fresh()->status);

        $this->service->liberar($reserva->id, 'expirada');

        $this->assertSame(2, $this->agenda->fresh()->vagasDisponiveis());
        $this->assertSame('aberta', $this->agenda->fresh()->status);
        $this->assertSame('expirada', $reserva->fresh()->status);
    }

    public function test_confirmar_reserva_ativa(): void
    {
        $reserva = $this->service->reservar($this->agenda->id, 1);

        $confirmada = $this->service->confirmar($reserva->id);

        $this->assertSame('confirmada', $confirmada->status);
    }

    public function test_confirmar_reserva_ja_confirmada_lanca_excecao(): void
    {
        $reserva = $this->service->reservar($this->agenda->id, 1);
        $this->service->confirmar($reserva->id);

        $this->expectException(VagasIndisponiveisException::class);

        $this->service->confirmar($reserva->id);
    }

    public function test_liberar_reserva_ja_confirmada_e_idempotente_e_nao_devolve_vaga(): void
    {
        $reserva = $this->service->reservar($this->agenda->id, 2);
        $this->service->confirmar($reserva->id);

        $this->service->liberar($reserva->id, 'expirada');

        $this->assertSame('confirmada', $reserva->fresh()->status);
        $this->assertSame(0, $this->agenda->fresh()->vagasDisponiveis());
    }

    public function test_reservar_quantidade_zero_lanca_excecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->reservar($this->agenda->id, 0);
    }
}
