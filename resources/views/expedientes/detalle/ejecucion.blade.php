<div class="space-y-4">
    <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
        <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-magnifying-glass-chart text-verde-institucional mr-2"></i> Ejecución de la investigación</h3>
            <span class="text-xs font-semibold px-2 py-1 rounded border"
                :class="pasoPorCodigo('ejecucion')?.estado === 'completado'
                    ? 'bg-green-50 border-green-200 text-green-800'
                    : (pasoPorCodigo('ejecucion')?.estado === 'activo'
                        ? 'bg-amber-50 border-amber-200 text-amber-800'
                        : 'bg-gris-claro border-gris-claro text-gris')"
                x-text="etiquetaPaso('ejecucion')"></span>
        </div>
        <div class="p-4 space-y-3">
            <p class="text-xs text-gris">
                Registre las diligencias de la investigación con «Emitir actuado». El plazo de ejecución corre desde que la Encargada aprueba el cronograma o MPA.
            </p>
            <template x-if="estadoCodigo() === 'PENDIENTE_APROBACION_AMPLIACION'">
                <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> Solicitud de ampliación enviada. En espera de aprobación de la Encargada.
                </p>
            </template>
            <template x-if="pasoEditable() && !esEncargada()">
                <div class="border-t border-gris-claro pt-4">
                    <button type="button" @click="abrirModalActuado()"
                        title="Registra una diligencia de la investigación como actuado inmutable"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional">
                        <i class="fa-solid fa-file-circle-plus"></i> Registrar diligencia
                    </button>
                </div>
            </template>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden" x-show="esAc022()" x-cloak>
        <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
            <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-clock-rotate-left text-verde-institucional mr-2"></i> Ampliación de plazo (Acuerdo 022)</h3>
        </div>
        <div class="p-4 space-y-3">
            <template x-if="tieneActuado('ACT_APROBAR_AMPLIACION')">
                <p class="text-xs font-semibold text-green-800"><i class="fa-solid fa-circle-check mr-1"></i> Ampliación de 5 días hábiles aprobada.</p>
            </template>
            <template x-if="!tieneActuado('ACT_APROBAR_AMPLIACION') && !tieneActuado('ACT_SOLICITAR_AMPLIACION')">
                <p class="text-xs text-gris">La ampliación excepcional de 5 días hábiles debe ser aprobada por la Encargada.</p>
            </template>
            <form @submit.prevent="submitAmpliacion()" x-show="!esEncargada() && pasoEditable() && estadoCodigo() === 'EN_EJECUCION' && !tieneActuado('ACT_SOLICITAR_AMPLIACION')"
                x-cloak class="space-y-3 border-t border-gris-claro pt-3">
                <div>
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1">Justificación <span class="text-[#F15A24]">*</span></label>
                    <textarea x-model="formAmpliacion.justificacion" rows="2" minlength="10" required
                        class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                        placeholder="Fundamente la solicitud de ampliación"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submittingAmpliacion"
                        title="Solicita 5 días hábiles adicionales sujetos a aprobación de la Encargada"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-verde-institucional text-verde-profundo text-sm font-semibold hover:bg-verde-institucional/10 disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane"></i> Solicitar ampliación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden" x-show="esAc055()" x-cloak>
        <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
            <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-scale-balanced text-verde-institucional mr-2"></i> Fase de descargos (Acuerdo 055)</h3>
        </div>
        <div class="p-4 space-y-4">
            <template x-if="!tieneActuado('ACT_COMUNICACION_HALLAZGOS')">
                <p class="text-xs text-gris">Comunique los hallazgos al auditado para iniciar el plazo de 5 días hábiles de descargos.</p>
            </template>
            <template x-if="tieneActuado('ACT_COMUNICACION_HALLAZGOS') && !tieneActuado('ACT_RECEPCION_DESCARGOS')">
                <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> Descargos en curso. El reloj principal de la auditoría está pausado.
                </p>
            </template>
            <template x-if="tieneActuado('ACT_RECEPCION_DESCARGOS')">
                <p class="text-xs font-semibold text-green-800"><i class="fa-solid fa-circle-check mr-1"></i> Descargos recibidos; la ejecución se reanudó.</p>
            </template>

            <form @submit.prevent="submitDescargos('comunicar')" x-show="!esEncargada() && pasoEditable() && !tieneActuado('ACT_COMUNICACION_HALLAZGOS')"
                x-cloak class="space-y-3 border-t border-gris-claro pt-3">
                <p class="text-sm font-semibold text-grafito">Comunicar hallazgos</p>
                <textarea x-model="formDescargos.comunicar.descripcion" rows="2" minlength="5" required
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Detalle de los hallazgos comunicados"></textarea>
                <input type="file" accept=".pdf,application/pdf" @change="archivoComunicacion = $event.target.files[0] || null" required
                    class="w-full text-xs text-gris">
                <div class="flex justify-end">
                    <button type="submit" :disabled="submittingDescargos"
                        title="Inicia los 5 días hábiles de descargos, solo para Auditoría Financiera"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane"></i> Comunicar hallazgos
                    </button>
                </div>
            </form>

            <form @submit.prevent="submitDescargos('recibir')" x-show="!esEncargada() && pasoEditable() && tieneActuado('ACT_COMUNICACION_HALLAZGOS') && !tieneActuado('ACT_RECEPCION_DESCARGOS')"
                x-cloak class="space-y-3 border-t border-gris-claro pt-3">
                <p class="text-sm font-semibold text-grafito">Registrar recepción de descargos</p>
                <textarea x-model="formDescargos.recibir.descripcion" rows="2" minlength="5" required
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Detalle de los descargos presentados"></textarea>
                <input type="file" accept=".pdf,application/pdf" @change="archivoRecepcion = $event.target.files[0] || null" required
                    class="w-full text-xs text-gris">
                <div class="flex justify-end">
                    <button type="submit" :disabled="submittingDescargos"
                        title="Cierra la fase de descargos y habilita el Informe Final"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                        <i class="fa-solid fa-inbox"></i> Registrar descargos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
