<?php

namespace App\Policies;

use App\Models\Actuado;
use App\Models\Usuario;

class ActuadoPolicy
{
    /**
     * B2.3: lectura de un actuado (y de sus adjuntos) bajo el mismo criterio
     * del expediente contenedor. Sin bypass para ADMIN (RF-03/AUD-0001).
     */
    public function view(Usuario $user, Actuado $actuado): bool
    {
        return app(ExpedientePolicy::class)->view($user, $actuado->expediente);
    }
}
