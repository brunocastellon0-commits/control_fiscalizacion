<?php

namespace App\Services;

use App\Models\Plazo;

class MarcarPlazosVencidosService
{
    /**
     * Estampa el flag fuera_de_plazo en los plazos internos (distintos de
     * SUBSANACION) que siguen VIGENTE y cuyo vencimiento estricto ya fue
     * superado (QA 3): el vencimiento no bloquea el flujo, pero deja una
     * marca persistente de auditoría de rendimiento.
     *
     * El plazo se mantiene VIGENTE para que el cierre por entrega tardía
     * (VIGENTE -> CERRADO) de los servicios de flujo siga funcionando y
     * preserve la marca histórica. No transiciona el expediente ni cierra
     * bandejas: eso es responsabilidad del flujo o de
     * ArchivoPorAbandonoService. Idempotente gracias al filtro
     * fuera_de_plazo = false.
     *
     * @return int Cantidad de plazos marcados como fuera de plazo.
     */
    public function marcarVencidos(): int
    {
        return Plazo::query()
            ->where('tipo_plazo', '!=', ArchivoPorAbandonoService::TIPO_PLAZO_SUBSANACION)
            ->where('estado', ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
            ->where('fuera_de_plazo', false)
            ->where('fecha_limite', '<', now()->toDateString())
            ->update(['fuera_de_plazo' => true]);
    }
}
