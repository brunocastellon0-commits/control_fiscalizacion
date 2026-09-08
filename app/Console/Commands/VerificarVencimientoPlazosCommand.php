<?php

namespace App\Console\Commands;

use App\Services\ArchivoPorAbandonoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plazos:verificar-vencidos')]
#[Description('Registra el actuado automático de archivo por abandono en cada plazo de subsanación vencido (RN-03).')]
class VerificarVencimientoPlazosCommand extends Command
{
    /**
     * Ejecuta la verificación de plazos de subsanación vencidos y archiva
     * los expedientes sin respuesta del interesado.
     */
    public function handle(ArchivoPorAbandonoService $archivoPorAbandonoService): int
    {
        $archivados = $archivoPorAbandonoService->archivarVencidos();

        $this->info("Archivo automático ejecutado: {$archivados} expediente(s) archivado(s) por abandono.");

        return self::SUCCESS;
    }
}
