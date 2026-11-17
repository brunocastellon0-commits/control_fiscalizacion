<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-bolt text-verde-institucional mr-2"></i> Acciones disponibles</h3>
    </div>
    <div class="p-4 space-y-4">
        <p class="text-xs text-gris">Las acciones dependen de su rol, el estado del expediente y las reglas del sistema. Si una acción no está permitida, el sistema le informará.</p>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="abrirModalActuado()" x-show="catalogoCargado && catalogoActuados.length > 0"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium hover:bg-verde-institucional transition">
                <i class="fa-solid fa-file-circle-plus"></i> Emitir actuado
            </button>

            <button type="button" @click="abrirModalEnmienda()"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-azul-petroleo text-white text-sm font-medium hover:opacity-90 transition">
                <i class="fa-solid fa-pen-to-square"></i> Registrar enmienda
            </button>

            <button type="button" @click="abrirModalIncompetencia()"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-sm font-medium hover:bg-amber-100 transition">
                <i class="fa-solid fa-share-from-square"></i> Solicitar incompetencia
            </button>

            <template x-if="rolUsuarioActual() === 'ENCARGADA'">
                <div class="contents">
                    <button type="button" @click="abrirModalRemision('remitir')"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gris-claro bg-white text-grafito text-sm font-medium hover:bg-gris-claro/40 transition">
                        <i class="fa-solid fa-paper-plane"></i> Remitir a unidad externa
                    </button>
                    <button type="button" @click="abrirModalRemision('recepcion')"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gris-claro bg-white text-grafito text-sm font-medium hover:bg-gris-claro/40 transition">
                        <i class="fa-solid fa-inbox"></i> Registrar recepción
                    </button>
                    <button type="button" @click="resolverIncompetencia(true)"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-green-300 bg-green-50 text-green-800 text-sm font-medium hover:bg-green-100 transition">
                        <i class="fa-solid fa-check"></i> Aprobar incompetencia
                    </button>
                    <button type="button" @click="resolverIncompetencia(false)"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-300 bg-red-50 text-red-800 text-sm font-medium hover:bg-red-100 transition">
                        <i class="fa-solid fa-xmark"></i> Rechazar incompetencia
                    </button>
                </div>
            </template>
        </div>

        <div x-show="modalIncompetenciaOpen" class="border border-gris-claro rounded-lg p-4 bg-gris-claro/30 space-y-3" x-cloak>
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-grafito">Solicitud de incompetencia</h4>
                <button type="button" @click="modalIncompetenciaOpen = false" class="text-gris hover:text-grafito"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Unidad de destino (ID)</label>
                <input type="number" min="1" x-model="formIncompetencia.unidad_destino_id"
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
            </div>
            <div>
                <label class="block text-xs font-semibold text-grafito uppercase mb-1">Motivo / justificación <span class="text-[#F15A24]">*</span></label>
                <textarea x-model="formIncompetencia.motivo" rows="3" required
                    class="w-full border border-gris-claro rounded-lg text-sm px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalIncompetenciaOpen = false" class="px-3 py-2 rounded-lg bg-white border border-gris-claro text-sm">Cancelar</button>
                <button type="button" @click="submitIncompetenciaSolicitud()" :disabled="submittingIncompetencia"
                    class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 disabled:opacity-50">
                    <span x-text="submittingIncompetencia ? 'Enviando...' : 'Enviar solicitud'"></span>
                </button>
            </div>
        </div>

        <p class="text-xs text-gris">Sugerencia: use “Emitir actuado” para las actuaciones permitidas según el estado y su rol. Las acciones específicas (enmienda, incompetencia, remisión/recepción) se registran con su respaldo correspondiente.</p>
    </div>
</div>
