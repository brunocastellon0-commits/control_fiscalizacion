<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
    <div class="bg-gris-claro px-4 py-3 rounded-t-xl border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-circle-check text-verde-institucional mr-2"></i> Estado del expediente</h3>
    </div>
    <div class="p-4">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-verde-institucional/10 text-verde-profundo">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-grafito" x-text="expediente.estado_actual?.nombre || expediente.estado_actual?.codigo || 'Sin estado'"></p>
                <p class="text-[11px] text-gris mt-0.5">Estado actual del trámite</p>
            </div>
        </div>

        <template x-if="expediente.sem_plazo">
            <div class="mt-4 flex items-center gap-3 px-3 py-2.5 rounded-lg border" :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                <span class="relative inline-flex rounded-full h-3 w-3 shrink-0" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-bold uppercase tracking-wide" x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                    <span class="text-[11px] font-medium"
                        x-show="expediente.sem_plazo.dias_restantes !== null && expediente.sem_plazo.dias_restantes !== undefined">
                        <span x-text="expediente.sem_plazo.dias_restantes"></span> días hábiles restantes
                    </span>
                </div>
            </div>
        </template>
    </div>
</div>
