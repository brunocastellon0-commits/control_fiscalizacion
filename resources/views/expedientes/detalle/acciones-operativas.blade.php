<div class="bg-white rounded-xl border border-gris-claro shadow-sm p-4 space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <template x-if="pasoSiguiente()">
            <span class="relative inline-flex group">
                <button type="button" @click="irASiguientePaso()"
                    :disabled="!puedeAvanzarPaso()"
                    :title="puedeAvanzarPaso()
                        ? `Avanza al paso ${pasoSiguiente().numero}: ${pasoSiguiente().nombre}`
                        : 'Complete y guarde el trabajo de esta etapa para habilitar el avance'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional transition disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-text="etiquetaSiguientePaso()"></span>
                    <i class="fa-solid" :class="puedeAvanzarPaso() ? 'fa-arrow-right' : 'fa-lock'"></i>
                </button>
                <span role="tooltip"
                    class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-64 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                    <span x-show="!puedeAvanzarPaso()">Complete y guarde el trabajo de esta etapa para habilitar el avance.</span>
                    <span x-show="puedeAvanzarPaso()">Avanza al siguiente paso del trámite.</span>
                </span>
            </span>
        </template>

        <template x-if="!esEncargada() && catalogoCargado && catalogoActuados.length > 0">
            <span class="relative inline-flex group">
                <button type="button" @click="abrirModalActuado()"
                    :disabled="!pasoEditable()"
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

        <template x-if="!esEncargada() && fasePendiente() && !estadoFinal()">
            <span class="relative inline-flex group">
                <button type="button" @click="pasarDeFase()"
                    :disabled="submittingPasarFase"
                    title="Aplica el cambio de fase pendiente registrado en el último actuado"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-azul-petroleo text-white text-xs font-semibold hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid" :class="submittingPasarFase ? 'fa-spinner fa-spin' : 'fa-forward-step'"></i>
                    <span x-text="submittingPasarFase ? 'Pasando de fase...' : `Pasar de fase (${fasePendiente().estado_nuevo.nombre})`"></span>
                </button>
                <span role="tooltip"
                    class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-64 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                    Aplica la transición pendiente hacia la siguiente fase del expediente.
                </span>
            </span>
        </template>

        <template x-if="!esEncargada()">
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
        </template>

        <template x-if="!esEncargada()">
            <span class="relative inline-flex group">
                <button type="button" @click="abrirModalIncompetencia()" :disabled="!puedeSolicitarIncompetencia()"
                    :title="puedeSolicitarIncompetencia()
                        ? 'Solicita la derivación a la Unidad de Transparencia (Ley N° 974) y pide visto bueno'
                        : 'La solicitud se habilita desde Admisibilidad y antes del cierre'"
                    class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gris-claro bg-white text-grafito text-xs font-medium hover:bg-gris-claro/40 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-share-from-square text-azul-petroleo"></i> Derivación a Transparencia
                </button>
                <span role="tooltip"
                    class="pointer-events-none absolute top-full left-0 z-40 mt-2 w-64 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                    Solicita derivar el expediente a la Unidad de Transparencia (Ley N° 974). Congela los plazos y pide el Visto Bueno de la Encargada.
                </span>
            </span>
        </template>

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
            </div>
        </template>
    </div>
</div>
