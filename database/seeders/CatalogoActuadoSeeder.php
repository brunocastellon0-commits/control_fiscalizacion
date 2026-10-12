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
        $encargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();
        $tecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
        $audJuridico = Rol::where('codigo', Rol::CODIGO_AUD_JURIDICO)->firstOrFail();
        $audFinanciero = Rol::where('codigo', Rol::CODIGO_AUD_FINANCIERO)->firstOrFail();
        $admin = Rol::where('codigo', Rol::CODIGO_ADMIN)->firstOrFail();

        $pendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();
        $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
        $rechazado = CatalogoEstado::where('codigo', 'RECHAZADO')->firstOrFail();
        $subsanacion = CatalogoEstado::where('codigo', 'EN_SUBSANACION')->firstOrFail();
        $planificacion = CatalogoEstado::where('codigo', 'EN_PLANIFICACION')->firstOrFail();
        $pendienteVistoBueno = CatalogoEstado::where('codigo', 'PENDIENTE_VISTO_BUENO')->firstOrFail();
        $ejecucion = CatalogoEstado::where('codigo', 'EN_EJECUCION')->firstOrFail();
        $pendienteAprobacionAmpliacion = CatalogoEstado::where('codigo', 'PENDIENTE_APROBACION_AMPLIACION')->firstOrFail();
        $archivoAbandono = CatalogoEstado::where('codigo', 'ARCHIVO_POR_ABANDONO')->firstOrFail();
        $enImpugnacion = CatalogoEstado::where('codigo', 'EN_IMPUGNACION')->firstOrFail();
        $archivoDefinitivo = CatalogoEstado::where('codigo', 'ARCHIVO_DEFINITIVO')->firstOrFail();
        $admitido = CatalogoEstado::where('codigo', 'ADMITIDO')->firstOrFail();
        $pendienteVbFinal = CatalogoEstado::where('codigo', 'PENDIENTE_VISTO_BUENO_FINAL')->firstOrFail();
        $listoParaReparto = CatalogoEstado::where('codigo', 'LISTO_PARA_REPARTO')->firstOrFail();
        $concluidoRemitido = CatalogoEstado::where('codigo', 'CONCLUIDO_REMITIDO')->firstOrFail();
        $remitidoUnidadExterna = CatalogoEstado::where('codigo', 'REMITIDO_UNIDAD_EXTERNA')->firstOrFail();

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
            ['codigo' => 'ACT_DEVOLUCION_OBSERVACION', 'nombre' => 'Devolución por Observaciones', 'fase' => 'PLANIFICACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $pendienteVistoBueno->id, 'estado_destino_id' => $planificacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada devuelve el Cronograma/MPA con observaciones; retorna a planificación y reabre su plazo (2 días hábiles)'],
            ['codigo' => 'ACT_INFORME_FINAL', 'nombre' => 'Informe Final', 'fase' => 'INVESTIGACION', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final de la investigación; habilita el Visto Bueno Final de la Encargada (RN-09)'],
            // Ampliación de plazo en ejecución (US-2.6, solo AC022)
            ['codigo' => 'ACT_SOLICITAR_AMPLIACION', 'nombre' => 'Solicitud de Ampliación de Plazo', 'fase' => 'INVESTIGACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteAprobacionAmpliacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'El Técnico solicita la única ampliación de 5 días hábiles del plazo de ejecución (AC022)'],
            ['codigo' => 'ACT_APROBAR_AMPLIACION', 'nombre' => 'Aprobación de Ampliación de Plazo', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $pendienteAprobacionAmpliacion->id, 'estado_destino_id' => $ejecucion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada aprueba la ampliación; cierra el plazo original y abre EJECUCION_AMPLIADA por 5 días hábiles'],
            ['codigo' => 'ACT_ARCHIVO_POR_ABANDONO', 'nombre' => 'Archivo por Abandono', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $admin->id, 'reglamento_id' => null, 'estado_origen_id' => $subsanacion->id, 'estado_destino_id' => $archivoAbandono->id, 'es_automatico' => true, 'requiere_adjunto' => false, 'descripcion' => 'Evento automático del sistema: archiva el expediente por caducidad del plazo de subsanación sin respuesta (RN-03)'],
            // Salida de éxito de subsanación (RN-03 / AUD-0032, B1.4): única
            // alternativa al archivo por abandono; cierra su reloj vía
            // MAPA_CIERRA_PLAZO y retorna el expediente a evaluación.
            ['codigo' => 'ACT_SUBSANACION_ACEPTADA', 'nombre' => 'Subsanación Aceptada', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $subsanacion->id, 'estado_destino_id' => $evaluacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Aceptación de la subsanación presentada por el interesado; retorna el expediente a EN_EVALUACION y cierra el plazo de subsanación (RN-03)'],
            // Impugnaciones (RN-08)
            ['codigo' => 'ACT_REMITIR_IMPUGNACION', 'nombre' => 'Remisión de Impugnación', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $rechazado->id, 'estado_destino_id' => $enImpugnacion->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'El operador remite el expediente rechazado a la Encargada para su resolución (RN-08)'],
            ['codigo' => 'ACT_RESOLUCION_RATIFICA_RECHAZO', 'nombre' => 'Ratificación del Rechazo', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $enImpugnacion->id, 'estado_destino_id' => $archivoDefinitivo->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada ratifica el rechazo; el expediente queda en ARCHIVO_DEFINITIVO (RN-08)'],
            ['codigo' => 'ACT_RESOLUCION_REVOCA_RECHAZO', 'nombre' => 'Revocación del Rechazo', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $enImpugnacion->id, 'estado_destino_id' => $admitido->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada revoca el rechazo; el expediente retorna a ADMITIDO para su sustanciación (RN-08)'],
            // Paso automático ADMITIDO → EN_PLANIFICACION (AUD-0033, D-6b): cierra el
            // hueco AUD-0021 #2 (ADMITIDO sin salida). Sin entrada en MAPA_TIPO_PLAZO:
            // el plazo PLANIFICACION ya lo abre la revocación.
            ['codigo' => 'ACT_PASO_PLANIFICACION', 'nombre' => 'Paso Automático a Planificación', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $admin->id, 'reglamento_id' => null, 'estado_origen_id' => $admitido->id, 'estado_destino_id' => $planificacion->id, 'es_automatico' => true, 'requiere_adjunto' => false, 'descripcion' => 'Evento automático del sistema: al aterrizar en ADMITIDO el expediente transiciona a EN_PLANIFICACION en la misma transacción (AUD-0033, D-6b)'],
            // Derivación NUREJ Hijo (E9-S1, RN-10)
            ['codigo' => 'ACT_CREACION_NUREJ_HIJO', 'nombre' => 'Creación de NUREJ Hijo', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada deriva un NUREJ Hijo a partir de un expediente padre; el padre conserva su estado (RN-10)'],
            // Cierre y salida institucional (E10-S1/S2, RN-09/RN-12)
            ['codigo' => 'ACT_VISTO_BUENO_FINAL', 'nombre' => 'Visto Bueno Final', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $pendienteVbFinal->id, 'estado_destino_id' => $listoParaReparto->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Aprobación jerárquica del informe final; habilita el reparto institucional (E10-S1)'],
            ['codigo' => 'ACT_REPARTO_INSTITUCIONAL', 'nombre' => 'Reparto Institucional', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $listoParaReparto->id, 'estado_destino_id' => $concluidoRemitido->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Cierre formal del NUREJ: remisión a destino externo (RN-09/RN-12)'],
            // Incompetencia procesal (E5-S5, RN-09 Fase 5 / CTR-10, B2.2): el
            // par Solicitud/Visto Bueno reemplaza al flujo B1.3 (A20/A21, dados
            // de baja más abajo). Destino null en ambos: la transición la fija
            // el service con estadoNuevoIdExplicito (EN_EVALUACION al solicitar,
            // REMITIDO_UNIDAD_EXTERNA al aprobar el VB y el estado original al
            // rechazarlo). Sin entrada en MAPA_TIPO_PLAZO/MAPA_CIERRA_PLAZO: la
            // congelación de relojes la hace el service al aprobar el VB.
            ['codigo' => 'ACT_SOLICITUD_INCOMPETENCIA', 'nombre' => 'Solicitud de Incompetencia Procesal', 'fase' => 'INVESTIGACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'El operador solicita incompetencia y envía el caso a la Encargada en espera de visto bueno (CTR-10); guarda unidad_destino_id en metadatos, no toca relojes ni asignaciones'],
            ['codigo' => 'ACT_VB_INCOMPETENCIA', 'nombre' => 'Visto Bueno de Incompetencia', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada resuelve la solicitud de incompetencia (CTR-10); al aprobar congela los relojes vigentes y remite a la unidad externa (REMITIDO_UNIDAD_EXTERNA) con estado_previo_id, al rechazar restaura el estado original'],
            // Fase de descargos de auditoría financiera (E7-S*, RN-09, AC055)
            ['codigo' => 'ACT_COMUNICACION_HALLAZGOS', 'nombre' => 'Comunicación de Hallazgos', 'fase' => 'INVESTIGACION', 'rol_id' => $audFinanciero->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Comunicación formal de hallazgos a los auditados (AC055); pausa el reloj de ejecución y abre el sub-reloj de 5 días hábiles para descargos (RN-09)'],
            ['codigo' => 'ACT_RECEPCION_DESCARGOS', 'nombre' => 'Recepción de Descargos', 'fase' => 'INVESTIGACION', 'rol_id' => $audFinanciero->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Recepción de los descargos presentados por los auditados (AC055); cierra el sub-reloj y reanuda el reloj de ejecución (RN-09)'],
            ['codigo' => 'ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD', 'nombre' => 'Informe Final con Responsabilidad', 'fase' => 'INVESTIGACION', 'rol_id' => $audFinanciero->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final del auditor financiero con responsabilidad (AC055); exige la fase de descargos previa y habilita el cierre jerárquico (RN-09)'],
            ['codigo' => 'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD', 'nombre' => 'Informe Final sin Responsabilidad', 'fase' => 'INVESTIGACION', 'rol_id' => $audFinanciero->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final del auditor financiero sin responsabilidad (AC055); exige la fase de descargos previa y habilita el cierre jerárquico (RN-09)'],
            // Informes finales exclusivos del Técnico (SRS 374-391, AUD-0008, D-6d)
            ['codigo' => 'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD', 'nombre' => 'Informe Final Técnico con Responsabilidad', 'fase' => 'INVESTIGACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final técnico con indicios de falta disciplinaria (AC022); pasa a Visto Bueno Final de la Encargada (RN-09)'],
            ['codigo' => 'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD', 'nombre' => 'Informe Final Técnico sin Responsabilidad', 'fase' => 'INVESTIGACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final técnico sin hallazgo de faltas (AC022); pasa a Visto Bueno Final de la Encargada (RN-09)'],
            ['codigo' => 'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION', 'nombre' => 'Informe Final Técnico con Responsabilidad y Recomendación de Auditoría', 'fase' => 'INVESTIGACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final técnico con falta disciplinaria y recomendación de auditoría de fondo (AC022); al recibir Visto Bueno cierra el NUREJ Padre y gatilla el NUREJ Hijo (RN-10)'],
            ['codigo' => 'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION', 'nombre' => 'Informe Final Técnico sin Responsabilidad y Recomendación de Auditoría', 'fase' => 'INVESTIGACION', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe final técnico sin falta pero con irregularidades de fondo (AC022); al recibir Visto Bueno archiva el Padre y gatilla el NUREJ Hijo (RN-10)'],
            // Informes finales exclusivos del Auditor Jurídico (SRS 393-402, AUD-0008, D-6e)
            ['codigo' => 'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD', 'nombre' => 'Informe de Auditoría Jurídica con Responsabilidad', 'fase' => 'INVESTIGACION', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe de auditoría jurídica que establece fallos contra la ley o daño (AC054); pasa a Visto Bueno Final para remisión externa (RN-09)'],
            ['codigo' => 'ACT_INFORME_AUDITORIA_JURIDICA_SIN_RESPONSABILIDAD', 'nombre' => 'Informe de Auditoría Jurídica sin Responsabilidad', 'fase' => 'INVESTIGACION', 'rol_id' => $audJuridico->id, 'reglamento_id' => null, 'estado_origen_id' => $ejecucion->id, 'estado_destino_id' => $pendienteVbFinal->id, 'es_automatico' => false, 'requiere_adjunto' => true, 'descripcion' => 'Informe de auditoría jurídica que desestima las irregularidades (AC054); pasa a Visto Bueno Final para archivo definitivo (RN-09)'],
            // Enmienda de datos caratulados (RF-02 / CTR-09, B1.7): no-op de
            // estado (origen y destino null); el estado nuevo del actuado se
            // fija como `estado_actual_id` en EnmiendaService. Sin entrada en
            // MAPA_TIPO_PLAZO/MAPA_CIERRA_PLAZO: no toca relojes.
            ['codigo' => 'ACT_ENMIENDA', 'nombre' => 'Actuado de Enmienda', 'fase' => 'REGISTRO', 'rol_id' => $tecnico->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'Corrección de datos caratulados o de partes con captura de datos_anteriores y datos_nuevos en el payload (RF-02); conserva el estado procesal de la causa'],
            // Par Remisión/Recepción de unidades inter (RF-05 / CTR-04 y CTR-05,
            // B2.1): la remisión transiciona a REMITIDO_UNIDAD_EXTERNA; la
            // recepción restaura el estado_previo_id de la remisión abierta
            // (destino null: estado variable en runtime). Sin entrada en
            // MAPA_TIPO_PLAZO/MAPA_CIERRA_PLAZO: la congelación y reanudación
            // de relojes las hace TransferenciaUnidadService.
            ['codigo' => 'ACT_REMISION_UNIDAD', 'nombre' => 'Remisión a Unidad Externa', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => null, 'estado_destino_id' => $remitidoUnidadExterna->id, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada remite físicamente la causa a una unidad externa (RF-05); congela los relojes vigentes, guarda estado_previo_id y deja la causa en REMITIDO_UNIDAD_EXTERNA'],
            ['codigo' => 'ACT_RECEPCION_UNIDAD', 'nombre' => 'Recepción de Unidad Externa', 'fase' => 'INVESTIGACION', 'rol_id' => $encargada->id, 'reglamento_id' => null, 'estado_origen_id' => $remitidoUnidadExterna->id, 'estado_destino_id' => null, 'es_automatico' => false, 'requiere_adjunto' => false, 'descripcion' => 'La Encargada registra la recepción física desde la unidad externa (RF-05); restaura la fase procesal previa exacta y reanuda los relojes sin pérdida de días hábiles'],
        ];

        foreach ($actuados as $a) {
            CatalogoActuado::updateOrCreate(['codigo' => $a['codigo']], $a);
        }

        // Baja de los tipos del flujo B1.3 reemplazado por CTR-10 (B2.2,
        // reemplazo aprobado). Pivotes primero (FK) y luego la fila: si una BD
        // ya sembrada tiene NUREJ que emitieron estos actuados, la FK RESTRICT
        // de actuados.catalogo_actuado_id impedirá el borrado y el seeder
        // fallará en voz alta en vez de dejar datos huérfanos.
        foreach (['ACT_DERIVACION_INCOMPETENCIA', 'ACT_REMISION_TRANSPARENCIA'] as $codigoObsoleto) {
            $obsoleto = CatalogoActuado::where('codigo', $codigoObsoleto)->first();

            if ($obsoleto !== null) {
                DB::table('catalogo_actuado_roles')->where('catalogo_actuado_id', $obsoleto->id)->delete();
                $obsoleto->delete();
            }
        }

        $pivotes = [
            'ACT_REGISTRO_DIGITALIZACION' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => null],
            ],
            'ACT_SORTEO_INICIAL' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_OBSERVACION' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
            'ACT_SUBSANACION_ACEPTADA' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
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
            'ACT_DEVOLUCION_OBSERVACION' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_INFORME_FINAL' => [
                ['rol_id' => $audJuridico->id, 'reglamento_id' => null],
            ],
            'ACT_SOLICITAR_AMPLIACION' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ],
            'ACT_APROBAR_AMPLIACION' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_REMITIR_IMPUGNACION' => $this->perfilesEvaluacion($tecnico, $audJuridico, $audFinanciero, $ac022, $ac054, $ac055),
            'ACT_RESOLUCION_RATIFICA_RECHAZO' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_RESOLUCION_REVOCA_RECHAZO' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_CREACION_NUREJ_HIJO' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_VISTO_BUENO_FINAL' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_REPARTO_INSTITUCIONAL' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            // CTR-10 / B2.2: solicitud solo para los perfiles operativos
            // (transversal, reglamento NULL) y visto bueno exclusivo de la
            // Encargada; ningún operativo aprueba la remisión.
            'ACT_SOLICITUD_INCOMPETENCIA' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => null],
                ['rol_id' => $audJuridico->id, 'reglamento_id' => null],
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => null],
            ],
            'ACT_VB_INCOMPETENCIA' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_COMUNICACION_HALLAZGOS' => [
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => $ac055->id],
            ],
            'ACT_RECEPCION_DESCARGOS' => [
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => $ac055->id],
            ],
            'ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD' => [
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => $ac055->id],
            ],
            'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD' => [
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => $ac055->id],
            ],
            'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ],
            'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ],
            'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ],
            'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => $ac022->id],
            ],
            'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD' => [
                ['rol_id' => $audJuridico->id, 'reglamento_id' => $ac054->id],
            ],
            'ACT_INFORME_AUDITORIA_JURIDICA_SIN_RESPONSABILIDAD' => [
                ['rol_id' => $audJuridico->id, 'reglamento_id' => $ac054->id],
            ],
            // RF-02 / CTR-09: cualquier operativo con asignación activa o la
            // Encargada (reglamento NULL = cualquier Acuerdo); la condición de
            // asignación activa la resuelve ExpedientePolicy::crearActuado.
            'ACT_ENMIENDA' => [
                ['rol_id' => $tecnico->id, 'reglamento_id' => null],
                ['rol_id' => $audJuridico->id, 'reglamento_id' => null],
                ['rol_id' => $audFinanciero->id, 'reglamento_id' => null],
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            // RF-05 / CTR-04 y CTR-05: el par Remisión/Recepción es exclusivo
            // de la Encargada (supervisión distrital); ningún operativo.
            'ACT_REMISION_UNIDAD' => [
                ['rol_id' => $encargada->id, 'reglamento_id' => null],
            ],
            'ACT_RECEPCION_UNIDAD' => [
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
