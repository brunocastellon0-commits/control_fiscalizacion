<div class="bg-white rounded-xl border border-gris-claro shadow-sm">
    <div class="bg-gris-claro px-4 py-3 rounded-t-xl border-b border-gris-claro flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-file-lines text-verde-institucional mr-2"></i> Historial de actuados</h3>
        <span class="text-xs text-gris" x-text="(expediente.actuados || []).length + ' registro(s)'"></span>
    </div>
    <div class="p-4">
        <template x-if="actuadosDescendentes().length > 0">
            <ul class="divide-y divide-gris-claro">
                <template x-for="actuado in actuadosDescendentes()" :key="actuado.id">
                    <li class="py-3 flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-verde-institucional/10 text-verde-profundo">
                            <i class="fa-solid fa-file-signature text-xs"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-[11px] font-bold uppercase tracking-wide text-gris"
                                    x-text="formatDate(actuado.fecha_hora || actuado.created_at)"></span>
                                <span class="text-sm font-semibold text-grafito"
                                    x-text="actuado.tipo_actuado?.nombre || actuado.tipo_actuado?.codigo || 'Actuado'"></span>
                                <span x-show="(actuado.actuado_referencia_id ?? null) !== null"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold uppercase"
                                    :title="`Corrige el actuado #${actuado.actuado_referencia_id}`">Enmienda</span>
                                <span x-show="esActuadoEnmendado(actuado)"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-verde-institucional/15 text-verde-profundo font-bold uppercase"
                                    title="Este actuado ya tiene una enmienda registrada">Enmendado</span>
                            </div>
                            <p class="text-xs text-gris mt-0.5 break-words" x-text="actuado.descripcion || ''"></p>
                        </div>
                        <button type="button" x-show="puedeEnmendarActuado(actuado)"
                            @click="abrirModalEnmiendaActuado(actuado)"
                            title="Corrige la descripción de este actuado; el original permanece inmutable"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gris-claro bg-white text-xs font-medium text-grafito hover:bg-gris-claro/40 transition">
                            <i class="fa-solid fa-pen-to-square"></i> Enmendar
                        </button>
                        <button type="button" @click="abrirAdjuntoActuado(actuado)"
                            :disabled="!(actuado.adjuntos || []).length"
                            :title="(actuado.adjuntos || []).length
                                ? 'Abre el documento adjunto de este actuado'
                                : 'Este actuado no tiene documento adjunto'"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gris-claro bg-white text-xs font-medium text-grafito hover:bg-gris-claro/40 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-eye"></i> Ver más
                        </button>
                    </li>
                </template>
            </ul>
        </template>
        <template x-if="actuadosDescendentes().length === 0">
            <p class="text-sm text-gris text-center py-4">Aún no hay actuados registrados en este expediente.</p>
        </template>
    </div>
</div>
