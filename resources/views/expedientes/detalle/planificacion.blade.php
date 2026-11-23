<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-calendar-days text-verde-institucional mr-2"></i> Planificación</h3>
        <span class="text-xs font-semibold px-2 py-1 rounded border"
            :class="pasoPorCodigo('planificacion')?.estado === 'completado'
                ? 'bg-green-50 border-green-200 text-green-800'
                : (pasoPorCodigo('planificacion')?.estado === 'activo'
                    ? 'bg-amber-50 border-amber-200 text-amber-800'
                    : 'bg-gris-claro border-gris-claro text-gris')"
            x-text="etiquetaPaso('planificacion')"></span>
    </div>

    <div class="p-4 space-y-4">
        <p class="text-xs text-gris">
            El Técnico carga el Cronograma de Trabajo (Acuerdo 022) y los Auditores registran el MPA (Acuerdos 054 y 055). La Encargada debe otorgar el Visto Bueno para iniciar la investigación.
        </p>

        <template x-if="actuadoDe('ACT_CRONOGRAMA_TRABAJO') || actuadoDe('ACT_MPA')">
            <div class="rounded-lg border border-gris-claro bg-gris-claro/30 p-4 space-y-2">
                <p class="text-xs font-bold uppercase tracking-wider text-verde-profundo">
                    Documento presentado: <span x-text="actuadoDe('ACT_CRONOGRAMA_TRABAJO')?.tipo_actuado?.nombre || actuadoDe('ACT_MPA')?.tipo_actuado?.nombre"></span>
                </p>
                <p class="text-sm text-gris whitespace-pre-line" x-text="(actuadoDe('ACT_CRONOGRAMA_TRABAJO') || actuadoDe('ACT_MPA'))?.descripcion || ''"></p>
                <template x-if="(actuadoDe('ACT_CRONOGRAMA_TRABAJO') || actuadoDe('ACT_MPA'))?.adjuntos?.length">
                    <a :href="`/api/adjuntos/${(actuadoDe('ACT_CRONOGRAMA_TRABAJO') || actuadoDe('ACT_MPA')).adjuntos[0].id}/descargar`" target="_blank"
                        class="inline-flex items-center gap-2 text-xs text-verde-institucional hover:underline">
                        <i class="fa-solid fa-file-pdf"></i> Ver documento adjunto
                    </a>
                </template>
                <template x-if="tieneActuado('ACT_VISTO_BUENO_PLANIFICACION')">
                    <p class="text-xs font-semibold text-green-800"><i class="fa-solid fa-circle-check mr-1"></i> Visto Bueno aprobado por la Encargada.</p>
                </template>
            </div>
        </template>

        <form @submit.prevent="submitPlanificacion()" x-show="pasoActivo === 'planificacion' && !esEncargada() && pasoPorCodigo('planificacion')?.estado === 'activo'"
            x-cloak class="space-y-3 border-t border-gris-claro pt-4">
            <p class="text-sm font-semibold text-grafito" x-text="esAc022() ? 'Cargar Cronograma de Trabajo' : 'Registrar MPA'"></p>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Descripción <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formPlanificacion.descripcion" rows="3" minlength="10" required
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Alcance y actividades previstas"></textarea>
            </div>
            <div x-show="!esAc022()">
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Fecha límite propuesta <span class="text-[#F15A24]">*</span></label>
                <input type="date" x-model="formPlanificacion.fecha_limite_propuesta" :required="!esAc022()"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Documento PDF <span class="text-[#F15A24]">*</span></label>
                <input type="file" accept=".pdf,application/pdf" @change="archivoPlanificacion = $event.target.files[0] || null" required
                    class="w-full text-xs text-gris">
                <p class="mt-1 text-[11px] text-gris">PDF, máximo 20 MB.</p>
            </div>
            <p x-show="errorPlanificacion" x-text="errorPlanificacion" class="text-sm text-[#B53F12]"></p>
            <div class="flex justify-end">
                <button type="submit" :disabled="submittingPlanificacion"
                    title="Registra el Cronograma o MPA y lo envía a Visto Bueno"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                    <i class="fa-solid" :class="submittingPlanificacion ? 'fa-spinner fa-spin' : 'fa-upload'"></i>
                    <span x-text="submittingPlanificacion ? 'Guardando...' : (esAc022() ? 'Subir cronograma' : 'Registrar MPA')"></span>
                </button>
            </div>
        </form>

        <template x-if="pasoActivo === 'planificacion' && esEncargada() && estadoCodigo() === 'PENDIENTE_VISTO_BUENO'">
            <div class="space-y-3 border-t border-gris-claro pt-4">
                <p class="text-sm font-semibold text-grafito">Visto Bueno a la planificación</p>
                <textarea x-model="formVistoBuenoPlanificacion.descripcion" rows="2" minlength="5"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Descripción de la aprobación"></textarea>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="submitVistoBuenoPlanificacion()" :disabled="submittingVistoBuenoPlanificacion"
                        title="Autoriza el inicio de la investigación y activa el reloj de ejecución"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                        <i class="fa-solid fa-check"></i> Aprobar planificación
                    </button>
                    <button type="button" @click="submitDevolverPlanificacion()" :disabled="submittingDevolverPlanificacion"
                        title="Observa el plan y lo devuelve al operador sin activar el reloj"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-sm font-semibold hover:bg-amber-100 disabled:opacity-50">
                        <i class="fa-solid fa-rotate-left"></i> Devolver con observaciones
                    </button>
                </div>
            </div>
        </template>

        <template x-if="pasoActivo === 'planificacion' && esEncargada() && estadoCodigo() !== 'PENDIENTE_VISTO_BUENO'">
            <p class="border-t border-gris-claro pt-4 text-xs text-gris">
                La planificación es registrada por el operador asignado. Usted interviene únicamente al otorgar el Visto Bueno.
            </p>
        </template>
    </div>
</div>
