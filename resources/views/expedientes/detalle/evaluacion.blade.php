<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-clipboard-check text-verde-institucional mr-2"></i> Evaluación de admisibilidad</h3>
        <button type="button" @click="cargarRequisitos()" class="text-xs text-verde-profundo hover:underline"
            x-show="!cargandoRequisitos && pasoEditable()">
            <i class="fa-solid fa-rotate-right mr-1"></i>Actualizar
        </button>
    </div>

    <div class="border-b border-gris-claro bg-gris-claro/50 px-4 py-2 text-[11px] leading-snug text-gris">
        <i class="fa-solid fa-circle-info mr-1.5 text-verde-institucional"></i>Revise los requisitos según el reglamento de la causa. El sistema determina admisión, observación o rechazo y deja constancia inmutable de la evaluación.
    </div>

    <div class="p-4 space-y-4">
        <template x-if="pasoCompletado('admisibilidad')">
            <p class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800">
                <i class="fa-solid fa-lock mr-1"></i> Evaluación registrada. Los requisitos se muestran tal como se guardaron y no pueden modificarse.
            </p>
        </template>

        <template x-if="cargandoRequisitos">
            <p class="text-sm text-gris"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando requisitos del reglamento...</p>
        </template>

        <template x-if="!cargandoRequisitos && errorRequisitos">
            <div class="text-sm text-[#B53F12] bg-[#F15A24]/10 border border-[#F15A24]/20 rounded-lg p-3">
                <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorRequisitos"></span>
            </div>
        </template>

        <template x-if="!cargandoRequisitos && !errorRequisitos && requisitos.length === 0">
            <p class="text-sm text-gris">No hay requisitos configurados para evaluar este expediente o no tiene permisos para verlos.</p>
        </template>

        <template x-if="!cargandoRequisitos && requisitos.length > 0">
            <form @submit.prevent="submitEvaluacion()" class="space-y-3">
                <div class="space-y-2">
                    <template x-for="req in requisitos" :key="req.id">
                        <div class="border border-gris-claro rounded-lg p-3 bg-gris-claro/30">
                            <label class="flex items-start gap-3" :class="pasoEditable() ? 'cursor-pointer' : 'cursor-default'">
                                <input type="checkbox"
                                    class="mt-1 rounded border-gris-claro text-verde-institucional focus:ring-verde-institucional disabled:opacity-70 disabled:cursor-not-allowed"
                                    x-model="req._cumplido"
                                    :disabled="!pasoEditable() || (esSubsanacionPendiente() && !!documentosEvaluacion[req.id])">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-medium text-grafito" x-text="req.nombre || req.codigo || ('Requisito #' + req.id)"></span>
                                        <span x-show="req.es_critico" class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded bg-red-100 text-red-700 font-semibold">Crítico</span>
                                    </div>
                                    <p class="text-xs text-gris mt-0.5" x-show="req.descripcion" x-text="req.descripcion"></p>
                                </div>
                            </label>

                            <template x-if="pasoEditable() && !req._cumplido">
                                <div class="mt-3 pt-3 border-t border-gris-claro">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button"
                                            @click="document.getElementById('doc-eval-' + req.id).click()"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-verde-institucional text-xs font-medium text-verde-profundo hover:bg-verde-institucional/10">
                                            <i class="fa-solid fa-file-arrow-up"></i>
                                            <span x-text="documentosEvaluacion[req.id] ? 'Cambiar PDF' : (req.es_critico ? 'Impugnación' : 'Subir subsanación')"></span>
                                        </button>
                                        <input type="file" accept="application/pdf,.pdf" class="hidden"
                                            :id="'doc-eval-' + req.id"
                                            @change="seleccionarDocumentoEvaluacion(req, $event)">

                                        <template x-if="documentosEvaluacion[req.id]">
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-800">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <span class="font-semibold">PDF cargado:</span>
                                                <span class="max-w-[14rem] truncate" x-text="documentosEvaluacion[req.id].name"></span>
                                                <span class="font-normal text-green-700/80" x-text="tamanoDocumentoEvaluacion(req)"></span>
                                                <button type="button" @click="quitarDocumentoEvaluacion(req)"
                                                    class="ml-0.5 text-green-800 hover:text-green-950"
                                                    title="Quitar archivo" aria-label="Quitar archivo">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </span>
                                        </template>

                                        <span class="text-[11px] font-medium text-[#B53F12]"
                                            x-show="!documentosEvaluacion[req.id]">PDF pendiente</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-2 border-t border-gris-claro">
                    <p class="text-xs text-gris" x-text="documentosFaltantes().length > 0
                        ? 'Cargue un PDF junto a cada requisito no cumplido para habilitar el guardado.'
                        : (pasoEditable()
                            ? 'Marque los requisitos que se cumplen. El sistema determinará el resultado según la configuración.'
                            : 'Evaluación conservada para la trazabilidad del expediente.')"></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" x-show="estadoCodigo() === 'RECHAZADO' && pasoEditable()"
                            @click="remitirImpugnacion()" :disabled="submittingEvaluacion"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gris-claro bg-white text-xs font-medium text-grafito hover:bg-gris-claro/40 disabled:opacity-50">
                            <i class="fa-solid fa-paper-plane"></i> Remitir impugnación
                        </button>
                        <button type="submit" x-show="pasoEditable()" :disabled="!puedeGuardarEvaluacion()"
                            :title="puedeGuardarEvaluacion() ? 'Registrar la evaluación' : 'Cargue un PDF por cada requisito no cumplido para habilitar el guardado'"
                            class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional transition disabled:cursor-not-allowed disabled:opacity-50">
                            <i class="fa-solid mr-2" :class="submittingEvaluacion ? 'fa-spinner fa-spin' : 'fa-check-double'"></i>
                            <span x-text="submittingEvaluacion ? 'Guardando...' : 'Guardar evaluación'"></span>
                        </button>
                        <button type="button" x-show="esImpugnacionPendiente() && pasoEditable()"
                            @click="ratificarRechazo()" :disabled="submittingEvaluacion"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-300 bg-red-50 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-50">
                            <i class="fa-solid fa-gavel"></i> Ratificar rechazo
                        </button>
                    </div>
                </div>

                <template x-if="resultadoEvaluacion || resultadoEvaluacionGuardado()">
                    <div class="rounded-lg border p-3 text-sm"
                        :class="(resultadoEvaluacion || resultadoEvaluacionGuardado()).resultado === 'ACT_ADMISION'
                            ? 'bg-green-50 border-green-200 text-green-800'
                            : ((resultadoEvaluacion || resultadoEvaluacionGuardado()).resultado === 'ACT_OBSERVACION'
                                ? 'bg-amber-50 border-amber-200 text-amber-800'
                                : 'bg-red-50 border-red-200 text-red-800')">
                        <p class="font-semibold">Resultado: <span x-text="etiquetaResultadoEvaluacion((resultadoEvaluacion || resultadoEvaluacionGuardado()).resultado)"></span></p>
                        <p class="text-xs mt-1" x-text="detalleResultadoEvaluacion(resultadoEvaluacion || resultadoEvaluacionGuardado())"></p>
                    </div>
                </template>
            </form>
        </template>
    </div>
</div>
