<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
    <div class="bg-gris-claro px-4 py-3 rounded-t-xl border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-circle-check text-verde-institucional mr-2"></i> Estado del expediente</h3>
    </div>
    <div class="p-4 space-y-4">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-verde-institucional/10 text-verde-profundo">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-grafito" x-text="expediente.estado_actual?.nombre || expediente.estado_actual?.codigo || 'Sin estado'"></p>
                <p class="text-xs text-gris mt-0.5">
                    Etapa actual: <span class="font-medium text-grafito" x-text="pasoPorCodigo(pasoActivo) ? pasoPorCodigo(pasoActivo).nombre : '—'"></span>
                </p>
            </div>
        </div>

        <template x-if="expediente.sem_plazo">
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg border" :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                <span class="relative inline-flex rounded-full h-3 w-3 shrink-0" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-bold uppercase tracking-wide" x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                    <span class="text-[11px] font-medium" x-show="expediente.sem_plazo.dias_restantes !== null && expediente.sem_plazo.dias_restantes !== undefined">
                        <span x-text="expediente.sem_plazo.dias_restantes"></span> días hábiles restantes
                    </span>
                </div>
            </div>
        </template>

        <template x-if="plazosOrdenados().length > 0">
            <div class="border-t border-gris-claro pt-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gris mb-2">Plazos</p>
                <ul class="space-y-2">
                    <template x-for="plazo in plazosOrdenados().slice(0, 3)" :key="plazo.id || plazo.tipo_plazo + plazo.fecha_limite">
                        <li class="flex items-center justify-between gap-2 text-xs">
                            <span class="text-grafito truncate" x-text="plazo.tipo_plazo || 'Plazo'"></span>
                            <span class="text-gris shrink-0" x-text="formatDate(plazo.fecha_limite)"></span>
                        </li>
                    </template>
                </ul>
                <button type="button" @click="seleccionarSeccion('historial'); seguimientoPestana = 'plazos'"
                    title="Muestra el detalle completo de plazos y su semáforo"
                    class="mt-2 text-xs text-verde-profundo hover:underline">
                    Ver todos los plazos <i class="fa-solid fa-arrow-right ml-1"></i>
                </button>
            </div>
        </template>

        <button type="button" @click="irAEtapaActual()"
            :title="pasoPorCodigo(pasoActivo) ? 'Abre la etapa ' + pasoPorCodigo(pasoActivo).nombre + ' del expediente' : 'Abre la etapa actual del expediente'"
            class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium hover:bg-verde-institucional transition">
            <i class="fa-solid fa-arrow-right"></i>
            <span x-text="etiquetaBotonEtapa()"></span>
        </button>
    </div>
</div>
