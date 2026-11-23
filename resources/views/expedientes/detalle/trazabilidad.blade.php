<div class="bg-white rounded-xl border border-gris-claro shadow-sm">
    <button type="button" @click="trazabilidadAbierta = !trazabilidadAbierta"
        :aria-expanded="trazabilidadAbierta ? 'true' : 'false'"
        title="Despliega el control de plazos y la línea de tiempo de actuados"
        class="w-full bg-gris-claro px-4 py-3 rounded-t-xl border-b border-gris-claro flex items-center justify-between text-left">
        <span class="text-sm font-bold text-grafito uppercase">
            <i class="fa-solid fa-route text-verde-institucional mr-2"></i> Plazos y trazabilidad
        </span>
        <span class="flex items-center gap-3">
            <template x-if="expediente.sem_plazo">
                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded text-[10px] font-bold border"
                    :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                    <span class="rounded-full h-2 w-2" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                    <span x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                </span>
            </template>
            <i class="fa-solid text-gris" :class="trazabilidadAbierta ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </span>
    </button>

    <div x-show="trazabilidadAbierta" x-cloak>
        <div class="px-4 pt-3 border-b border-gris-claro flex gap-2" role="tablist" aria-label="Seguimiento del expediente">
            <button type="button" role="tab" @click="seguimientoPestana = 'plazos'"
                :aria-selected="seguimientoPestana === 'plazos' ? 'true' : 'false'"
                :class="seguimientoPestana === 'plazos'
                    ? 'border-verde-institucional text-verde-profundo'
                    : 'border-transparent text-gris hover:text-grafito'"
                class="px-3 py-2 text-xs font-bold uppercase tracking-wider border-b-2 transition-colors"
                title="Muestra los plazos perentorios y su semáforo">
                <i class="fa-solid fa-stopwatch mr-1"></i> Plazos
            </button>
            <button type="button" role="tab" @click="seguimientoPestana = 'trazabilidad'"
                :aria-selected="seguimientoPestana === 'trazabilidad' ? 'true' : 'false'"
                :class="seguimientoPestana === 'trazabilidad'
                    ? 'border-verde-institucional text-verde-profundo'
                    : 'border-transparent text-gris hover:text-grafito'"
                class="px-3 py-2 text-xs font-bold uppercase tracking-wider border-b-2 transition-colors"
                title="Muestra la línea de tiempo de actuados inmutables">
                <i class="fa-solid fa-timeline mr-1"></i> Trazabilidad
            </button>
        </div>

        <div class="p-4">
            <div x-show="seguimientoPestana === 'plazos'" x-cloak>
                <template x-if="(expediente.plazos || []).length > 0">
                    <ul class="divide-y divide-gris-claro border border-gris-claro rounded-lg">
                        <template x-for="plazo in plazosOrdenados()" :key="plazo.id || plazo.tipo_plazo + plazo.fecha_limite">
                            <li class="p-3 flex flex-col md:flex-row md:items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-grafito uppercase" x-text="plazo.tipo_plazo || 'Plazo'"></span>
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-gris-claro text-gris uppercase" x-text="plazo.estado || ''"></span>
                                    </div>
                                    <p class="text-xs text-gris mt-1">
                                        <span x-text="formatDate(plazo.fecha_inicio)"></span> → <span x-text="formatDate(plazo.fecha_limite)"></span>
                                        <span x-show="plazo.dias_habiles_otorgados"> · <span x-text="plazo.dias_habiles_otorgados"></span> días hábiles</span>
                                    </p>
                                </div>
                                <template x-if="plazo.sem_plazo">
                                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded text-xs font-bold border shrink-0"
                                        :class="getSemaforoClass(plazo.sem_plazo.codigo_color)">
                                        <span class="rounded-full h-2.5 w-2.5 shrink-0" :class="getSemaforoDotClass(plazo.sem_plazo.codigo_color)"></span>
                                        <span x-text="semEtiqueta(plazo.sem_plazo.codigo_color)"></span>
                                    </span>
                                </template>
                            </li>
                        </template>
                    </ul>
                </template>
                <template x-if="!(expediente.plazos || []).length">
                    <p class="text-sm text-gris text-center py-6">Aún no hay plazos registrados para este expediente.</p>
                </template>
            </div>

            <div x-show="seguimientoPestana === 'trazabilidad'" x-cloak>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-grafito">Historial de actuados</h4>
                    <span class="text-xs text-gris" x-text="(expediente.actuados || []).length + ' registro(s)'"></span>
                </div>

                <div class="p-4 relative border border-gris-claro rounded-lg">
                    <div class="absolute left-[23px] top-8 bottom-8 w-0.5 bg-gris-claro" x-show="(expediente.actuados || []).length"></div>
                    <template x-if="(expediente.actuados || []).length > 0">
                        <div class="space-y-6 relative">
                            <template x-for="actuado in expediente.actuados" :key="actuado.id">
                                <div class="flex gap-4 items-start">
                                    <div class="shrink-0 w-5 h-5 rounded-full bg-verde-profundo border-2 border-verde-institucional relative z-10 mt-1"></div>
                                    <div class="flex-1 bg-gris-claro rounded-lg p-4 border border-gris-claro">
                                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mb-2">
                                            <span class="text-sm font-bold text-grafito" x-text="actuado.tipo_actuado?.nombre || actuado.tipo_actuado?.codigo || 'Actuado'"></span>
                                            <span class="text-xs text-gris shrink-0" x-text="formatDateTime(actuado.fecha_hora || actuado.created_at)"></span>
                                        </div>
                                        <template x-if="actuado.estado_nuevo || actuado.estado_anterior">
                                            <div class="flex items-center gap-2 mb-2 flex-wrap text-[10px] font-bold uppercase tracking-wider">
                                                <span class="px-2 py-0.5 rounded bg-white text-gris border border-gris-claro" x-text="actuado.estado_anterior?.nombre || actuado.estado_anterior?.codigo || '—'"></span>
                                                <i class="fa-solid fa-arrow-right text-gris"></i>
                                                <span class="px-2 py-0.5 rounded bg-verde-profundo/10 text-verde-profundo border border-verde-institucional/30" x-text="actuado.estado_nuevo?.nombre || actuado.estado_nuevo?.codigo || '—'"></span>
                                            </div>
                                        </template>
                                        <p class="text-sm text-gris whitespace-pre-line" x-text="actuado.descripcion || ''"></p>

                                        <template x-if="actuado.hash_actuado || actuado.hash_anterior">
                                            <div class="relative inline-flex group mt-2">
                                                <button type="button" @click.stop="hashActuado = actuado"
                                                    :aria-describedby="`ayuda-hash-${actuado.id}`"
                                                    title="Ver el hash anterior y el hash firmado de este actuado"
                                                    class="text-xs text-verde-institucional hover:underline">
                                                    <i class="fa-solid fa-lock mr-1"></i>Ver cadena de custodia
                                                </button>
                                                <span :id="`ayuda-hash-${actuado.id}`" role="tooltip"
                                                    class="pointer-events-none absolute bottom-full left-0 z-40 mb-2 w-56 rounded-lg bg-grafito px-3 py-2 text-[11px] leading-snug text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
                                                    Abre el detalle SHA‑256 sin desplazar la línea de tiempo.
                                                </span>
                                            </div>
                                        </template>

                                        <template x-if="(actuado.adjuntos || []).length > 0">
                                            <div class="mt-3 pt-3 border-t border-gris-claro flex flex-wrap gap-2">
                                                <template x-for="adj in actuado.adjuntos" :key="adj.id">
                                                    <a :href="`/api/adjuntos/${adj.id}/descargar`" target="_blank"
                                                        title="Abre el documento PDF adjunto al actuado"
                                                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gris-claro text-xs font-medium text-grafito rounded hover:bg-[#F15A24]/10 hover:text-[#B53F12] hover:border-[#F15A24]/30 transition">
                                                        <i class="fa-solid fa-file-pdf mr-1.5 text-[#F15A24]"></i> Ver documento
                                                    </a>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!(expediente.actuados || []).length">
                        <p class="text-sm text-gris text-center py-8">Aún no hay actuados registrados en este expediente.</p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
