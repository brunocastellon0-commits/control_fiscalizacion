<?php

namespace App\Services;

use App\Exceptions\CannotDeriveNurejException;
use App\Models\Expediente;
use App\Models\NurejSequence;
use Illuminate\Support\Facades\DB;

class NurejGeneratorService
{
    /**
     * Genera el NUREJ Padre del año en curso en forma atómica: `YYYY-NNNNN`
     * (ej. `2026-00001`). El lock `FOR UPDATE` sobre la secuencia del año
     * serializa la creación concurrente de causas.
     */
    public function generarPadre(): string
    {
        return DB::transaction(function () {
            $anio = (int) now()->format('Y');

            $secuencia = $this->lockearSecuencia($anio);
            $secuencia->increment('correlativo');

            return sprintf('%d-%05d', $anio, $secuencia->correlativo);
        });
    }

    /**
     * Genera el NUREJ Hijo/Derivado de un expediente padre: `YYYY-NNNNN-X`
     * (ej. `2026-00001-2`). Bloquea la fila del padre para serializar
     * derivaciones simultáneas sobre la misma causa. Por RN-10, solo una
     * causa matriz (sin `nurej_padre_id`) puede tener derivados.
     */
    public function generarHijo(int $nurejPadreId): string
    {
        return DB::transaction(function () use ($nurejPadreId) {
            $padre = Expediente::select('id', 'nurej_code', 'nurej_padre_id')
                ->whereKey($nurejPadreId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($padre->nurej_padre_id !== null) {
                throw new CannotDeriveNurejException;
            }

            $nroHijo = Expediente::where('nurej_padre_id', $nurejPadreId)->count() + 1;

            return $padre->nurej_code.'-'.$nroHijo;
        });
    }

    /**
     * Devuelve la fila de secuencia del año bloqueada para escritura.
     * Si aún no existe (primer código del ejercicio), la crea con
     * `insertOrIgnore` para tolerar la carrera de creación simultánea.
     */
    protected function lockearSecuencia(int $anio): NurejSequence
    {
        $secuencia = NurejSequence::where('anio', $anio)->lockForUpdate()->first();

        if ($secuencia === null) {
            DB::table('nurej_sequences')->insertOrIgnore([
                'anio' => $anio,
                'correlativo' => 0,
            ]);

            $secuencia = NurejSequence::where('anio', $anio)->lockForUpdate()->firstOrFail();
        }

        return $secuencia;
    }
}
