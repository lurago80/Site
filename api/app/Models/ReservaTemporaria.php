<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'agenda_visitacao_id', 'quantidade', 'expira_em', 'status'])]
class ReservaTemporaria extends Model
{
    protected $table = 'reservas_temporarias';

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
        ];
    }

    public function agendaVisitacao(): BelongsTo
    {
        return $this->belongsTo(AgendaVisitacao::class);
    }
}
