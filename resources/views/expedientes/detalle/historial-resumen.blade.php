<div class="bg-white rounded-xl border border-gris-claro shadow-sm">
    <div class="bg-gris-claro px-4 py-3 rounded-t-xl border-b border-gris-claro flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-clock text-verde-institucional mr-2"></i> Historial de movimientos</h3>
        <button type="button" @click="seleccionarSeccion('historial')" title="Abre la línea de tiempo completa"
            class="text-xs text-verde-profundo hover:underline whitespace-nowrap">
            Ver todos <i class="fa-solid fa-arrow-right ml-1"></i>
        </button>
    </div>
    <div class="p-4">
        <template x-if="actuadosRecientes().length > 0">
            <ul class="space-y-3">
                <template x-for="actuado in actuadosRecientes()" :key="actuado.id">
                    <li class="flex items-start gap-3">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-verde-profundo ring-2 ring-white"></span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-grafito leading-snug"
                                x-text="actuado.tipo_actuado?.nombre || actuado.tipo_actuado?.codigo || 'Actuado'"></p>
                            <p class="text-[11px] text-gris" x-text="formatDate(actuado.fecha_hora || actuado.created_at)"></p>
                        </div>
                    </li>
                </template>
            </ul>
        </template>
        <template x-if="actuadosRecientes().length === 0">
            <p class="text-sm text-gris text-center py-2">Aún no hay actuados registrados en este expediente.</p>
        </template>
    </div>
</div>
