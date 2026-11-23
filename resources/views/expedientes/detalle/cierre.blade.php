<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-flag-checkered text-verde-institucional mr-2"></i> Cierre del expediente</h3>
        <span class="text-xs font-semibold px-2 py-1 rounded border"
            :class="pasoPorCodigo('cierre')?.estado === 'completado'
                ? 'bg-green-50 border-green-200 text-green-800'
                : (pasoPorCodigo('cierre')?.estado === 'activo'
                    ? 'bg-amber-50 border-amber-200 text-amber-800'
                    : 'bg-gris-claro border-gris-claro text-gris')"
            x-text="etiquetaPaso('cierre')"></span>
    </div>

    <div class="p-4 space-y-4">
        <p class="text-xs text-gris">
            El informe final se registra como actuado inmutable. Una vez firmado, la Encargada otorga el Visto Bueno Final y ejecuta el reparto institucional.
        </p>

        <template x-if="pasoPorCodigo('cierre')?.estado === 'completado'">
            <p class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800">
                <i class="fa-solid fa-circle-check mr-1"></i> El expediente concluyó su tramitación en esta vía.
            </p>
        </template>

        <template x-if="!esEncargada() && !tieneInformeFinal()">
            <div class="border-t border-gris-claro pt-4 space-y-3">
                <p class="text-sm font-semibold text-grafito">Emitir informe final</p>
                <p class="text-xs text-gris" x-show="actuadosFinales().length === 0">No hay informes finales habilitados para su rol en el estado actual del expediente.</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="actuado in actuadosFinales()" :key="actuado.id">
                        <button type="button" @click="abrirModalActuado(actuado.id)"
                            :title="`Registra ${actuado.nombre} como actuado inmutable`"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-verde-profundo text-white text-xs font-semibold hover:bg-verde-institucional">
                            <i class="fa-solid fa-file-circle-plus"></i>
                            <span x-text="actuado.nombre"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="!esEncargada() && tieneInformeFinal() && pasoPorCodigo('cierre')?.estado === 'activo'">
            <p class="border-t border-gris-claro pt-4 text-xs text-gris">
                <i class="fa-solid fa-hourglass-half mr-1"></i> Informe final registrado. En espera del Visto Bueno de la Encargada.
            </p>
        </template>

        <template x-if="esEncargada() && estadoCodigo() === 'PENDIENTE_VISTO_BUENO_FINAL'">
            <div class="space-y-3 border-t border-gris-claro pt-4">
                <p class="text-sm font-semibold text-grafito">Visto Bueno Final</p>
                <textarea x-model="formVistoBuenoFinal.descripcion" rows="2" minlength="10"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Descripción de la aprobación del informe final"></textarea>
                <div class="flex justify-end">
                    <button type="button" @click="submitVistoBuenoFinal()" :disabled="submittingVistoBuenoFinal"
                        title="Aprueba el Informe Final y habilita la salida externa"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                        <i class="fa-solid fa-check"></i> Aprobar informe final
                    </button>
                </div>
            </div>
        </template>

        <template x-if="esEncargada() && estadoCodigo() === 'LISTO_PARA_REPARTO'">
            <form @submit.prevent="submitReparto()" class="space-y-3 border-t border-gris-claro pt-4">
                <p class="text-sm font-semibold text-grafito">Reparto institucional</p>
                <div>
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1">Destino <span class="text-[#F15A24]">*</span></label>
                    <select x-model="formReparto.destino" required
                        class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
                        <option value="">Seleccione un destino</option>
                        <template x-for="destino in destinosReparto" :key="destino">
                            <option :value="destino" x-text="destino"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1">Justificación <span class="text-[#F15A24]">*</span></label>
                    <textarea x-model="formReparto.justificacion" rows="2" minlength="10" required
                        class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"
                        placeholder="Fundamento del reparto"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submittingReparto"
                        title="Selecciona el destino exacto y cierra el NUREJ"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane"></i> Ejecutar reparto
                    </button>
                </div>
            </form>
        </template>
    </div>
</div>
