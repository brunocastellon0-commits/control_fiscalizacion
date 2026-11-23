<div class="overflow-x-auto pb-1" role="tablist" aria-label="Pasos del expediente">
    <div class="flex items-start min-w-max">
        <template x-for="(paso, indice) in pasos" :key="paso.codigo">
            <button type="button" role="tab" @click="seleccionarPaso(paso)"
                :aria-selected="pasoActivo === paso.codigo ? 'true' : 'false'"
                :aria-disabled="paso.estado === 'bloqueado' ? 'true' : 'false'"
                :tabindex="paso.estado === 'bloqueado' ? -1 : 0"
                :title="paso.estado === 'bloqueado' ? paso.mensaje_bloqueo : `Paso ${paso.numero}: ${paso.nombre}. ${paso.descripcion || ''}`"
                class="group w-[132px] shrink-0 px-2 py-1 text-center transition-colors"
                :class="paso.estado === 'bloqueado' ? 'cursor-not-allowed' : ''">
                <span class="flex items-center w-full">
                    <span class="h-px flex-1"
                        :class="indice === 0
                            ? 'bg-transparent'
                            : (paso.estado === 'bloqueado' ? 'bg-gris-claro' : 'bg-verde-institucional/60')"></span>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs shrink-0 ring-2 ring-white"
                        :class="paso.estado === 'completado'
                            ? 'bg-verde-institucional text-white'
                            : (paso.estado === 'activo' ? 'bg-azul-petroleo text-white' : 'bg-gris-claro text-gris')">
                        <i class="fa-solid" :class="paso.estado === 'completado' ? 'fa-check' : (paso.estado === 'bloqueado' ? 'fa-lock' : 'fa-arrow-right')"></i>
                    </span>
                    <span class="h-px flex-1"
                        :class="indice === pasos.length - 1
                            ? 'bg-transparent'
                            : (pasos[indice + 1].estado === 'bloqueado' ? 'bg-gris-claro' : 'bg-verde-institucional/60')"></span>
                </span>
                <span class="mt-2 block text-[10px] font-bold uppercase tracking-wider text-gris">Paso <span x-text="paso.numero"></span></span>
                <span class="block text-xs font-semibold leading-tight"
                    :class="pasoActivo === paso.codigo
                        ? 'text-verde-profundo'
                        : (paso.estado === 'bloqueado' ? 'text-gris/60' : 'text-grafito')"
                    x-text="paso.nombre"></span>
                <span class="block text-[10px] font-normal leading-tight text-gris" x-text="paso.descripcion || ''"></span>
            </button>
        </template>
    </div>
</div>
