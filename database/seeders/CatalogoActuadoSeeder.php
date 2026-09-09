<?php

namespace Database\Seeders;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Reglamento;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoActuadoSeeder extends Seeder
{
    /**
     * Actuados base del flujo de fiscalización.
     *
     * Los actuados ADMISION/OBSERVACION/RECHAZO están habilitados para los
     * tres perfiles operativos vía la tabla pivote catalogo_actuado_roles,
     * uno por reglamento (Técnico-AC022, Auditor Jurídico-AC054, Auditor
     * Financiero-AC055).
     */
    public function run(): void
    {
        $encargada = Rol::where('codigo', 'ENCARGADA')->firstOrFail();
        $tecnico = Rol::where('codigo', 'TECNICO')->firstOrFail();
        $audJuridico = Rol::where('codigo', 'AUD_JURIDICO')->firstOrFail();
        $audFinanciero = Rol::where('codigo', 'AUD_FINANCIERO')->firstOrFail();
        $admin = Rol::where('codigo', 'ADMIN')->firstOrFail();

        $pendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();
        $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
        $rechazado = CatalogoEstado::where('codigo', 'RECHAZADO')->firstOrFail();
        $subsanacion = CatalogoEstado::where('codigo', 'EN_SUBSANACION')->firstOrFail();
        $planificacion = CatalogoEstado::where('codigo', 'EN_PLANIFICACION')->firstOrFail();
        $pendienteVistoBueno = CatalogoEstado::where('codigo', 'PENDIENTE_VISTO_BUENO')->firstOrFail();
        $ejecucion = CatalogoEstado::where('codigo', 'EN_EJECUCION')->firstOrFail();
        $archivoAbandono = CatalogoEstado::where('codigo', 'ARCHIVO_POR_ABANDONO')->firstOrFail();
        $enImpugnacion = CatalogoEstado::where('codigo', 'EN_IMPUGNACION')->firstOrFail();
        $archivoDefinitivo = CatalogoEstado::where('codigo', 'ARCHIVO_DEFINITIVO')->firstOrFail();
        $admitido = CatalogoEstado::where('codigo', 'ADMITIDO')->firstOrFail();

        $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
        $ac054 = Reglamento::where('codigo', 'AC_054_2018')->firstOrFail();
        $ac055 = Reglamento::where('codigo', 'AC_055_2018')->firstOrFail();

        $actuados = [
            ['codigo' => 'ACT_REGISTRO_DIGITALIZACION', 'nombre' => 'Registro y Digitalización', 'fase' => 'REGISTRO', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => $pendiente->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Registro y digitalización del expediente'],
            ['codigo' => 'ACT_SORTEO_INICIAL', 'nombre' => 'Sorteo Inicial', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $pendiente->id, 'estado_destino_id' => $evaluacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Sorteo inicial de expedientes'],
            ['codigo' => 'ACT_OBSERVACION', 'nombre' => 'Observación', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $evaluacion->id, 'estado_destino_id' => $subsanacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Observación de requisitos no críticos; abre subsanación'],
            ['codigo' => 'ACT_ADMISION', 'nombre' => 'Admisión', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $evaluacion->id, 'estado_destino_id' => $planificacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Admisión del expediente; habilita la planificación'],
            ['codigo' => 'ACT_RECHAZO', 'nombre' => 'Rechazo', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $evaluacion->id, 'estado_destino_id' => $rechazado->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Rechazo por requisito crítico ausente; desactiva relojes'],
            ['codigo' => 'ACT_VISTO_BUENO_PLANIFICACION', 'nombre' => 'Visto Bueno a Planificación', 'fase' => 'PLANIFICACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $pendienteVistoBueno->id, 'estado_destino_id' => $ejecucion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Aprueba el Cronograma/MPA; inicia la investigación y su plazo (RN-04/RN-05)'],
            ['codigo' => 'ACT_CRONOGRAMA_TRABAJO', 'nombre' => 'Cronograma de Trabajo', 'fase' => 'PLANIFICACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $planificacion->id, 'estado_destino_id' => $pendienteVistoBueno->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Carga del cronograma de trabajo por el Técnico (AC022)'],
            ['codigo' => 'ACT_MPA', 'nombre' => 'MPA (Programa de Auditoría)', 'fase' => 'PLANIFICACION', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $planificacion->id, 'estado_destino_id' => $pendienteVistoBueno->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Carga del MPA con fecha límite propuesta (AC054/AC055)'],
            ['codigo' => 'ACT_INFORME_FINAL', 'nombre' => 'Informe Final', 'fase' => 'INVESTIGACION', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final de la investigación'],
            ['codigo' => 'ACT_ARCHIVO_POR_ABANDONO', 'nombre' => 'Archivo por Abandono', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $admin->id, 'reglamento_id' => null, 'estado_origen_id' => $subsanacion->id, 'estado_destino_id' => $archivoAbandono->id, 'es_automatico' => true, 'requiere_adjunto' => false, 'descripcion' => 'Evento automático del sistema: archiva el expediente por caducidad del plazo de subsanación sin respuesta (RN-03)'],
            // Impugnaciones (RN-08)
            ['codigo' => 'ACT_REMITIR_IMPUGNACION', 'nombre' => 'Remisión de Impugnación', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $rechazado->id, 'estado_destino_id' => $enImpugnacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'El operador remite el expediente rechazado a la Encargada para su resolución (RN-08)'],
            ['codigo' => 'ACT_RESOLUCION_RATIFICA_RECHAZO', 'nombre' => 'Ratificación del Rechazo', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $enImpugnacion->id, 'estado_destino_id' => $archivoDefinitivo->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada ratifica el rechazo; el expediente queda en ARCHIVO_DEFINITIVO (RN-08)'],
            ['codigo' => 'ACT_RESOLUCION_REVOCA_RECHAZO', 'nombre' => 'Revocación del Rechazo', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $enImpugnacion->id, 'estado_destino_id' => $admitido->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada revoca el rechazo; el expediente retorna a ADMITIDO para su sustanciación (RN-08)'],
        ];

        foreach ($actuados as $a) {
            CatalogoActuado::updateOrCreate(['codigo' => $a['codigo']], $a);
        }

        $pivotes = [
            'ACT_REGISTRO_DIGITALIZACION' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => null],
            ],
            'ACT_SORTEO_INICIAL' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_OBSERVACION' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
            'ACT_ADMISION' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
            'ACT_RECHAZO' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
            'ACT_VISTO_BUENO_PLANIFICACION' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_CRONOGRAMA_TRABAJO' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ],
            'ACT_MPA' => [
                ['rol_id' => $audJuridico->id, 'reglamento_id' => $ac054->id],
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => $ac055->id],
            ],
            'ACT_INFORME_FINAL' => [
                ['rol_id' => $audJuridico->id, 'reglamento_id' => null],
            ],
            'ACT_REMITIR_IMPUGNACION' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
            'ACT_RESOLUCION_RATIFICA_RECHAZO' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_RESOLUCION_REVOCA_RECHAZO' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
        ];

        foreach ($pivotes as $codigo => $combinaciones) {
            $actuado = CatalogoActuado::where('codigo', $codigo)->firstOrFail();

            DB::table('catalogo_actuado_roles')
                ->where('catalogo_actuado_id', $actuado->id)
                ->delete();

            foreach ($combinaciones as $combo) {
                DB::table('catalogo_actuado_roles')->insert([
                    'catalogo_actuado_id' => $actuado->id,
                    'rol_id' => $combo['rol_id'],
                    'reglamento_id' => $combo['reglamento_id'],
                ]);
            }
        }
    }

    /**
     * Perfiles habilitados para los actuados de evaluación de admisibilidad:
     * un registro por (rol operativo, reglamento del Acuerdo).
     *
     * @return array<int, array{rol_id: int, reglamento_id: int}>
     */
    private function perfilesEvaluacion(
        Rol $tecnico,
        Rol $audJuridico,
        Rol $audFinanciero,
        Reglamento $ac022,
        Reglamento $ac054,
        Reglamento $ac055,
    ): array {
        return [
            ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ['rol_id' => $audJuridico->id, 'reglamento_id' => $ac054->id],
            ['rol_id' => $audFinanciero->id, 'reglamento_id' => $ac055->id],
        ];
    }
}
