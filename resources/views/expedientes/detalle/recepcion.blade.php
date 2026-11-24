<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden" x-show="seccionActiva === 'resumen'" x-cloak>
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-circle-info text-verde-institucional mr-2"></i> Información general</h3>
    </div>
    <dl class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div>
            <dt class="text-[10px] font-bold uppercase tracking-wider text-gris">Reglamento</dt>
            <dd class="text-grafito" x-text="expediente.reglamento?.codigo || '—'"></dd>
        </div>
        <div>
            <dt class="text-[10px] font-bold uppercase tracking-wider text-gris">Naturaleza</dt>
            <dd class="text-grafito" x-text="expediente.naturaleza || '—'"></dd>
        </div>
    </dl>
    <div class="px-4 pb-4">
        <p class="rounded-lg bg-verde-institucional/10 border border-verde-institucional/30 px-3 py-2 text-xs text-verde-profundo">
            <i class="fa-solid fa-circle-check mr-1"></i>
            <span x-show="pasoActivo === 'recepcion'" class="font-semibold uppercase tracking-wider">Solo lectura. </span>
            El expediente llegó a su bandeja mediante el sorteo de la Encargada. Este paso es informativo y no requiere ninguna acción adicional.
        </p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden"
    x-show="seccionActiva === 'resumen' || seccionActiva === 'partes'" x-cloak>
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-users text-verde-institucional mr-2"></i> Partes procesales</h3>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gris-claro">
        <template x-for="tipo in ['DENUNCIANTE', 'DENUNCIADO']" :key="tipo">
            <div class="p-4 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-verde-institucional"
                    x-text="tipo === 'DENUNCIANTE' ? 'Denunciante' : 'Denunciado'"></p>
                <template x-if="partesPorTipo(tipo).length > 0">
                    <ul class="mt-2 space-y-3">
                        <template x-for="parte in partesPorTipo(tipo)" :key="parte.id || parte.nombre_completo">
                            <li class="text-sm text-grafito">
                                <span class="block font-semibold" x-text="parte.nombre_completo"></span>
                                <span class="block text-xs text-gris" x-text="parte.documento_identidad || 'Sin CI/NIT'"></span>
                                <span class="block text-xs text-gris" x-show="parte.cargo_institucion" x-text="parte.cargo_institucion"></span>
                            </li>
                        </template>
                    </ul>
                </template>
                <template x-if="partesPorTipo(tipo).length === 0">
                    <p class="mt-2 text-sm text-gris">Sin parte registrada.</p>
                </template>
            </div>
        </template>
    </div>
    <template x-if="partesOtras().length > 0">
        <div class="px-4 pb-4 border-t border-gris-claro pt-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gris mb-2">Otras partes</p>
            <ul class="flex flex-wrap gap-x-6 gap-y-2">
                <template x-for="parte in partesOtras()" :key="parte.id || parte.nombre_completo">
                    <li class="text-xs text-grafito">
                        <span class="font-semibold" x-text="parte.tipo || 'Parte'"></span>
                        <span x-text="' · ' + (parte.nombre_completo || '')"></span>
                    </li>
                </template>
            </ul>
        </div>
    </template>
</div>

<div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden"
    x-show="seccionActiva === 'resumen' || seccionActiva === 'hechos'" x-cloak>
    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
        <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-file-lines text-verde-institucional mr-2"></i> Hechos del caso</h3>
    </div>
    <div class="p-4 text-sm text-gris leading-relaxed whitespace-pre-line" x-text="expediente.resumen_hechos || 'Sin resumen registrado.'"></div>
</div>
