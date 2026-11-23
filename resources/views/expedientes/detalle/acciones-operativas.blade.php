<div class="bg-white rounded-xl border border-gris-claro shadow-sm">
    <div class="bg-gris-claro px-4 py-3 rounded-t-xl border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-bolt text-verde-institucional mr-2"></i> Acciones disponibles</h3>
    </div>
    <div class="p-4 space-y-3">
        <p class="text-xs text-gris">Las acciones dependen de su rol, el estado del expediente y las reglas del sistema. Si una acción no está permitida, el sistema le informará.</p>

        <div class="flex flex-wrap gap-2">
            <template x-if="catalogoCargado && catalogoActuados.length > 0">
                <span class="relative inline-flex group">
                    <button type="button" @click="abrirModalActuado()"
                        :disabled="pasoActivo === 'recepcion' || estadoFinal()"
                        title="Registra un actuado formal según la etapa; no edita ni borra el historial"
                        class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-verde-profundo text-white text-xs font-medium hover:bg-verde-institucional transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-file-circle-plus"></i> Emitir actuado
                    </button>
                    <span role="tooltip"
                        class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-60 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                        Registra un actuado formal según la etapa. No edita ni borra el historial.
                    </span>
                </span>
            </template>

            <span class="relative inline-flex group">
                <button type="button" @click="abrirModalEnmienda()" :disabled="!puedeEnmendar()"
                    :title="puedeEnmendar()
                        ? 'Corrige un dato de carátula guardando el valor original y el nuevo'
                        : 'La enmienda se habilita desde Admisibilidad y antes del cierre'"
                    class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-azul-petroleo text-white text-xs font-medium hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-pen-to-square"></i> Registrar enmienda
                </button>
                <span role="tooltip"
                    class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-60 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                    Corrige un dato de la carátula guardando el valor original y el nuevo. No altera el historial.
                </span>
            </span>

            <span class="relative inline-flex group">
                <button type="button" @click="abrirModalIncompetencia()" :disabled="!puedeSolicitarIncompetencia()"
                    :title="puedeSolicitarIncompetencia()
                        ? 'Deriva por indicios penales a Transparencia y pide visto bueno inmediato'
                        : 'La solicitud se habilita desde Admisibilidad y antes del cierre'"
                    class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-xs font-medium hover:bg-amber-100 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-share-from-square"></i> Solicitar incompetencia
                </button>
                <span role="tooltip"
                    class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-64 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                    Deriva el expediente a Transparencia por indicios penales. Congela los plazos y pide el Visto Bueno inmediato de la Encargada.
                </span>
            </span>

            <template x-if="rolUsuarioActual() === 'ENCARGADA'">
                <div class="contents">
                    <span class="relative inline-flex group">
                        <button type="button" @click="abrirModalRemision('remitir')" :disabled="estadoFinal()"
                            title="Registra la salida del expediente hacia otra unidad"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gris-claro bg-white text-grafito text-xs font-medium hover:bg-gris-claro/40 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-paper-plane"></i> Remitir a unidad externa
                        </button>
                        <span role="tooltip" class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-56 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                            Registra la remisión y deja el trámite pendiente de recepción en la unidad destino.
                        </span>
                    </span>

                    <span class="relative inline-flex group">
                        <button type="button" @click="abrirModalRemision('recepcion')"
                            :disabled="estadoCodigo() !== 'REMITIDO_UNIDAD_EXTERNA'"
                            title="Confirma la llegada del expediente derivado y cierra la transferencia"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gris-claro bg-white text-grafito text-xs font-medium hover:bg-gris-claro/40 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-inbox"></i> Registrar recepción
                        </button>
                        <span role="tooltip" class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-56 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                            Confirma la recepción del expediente derivado y restablece la etapa previa.
                        </span>
                    </span>

                    <span class="relative inline-flex group">
                        <button type="button" @click="resolverIncompetencia(true)" :disabled="estadoFinal()"
                            title="Confirma la derivación por incompetencia propuesta por el operador"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-800 text-xs font-medium hover:bg-green-100 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-check"></i> Aprobar incompetencia
                        </button>
                        <span role="tooltip" class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-56 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                            Acepta la solicitud y habilita la remisión a Transparencia.
                        </span>
                    </span>

                    <span class="relative inline-flex group">
                        <button type="button" @click="resolverIncompetencia(false)" :disabled="estadoFinal()"
                            title="Deniega la derivación por incompetencia y devuelve el expediente al operador"
                            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-red-300 bg-red-50 text-red-800 text-xs font-medium hover:bg-red-100 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-xmark"></i> Rechazar incompetencia
                        </button>
                        <span role="tooltip" class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-56 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                            Observa la solicitud y la devuelve al operador para su corrección.
                        </span>
                    </span>
                </div>
            </template>
        </div>

        <div x-show="modalIncompetenciaOpen" class="border border-gris-claro rounded-lg p-4 bg-gris-claro/30 space-y-3" x-cloak>
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-grafito">Solicitud de incompetencia</h4>
                <button type="button" @click="modalIncompetenciaOpen = false" title="Cerrar solicitud" aria-label="Cerrar solicitud" class="text-gris hover:text-grafito"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Unidad de destino (ID)</label>
                <input type="number" min="1" x-model="formIncompetencia.unidad_destino_id"
                    title="Identificador de la unidad de destino de la derivación"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Motivo / justificación <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formIncompetencia.motivo" rows="3" required
                    title="Explique el indicio penal o de corrupción que motiva la derivación"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalIncompetenciaOpen = false" title="Descartar la solicitud" class="px-2.5 py-1.5 rounded-lg bg-white border border-gris-claro text-sm">Cancelar</button>
                <button type="button" @click="submitIncompetenciaSolicitud()" :disabled="submittingIncompetencia"
                    title="Envía la solicitud de incompetencia para el visto bueno de la Encargada"
                    class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 disabled:opacity-50">
                    <span x-text="submittingIncompetencia ? 'Enviando...' : 'Enviar solicitud'"></span>
                </button>
            </div>
        </div>

        <p class="text-xs text-gris">Sugerencia: use “Emitir actuado” para las actuaciones permitidas según el estado y su rol. Las acciones específicas (enmienda, incompetencia, remisión/recepción) se registran con su respaldo correspondiente.</p>
    </div>
</div>
