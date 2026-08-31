<?php

namespace Database\Seeders;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class CatalogoActuadoSeeder extends Seeder
{
    /**
     * Actuados base del flujo de fiscalización.
     */
    public function run(): void
    {
        $encargada = Rol::where('codigo', 'ENCARGADA')->firstOrFail();
        $tecnico = Rol::where('codigo', 'TECNICO')->firstOrFail();
        $audJuridico = Rol::where('codigo', 'AUD_JURIDICO')->firstOrFail();

        $pendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();
        $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
        $observado = CatalogoEstado::where('codigo', 'OBSERVADO')->firstOrFail();
        $admitido = CatalogoEstado::where('codigo', 'ADMITIDO')->firstOrFail();
        $rechazado = CatalogoEstado::where('codigo', 'RECHAZADO')->firstOrFail();

        $actuados = [
            ['codigo' => 'ACT_REGISTRO_DIGITALIZACION', 'nombre' => 'Registro y Digitalización', 'fase' => 'REGISTRO', 'rol_id' => $tecnico->id, 'estado_origen_id' => null, 'estado_destino_id' => $pendiente->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Registro y digitalización del expediente'],
            ['codigo' => 'ACT_SORTEO_INICIAL', 'nombre' => 'Sorteo Inicial', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $encargada->id, 'estado_origen_id' => $pendiente->id, 'estado_destino_id' => $evaluacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Sorteo inicial de expedientes'],
            ['codigo' => 'ACT_OBSERVACION', 'nombre' => 'Observación', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'estado_origen_id' => $evaluacion->id, 'estado_destino_id' => $observado->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Observación de requisitos'],
            ['codigo' => 'ACT_ADMISION', 'nombre' => 'Admisión', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'estado_origen_id' => $evaluacion->id, 'estado_destino_id' => $admitido->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Admisión del expediente'],
            ['codigo' => 'ACT_RECHAZO', 'nombre' => 'Rechazo', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'estado_origen_id' => $evaluacion->id, 'estado_destino_id' => $rechazado->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Rechazo del expediente'],
            ['codigo' => 'ACT_INFORME_FINAL', 'nombre' => 'Informe Final', 'fase' => 'INVESTIGACION', 'rol_id' => $audJuridico->id, 'estado_origen_id' => $admitido->id, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final de la investigación'],
        ];

        foreach ($actuados as $a) {
            CatalogoActuado::updateOrCreate(['codigo' => $a['codigo']], $a);
        }
    }
}
