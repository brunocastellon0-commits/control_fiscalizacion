<div x-show="modalEnmiendaActuadoOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-grafito/60 p-4" x-cloak>
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-gris-claro" @click.away="cerrarModalEnmiendaActuado()">
        <div class="bg-verde-profundo text-white px-5 py-3 flex items-center justify-between">
            <h3 class="text-sm font-bold uppercase"><i class="fa-solid fa-pen-to-square mr-2"></i> Enmienda de actuado</h3>
            <button type="button" @click="cerrarModalEnmiendaActuado()" class="text-white/70 hover:text-white" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form @submit.prevent="submitEnmiendaActuado()" class="p-6 space-y-4">
            <div class="bg-gris-claro/50 border border-gris-claro rounded-lg p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gris mb-1">Descripción actual (inmutable)</p>
                <p class="text-sm text-grafito" x-text="enmiendaActuadoObjetivo?.descripcion || '—'"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-grafito mb-1" for="enmienda-actuado-descripcion">Descripción corregida</label>
                <textarea id="enmienda-actuado-descripcion" x-model="formEnmiendaActuado.descripcion" required rows="3"
                    minlength="5" maxlength="10000"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-verde-institucional"
                    placeholder="Descripción corregida del actuado"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-grafito mb-1" for="enmienda-actuado-motivo">Motivo de la enmienda</label>
                <textarea id="enmienda-actuado-motivo" x-model="formEnmiendaActuado.motivo" required rows="2"
                    minlength="10" maxlength="1000"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-verde-institucional"
                    placeholder="Fundamente la corrección (mínimo 10 caracteres)"></textarea>
            </div>

            <p class="text-xs text-gris">
                El actuado original permanece inmutable; la corrección se registra como un nuevo actuado en la cadena de custodia.
            </p>

            <p x-show="errorEnmiendaActuado" class="text-sm text-[#B53F12] bg-[#F15A24]/10 border border-[#F15A24]/20 rounded-lg p-3">
                <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorEnmiendaActuado"></span>
            </p>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gris-claro">
                <button type="button" @click="cerrarModalEnmiendaActuado()" class="px-4 py-2 bg-gris-claro text-grafito rounded-lg text-sm font-medium hover:bg-gris-claro/70">Cancelar</button>
                <button type="submit" :disabled="submittingEnmiendaActuado"
                    class="px-4 py-2 bg-verde-profundo text-white rounded-lg text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                    <i class="fa-solid fa-pen-to-square mr-1"></i>
                    <span x-text="submittingEnmiendaActuado ? 'Registrando…' : 'Registrar enmienda'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
