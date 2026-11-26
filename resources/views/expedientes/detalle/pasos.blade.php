<div class="px-1">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <p class="text-[11px] font-bold uppercase tracking-wider text-grafito"
            x-text="etiquetaProgresoPasos()"></p>
        <p class="text-[10px] font-semibold uppercase tracking-widest text-gris">Progreso del trámite</p>
    </div>

    <div class="overflow-x-auto px-1 pb-1 pt-2" role="tablist" aria-label="Pasos del expediente">
        <div class="relative min-w-[820px]">
            <div class="absolute left-[10%] right-[10%] top-[20px] h-0.5 bg-gris-claro" aria-hidden="true"></div>
            <div class="absolute left-[10%] top-[20px] h-0.5 bg-verde-institucional transition-all duration-300"
                :style="`width: calc(80% * ${progresoPasos()} / 100)`" aria-hidden="true"></div>

            <div class="relative grid grid-cols-5 gap-3">
                <template x-for="paso in pasos" :key="paso.codigo">
                    <button type="button" role="tab" @click="seleccionarPaso(paso)"
                        :aria-selected="pasoActivo === paso.codigo ? 'true' : 'false'"
                        :aria-current="paso.estado === 'activo' ? 'step' : null"
                        :aria-disabled="paso.estado === 'bloqueado' ? 'true' : 'false'"
                        :tabindex="paso.estado === 'bloqueado' ? -1 : 0"
                        :title="paso.estado === 'bloqueado'
                            ? paso.mensaje_bloqueo
                            : (paso.estado === 'completado'
                                ? `Paso ${paso.numero}: ${paso.nombre}. Completado, solo consulta.`
                                : `Paso ${paso.numero}: ${paso.nombre}. ${paso.descripcion || ''}`)"
                        class="group flex flex-col items-center px-1 pb-1 text-center focus:outline-none"
                        :class="paso.estado === 'bloqueado' ? 'cursor-not-allowed' : ''">
                        <span class="relative z-10 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                            :class="paso.estado === 'activo'
                                ? (paso.codigo === 'admisibilidad' && colorEvaluacion() === 'observacion'
                                    ? 'bg-amber-100'
                                    : (paso.codigo === 'admisibilidad' && colorEvaluacion() === 'rechazo'
                                        ? 'bg-red-100'
                                        : 'bg-[#dceaf6]'))
                                : 'bg-transparent'">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-bold"
                                :class="paso.estado === 'completado'
                                    ? 'border-verde-institucional bg-verde-institucional text-white'
                                    : (paso.estado === 'activo'
                                        ? (paso.codigo === 'admisibilidad' && colorEvaluacion() === 'observacion'
                                            ? 'border-amber-500 bg-amber-100 text-amber-800'
                                            : (paso.codigo === 'admisibilidad' && colorEvaluacion() === 'rechazo'
                                                ? 'border-red-500 bg-red-100 text-red-700'
                                                : 'border-azul-petroleo bg-azul-petroleo text-white'))
                                        : 'border-gris-claro bg-white text-gris')">
                                <template x-if="paso.estado === 'completado'">
                                    <i class="fa-solid fa-check"></i>
                                </template>
                                <template x-if="paso.estado === 'activo'">
                                    <span x-text="paso.numero"></span>
                                </template>
                                <template x-if="paso.estado === 'bloqueado'">
                                    <i class="fa-solid fa-lock text-[10px]"></i>
                                </template>
                            </span>
                            <template x-if="paso.estado === 'activo'">
                                <span class="absolute left-full top-1/2 ml-1 -translate-y-1/2 whitespace-nowrap rounded bg-azul-petroleo px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white">En curso</span>
                            </template>
                        </span>

                        <span class="mt-1 block text-[11px] font-semibold leading-tight"
                            :class="paso.estado === 'activo'
                                ? (paso.codigo === 'admisibilidad' && colorEvaluacion() === 'observacion'
                                    ? 'text-amber-800'
                                    : (paso.codigo === 'admisibilidad' && colorEvaluacion() === 'rechazo'
                                        ? 'text-red-700'
                                        : 'text-azul-petroleo'))
                                : (paso.estado === 'bloqueado' ? 'text-gris/60' : 'text-grafito')">
                            Paso <span x-text="paso.numero"></span>
                            <span class="mx-0.5 text-gris">|</span>
                            <span x-text="paso.nombre"></span>
                        </span>

                        <span class="mt-0.5 block text-[10px] font-normal leading-tight text-gris"
                            x-text="paso.descripcion || ''"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
