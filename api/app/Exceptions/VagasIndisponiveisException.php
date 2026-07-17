<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando uma reserva não pode ser feita: horário lotado,
 * fechado, ou a reserva/confirmação já não está mais ativa.
 */
class VagasIndisponiveisException extends RuntimeException
{
    //
}
