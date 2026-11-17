<div x-show="modalActuadoOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-grafito/60 p-4" x-cloak>
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-gris-claro" @click.away="cerrarModalActuado()">
        <div class="bg-verde-profundo text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-base"><i class="fa-solid fa-file-signature text-verde-institucional mr-2"></i> Emitir actuado</h3>
            <button type="button" @click="cerrarModalActuado()" class="text-white/70 hover:text-white" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form @submit.prevent="submitActuado()" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Tipo de actuado <span class="text-[#F15A24]">*</span></label>
                <select x-model="formActuado.catalogo_actuado_id" required @change="verificarRequisitoAdjunto()"
                    title="Solo aparecen las actuaciones permitidas desde el estado actual del expediente."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional text-grafito bg-white">
                    <option value="">-- Seleccione una acción permitida --</option>
                    <template x-for="cat in catalogoActuados" :key="cat.id">
                        <option :value="cat.id" x-text="cat.nombre + (cat.codigo ? ' (' + cat.codigo + ')' : '')"></option>
                    </template>
                </select>
                <p x-show="cargandoCatalogoActuados" class="text-xs text-gris mt-1"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Cargando actuaciones disponibles...</p>
                <p x-show="!cargandoCatalogoActuados && catalogoActuados.length === 0" class="text-xs text-amber-700 mt-1">
                    No hay actuaciones disponibles para este expediente o su estado actual.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Descripción / proveído <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formActuado.descripcion" required rows="3"
                    title="Resumen verificable de la actuación; queda registrado en la cadena de custodia."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional text-grafito"
                    placeholder="Describa brevemente la actuación realizada."></textarea>
            </div>

            <div x-show="requiereAdjunto">
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Documento de respaldo (PDF) <span class="text-[#F15A24]">*</span></label>
                <input type="file" id="adjuntoActuado" accept="application/pdf" @change="manejarArchivoActuado($event)"
                    :required="requiereAdjunto"
                    title="PDF obligatorio para las actuaciones que generan documento de respaldo."
                    class="w-full text-sm text-gris file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gris-claro file:text-grafito hover:file:bg-[#F15A24]/10">
                <p class="text-xs text-gris mt-1">Este tipo de actuado requiere un archivo PDF adjunto.</p>
            </div>

            <p x-show="errorActuado" class="text-sm text-[#B53F12] bg-[#F15A24]/10 border border-[#F15A24]/20 rounded-lg p-3">
                <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorActuado"></span>
            </p>

            <div class="flex justify-end gap-3 pt-4 border-t border-gris-claro mt-2">
                <button type="button" @click="cerrarModalActuado()" class="px-4 py-2 bg-gris-claro text-grafito rounded-lg text-sm font-medium hover:bg-[#F15A24]/10">Cancelar</button>
                <button type="submit" :disabled="submittingActuado || !formActuado.catalogo_actuado_id"
                    class="px-6 py-2 bg-verde-profundo text-white rounded-lg text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid" :class="submittingActuado ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                    <span class="ml-2" x-text="submittingActuado ? 'Guardando...' : 'Guardar actuado'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
