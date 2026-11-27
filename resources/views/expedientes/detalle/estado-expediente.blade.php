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
                <p class="text-sm font-bold text-grafito" x-text="estadoPrincipal()"></p>
                <p class="text-[11px] text-gris mt-0.5">
                    Sub estado:
                    <span class="font-medium text-grafito" x-text="subestado()"></span>
                </p>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-gris-claro pt-3">
            <div class="min-w-0">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-gris">Fecha ingreso</dt>
                <dd class="text-sm text-grafito" x-text="formatDate(expediente.fecha_ingreso) || '—'"></dd>
            </div>
            <div class="min-w-0">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-gris">Reglamento</dt>
                <dd class="text-sm text-grafito" x-text="expediente.reglamento?.codigo || '—'"></dd>
            </div>
        </dl>

        <div class="border-t border-gris-claro pt-3 space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gris">Plazo de la fase actual</p>

            <template x-if="expediente.sem_plazo">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold text-grafito uppercase" x-text="plazoVigenteFase()?.tipo_plazo || 'Plazo'"></span>
                        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded text-[10px] font-bold border shrink-0"
                            :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                            <span class="rounded-full h-2 w-2 shrink-0" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                            <span x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                        </span>
                    </div>

                    <template x-if="!expediente.sem_plazo.es_fuera_de_plazo">
                        <div>
                            <p class="text-sm text-grafito">
                                <span class="text-lg font-bold" x-text="expediente.sem_plazo.dias_restantes ?? '—'"></span>
                                días hábiles restantes
                                <span class="text-gris" x-show="plazoVigenteFase()?.dias_habiles_otorgados">
                                    de <span x-text="plazoVigenteFase()?.dias_habiles_otorgados"></span> otorgados
                                </span>
                            </p>
                            <div class="mt-1.5 h-2 w-full rounded-full bg-gris-claro overflow-hidden" role="progressbar"
                                aria-label="Porcentaje de plazo consumido"
                                :aria-valuenow="Math.round((expediente.sem_plazo.porcentaje_consumido ?? 0) * 100)" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full transition-all duration-300"
                                    :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"
                                    :style="`width: ${Math.round((expediente.sem_plazo.porcentaje_consumido ?? 0) * 100)}%`"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="expediente.sem_plazo.es_fuera_de_plazo">
                        <p class="text-sm font-medium text-[#B53F12]">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Venció el <span class="font-bold" x-text="formatDate(expediente.sem_plazo.fecha_limite)"></span>
                        </p>
                    </template>
                </div>
            </template>

            <template x-if="!expediente.sem_plazo">
                <p class="text-xs text-gris">Sin plazo vigente para este expediente.</p>
            </template>
        </div>

        <div class="border-t border-gris-claro pt-3 space-y-2" x-show="esAc022()" x-cloak>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gris">Ampliación de plazo</p>
            <template x-if="tieneActuado('ACT_APROBAR_AMPLIACION')">
                <p class="text-xs font-semibold text-green-800"><i class="fa-solid fa-circle-check mr-1"></i> Ampliación de 5 días hábiles aprobada.</p>
            </template>
            <template x-if="!tieneActuado('ACT_APROBAR_AMPLIACION') && !tieneActuado('ACT_SOLICITAR_AMPLIACION')">
                <p class="text-xs text-gris">La ampliación excepcional de 5 días hábiles debe ser aprobada por la Encargada.</p>
            </template>
            <template x-if="tieneActuado('ACT_SOLICITAR_AMPLIACION') && !tieneActuado('ACT_APROBAR_AMPLIACION')">
                <p class="text-xs text-amber-800"><i class="fa-solid fa-hourglass-half mr-1"></i> Ampliación solicitada, en espera de la Encargada.</p>
            </template>
            <form @submit.prevent="submitAmpliacion()"
                x-show="!esEncargada() && pasoEditable() && estadoCodigo() === 'EN_EJECUCION' && !tieneActuado('ACT_SOLICITAR_AMPLIACION')"
                x-cloak class="space-y-2">
                <textarea x-model="formAmpliacion.justificacion" rows="2" minlength="10" required
                    title="Justificación de la solicitud de ampliación (obligatoria)"
                    placeholder="Fundamente la solicitud de ampliación"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"></textarea>
                <button type="submit" :disabled="submittingAmpliacion"
                    title="Solicita 5 días hábiles adicionales sujetos a aprobación de la Encargada"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-verde-institucional text-verde-profundo text-sm font-semibold hover:bg-verde-institucional/10 disabled:opacity-50">
                    <i class="fa-solid fa-paper-plane"></i> Solicitar ampliación
                </button>
            </form>
        </div>
    </div>
</div>
