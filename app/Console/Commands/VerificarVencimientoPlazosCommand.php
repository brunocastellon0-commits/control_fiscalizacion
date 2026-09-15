<?php

namespace App\Console\Commands;

use App\Services\ArchivoPorAbandonoService;
use App\Services\MarcarPlazosVencidosService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plazos:verificar-vencidos')]
#[Description('Archiva por abandono la SUBSANACION vencida (RN-03) y estampa fuera_de_plazo en los plazos internos vencidos (QA 3).')]
class VerificarVencimientoPlazosCommand extends Command
{
    /**
     * Ejecuta la verificación diaria de plazos vencidos:
     *
     * 1. Archiva los expedientes en SUBSANACION sin respuesta del interesado.
     * 2. Marca como fuera de plazo los plazos internos vencidos, sin bloquear
     *    el flujo del expediente.
     */
    public function handle(
        ArchivoPorAbandonoService $archivoPorAbandonoService,
        MarcarPlazosVencidosService $marcarPlazosVencidosService,
    ): int {
        $archivados = $archivoPorAbandonoService->archivarVencidos();
        $marcados = $marcarPlazosVencidosService->marcarVencidos();

        $this->info("Archivo automático ejecutado: {$archivados} expediente(s) archivado(s) por abandono.");
        $this->info("Plazos internos marcados como fuera de plazo: {$marcados}.");

        return self::SUCCESS;
    }
}
