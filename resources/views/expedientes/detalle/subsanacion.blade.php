<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden"
    x-show="estadoCodigo() === 'EN_SUBSANACION' && catalogoActuados.some(c => c.codigo === 'ACT_SUBSANACION_ACEPTADA')"
    x-cloak>
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-grafito uppercase">
            <i class="fa-solid fa-file-pen text-verde-institucional mr-2"></i> Subsanación
        </h3>
        <button type="button" x-show="observacionActuada()" @click="imprimirFormatoObservacion(observacionActuada())"
            title="Imprime el formato de observación entregado al interesado"
            class="text-xs text-verde-profundo hover:underline">
            <i class="fa-solid fa-print mr-1"></i>Imprimir formato
        </button>
    </div>

    <div class="p-4 space-y-4">
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <i class="fa-solid fa-clock mr-1"></i>
            El expediente espera la subsanación presentada por el interesado.
            <span x-show="plazoVigenteFase()">Plazo vigente hasta el
                <strong x-text="formatDate(plazoVigenteFase()?.fecha_limite)"></strong>
                (<span x-text="plazoVigenteFase()?.dias_habiles_otorgados"></span> días hábiles).</span>
            <span x-show="!plazoVigenteFase()">Sin plazo de subsanación vigente.</span>
        </p>

        <form @submit.prevent="aceptarSubsanacion()" class="space-y-3">
            <div>
                <label for="subs-descripcion" class="block text-xs font-semibold text-grafito uppercase mb-1">
                    Descripción de la aceptación
                </label>
                <textarea id="subs-descripcion" x-model="formSubsanacion.descripcion" rows="2" maxlength="10000"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 text-grafito focus:border-verde-institucional focus:ring-verde-institucional focus:outline-none"
                    placeholder="Describa la subsanación presentada y su verificación."></textarea>
            </div>

            <div>
                <label for="subs-adjunto" class="block text-xs font-semibold text-grafito uppercase mb-1">
                    Documento de subsanación (PDF, máx. 20 MB)
                </label>
                <input id="subs-adjunto" type="file" accept="application/pdf" required
                    @change="archivoSubsanacion = $event.target.files[0] || null"
                    class="block w-full text-xs text-grafito file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-verde-profundo file:text-white file:text-xs file:font-semibold hover:file:bg-verde-institucional">
            </div>

            <template x-if="errorSubsanacion">
                <p class="text-xs text-[#B53F12] bg-[#F15A24]/10 border border-[#F15A24]/20 rounded-lg px-3 py-2">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorSubsanacion"></span>
                </p>
            </template>

            <div class="flex justify-end">
                <button type="submit"
                    :disabled="submittingSubsanacion || !formSubsanacion.descripcion || !archivoSubsanacion"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:bg-verde-institucional transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid" :class="submittingSubsanacion ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    <span x-text="submittingSubsanacion ? 'Registrando...' : 'Aceptar subsanación'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
