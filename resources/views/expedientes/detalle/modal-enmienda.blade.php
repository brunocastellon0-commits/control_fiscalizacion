<div x-show="modalEnmiendaOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-grafito/60 p-4" x-cloak>
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-gris-claro" @click.away="cerrarModalEnmienda()">
        <div class="bg-azul-petroleo text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-base"><i class="fa-solid fa-pen-to-square mr-2"></i> Registrar enmienda</h3>
            <button type="button" @click="cerrarModalEnmienda()" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form @submit.prevent="submitEnmienda()" class="p-6 space-y-4">
            <p class="text-sm text-gris">Corrija un dato de carátula sin alterar el historial. Se registrará el valor original y el nuevo.</p>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Campo a corregir <span class="text-[#F15A24]">*</span></label>
                <input type="text" x-model="formEnmienda.campo_modificado" required maxlength="120"
                    title="Indique el dato de carátula que se corrige, por ejemplo: nombre de la parte demandada."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Ej.: Nombre de la parte demandada">
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Valor nuevo <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formEnmienda.valor_nuevo" required rows="2" maxlength="1000"
                    title="Valor corregido que reemplazará al dato original en la carátula."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Ingrese el dato corregido"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Motivo de la enmienda <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formEnmienda.motivo" required rows="2" maxlength="1000"
                    title="Justificación que quedará en el historial de auditoría de la enmienda."
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional"
                    placeholder="Explique por qué se realiza la corrección"></textarea>
            </div>
            <p x-show="errorEnmienda" class="text-sm text-[#B53F12] bg-[#F15A24]/10 border border-[#F15A24]/20 rounded-lg p-3">
                <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorEnmienda"></span>
            </p>
            <div class="flex justify-end gap-3 pt-4 border-t border-gris-claro">
                <button type="button" @click="cerrarModalEnmienda()" class="px-4 py-2 bg-gris-claro text-grafito rounded-lg text-sm font-medium">Cancelar</button>
                <button type="submit" :disabled="submittingEnmienda" class="px-6 py-2 bg-azul-petroleo text-white rounded-lg text-sm font-semibold hover:opacity-90 disabled:opacity-50">
                    <i class="fa-solid" :class="submittingEnmienda ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    <span class="ml-2" x-text="submittingEnmienda ? 'Guardando...' : 'Registrar enmienda'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
