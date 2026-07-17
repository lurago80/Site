<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'produto_id', 'data_hora', 'vagas_total', 'vagas_reservadas', 'status', 'valor_visita'])]
class AgendaVisitacao extends Model
{
    protected $table = 'agenda_visitacoes';

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'valor_visita' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function reservasTemporarias(): HasMany
    {
        return $this->hasMany(ReservaTemporaria::class);
    }

    public function vagasDisponiveis(): int
    {
        return $this->vagas_total - $this->vagas_reservadas;
    }
}
