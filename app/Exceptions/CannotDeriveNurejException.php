<?php

namespace App\Exceptions;

use DomainException;

/**
 * Lanzada al intentar derivar un NUREJ de un expediente que ya es derivado
 * (RN-10): la relación es estrictamente Padre -> Hijo, no existe sub-hijo.
 */
class CannotDeriveNurejException extends DomainException
{
    public function __construct()
    {
        parent::__construct('No se pueden generar NUREJ hijos de un expediente ya derivado (RN-10).');
    }
}
