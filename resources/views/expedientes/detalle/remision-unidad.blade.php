<div x-show="modalRemisionOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-grafito/60 p-4" x-cloak>
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-gris-claro" @click.away="cerrarModalRemision()">
        <div class="bg-verde-profundo text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-base" x-text="modoRemision === 'remitir' ? 'Remitir a unidad externa' : 'Registrar recepción de unidad'"></h3>
            <button type="button" @click="cerrarModalRemision()" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form @submit.prevent="submitRemision()" class="p-6 space-y-4">
            <p class="text-sm text-gris" x-show="modoRemision === 'remitir'">La causa pasará a estado de remitida a unidad externa. Solo la Encargada puede realizar esta acción.</p>
            <p class="text-sm text-gris" x-show="modoRemision === 'recepcion'">Registre el reingreso físico de la causa devuelta por la unidad externa.</p>

            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1" x-text="modoRemision === 'remitir' ? 'Unidad destinataria (ID)' : 'Unidad de origen (ID)'"></label>
                <input type="number" min="1" x-model="formRemision.unidad_id" required
                    title="Identificador de la unidad externa según el catálogo institucional."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Identificador de la unidad externa">
                <p class="text-xs text-gris mt-1">Ingrese el identificador de la unidad según el catálogo institucional.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Motivo <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formRemision.motivo" required rows="3" maxlength="1000"
                    title="Motivo de la remisión o recepción; quedará registrado en el historial del expediente."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Adjunto (opcional, PDF)</label>
                <input type="file" id="adjuntoRemision" accept="application/pdf" @change="manejarArchivoRemision($event)"
                    title="Adjunto opcional en PDF para respaldar la remisión o recepción."
                    class="w-full text-sm text-gris file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gris-claro file:text-grafito">
            </div>

            <p x-show="errorRemision" class="text-sm text-[#B53F12] bg-[#F15A24]/10 border border-[#F15A24]/20 rounded-lg p-3">
                <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorRemision"></span>
            </p>

            <div class="flex justify-end gap-3 pt-4 border-t border-gris-claro">
                <button type="button" @click="cerrarModalRemision()" class="px-4 py-2 bg-gris-claro text-grafito rounded-lg text-sm font-medium">Cancelar</button>
                <button type="submit" :disabled="submittingRemision" class="px-6 py-2 bg-verde-profundo text-white rounded-lg text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                    <i class="fa-solid" :class="submittingRemision ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    <span class="ml-2" x-text="submittingRemision ? 'Procesando...' : (modoRemision === 'remitir' ? 'Remitir causa' : 'Registrar recepción')"></span>
                </button>
            </div>
        </form>
    </div>
</div>
