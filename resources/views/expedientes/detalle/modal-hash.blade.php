<div x-show="hashActuado" class="fixed inset-0 z-[60] flex items-center justify-center bg-grafito/60 p-4" x-cloak>
    <div role="dialog" aria-modal="true" aria-labelledby="tituloModalHash"
        class="bg-white rounded-xl shadow-2xl border border-gris-claro w-full max-w-md overflow-hidden"
        @click.away="hashActuado = null">
        <div class="bg-azul-petroleo text-white px-5 py-3 flex items-start justify-between gap-4">
            <div>
                <h3 id="tituloModalHash" class="font-bold text-sm">
                    <i class="fa-solid fa-lock mr-2"></i> Cadena de Custodia (SHA-256)
                </h3>
                <p class="text-[11px] text-white/70 mt-0.5" x-text="hashActuado?.tipo_actuado?.nombre || 'Actuado'"></p>
            </div>
            <button type="button" @click="hashActuado = null" title="Cerrar detalle" aria-label="Cerrar detalle"
                class="text-white/70 hover:text-white shrink-0">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-5 space-y-3">
            <p class="text-xs text-gris">
                Registro SHA‑256 inmutable del actuado
                <span class="font-semibold text-grafito" x-text="formatDateTime(hashActuado?.fecha_hora || hashActuado?.created_at)"></span>.
            </p>
            <div class="rounded-lg bg-grafito p-3 text-[11px] font-mono text-gris-claro break-all leading-tight space-y-2">
                <div>
                    <span class="text-verde-institucional">HASH_ANTERIOR:</span>
                    <span x-text="hashActuado?.hash_anterior || '-'"></span>
                </div>
                <div>
                    <span class="text-verde-institucional">HASH_ACTUADO:</span>
                    <span x-text="hashActuado?.hash_actuado || '-'"></span>
                </div>
            </div>
        </div>

        <div class="px-5 pb-5 flex justify-end">
            <button type="button" @click="hashActuado = null"
                class="px-4 py-2 rounded-lg bg-gris-claro text-grafito text-sm font-medium hover:bg-gris-claro/70">
                Cerrar
            </button>
        </div>
    </div>
</div>
