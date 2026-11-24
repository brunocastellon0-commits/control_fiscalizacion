@extends('layouts.app')

@section('titulo', 'Bandeja de entrada')

@section('contenido')
    <div class="p-6" x-data="bandejaOperador()" x-init="cargar(1)">
        <div class="flex flex-col gap-4 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-grafito">Bandeja de entrada</h1>
                    <p class="text-sm text-gris">Expedientes asignados a su operación.</p>
                </div>
                <div class="text-sm text-gris text-left sm:text-right">
                    <span x-show="cargando" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i> Actualizando...
                    </span>
                    <span x-show="!cargando">
                        <span x-text="expedientesFiltrados().length"></span> de <span x-text="meta.total || 0"></span> expediente(s)
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-grafito mb-1">Buscar</label>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gris text-xs"></i>
                            <input type="text" x-model="filtros.busqueda"
                                placeholder="NURED o nombre de parte interesada..."
                                class="w-full border border-gris-claro rounded-lg pl-9 pr-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-grafito mb-1">Estado</label>
                        <select x-model="filtros.estado"
                            class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
                            <option value="">Todos los estados</option>
                            <option value="EN_EVALUACION">En evaluación</option>
                            <option value="EN_PLANIFICACION">En planificación</option>
                            <option value="EN_EJECUCION">En ejecución</option>
                            <option value="EN_SUBSANACION">En subsanación</option>
                            <option value="PENDIENTE_SORTEO">Pendiente de sorteo</option>
                            <option value="EN_TRAMITE">En trámite</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-grafito mb-1">Ordenar por</label>
                        <select x-model="filtros.orden"
                            class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
                            <option value="antiguo">Más antiguo primero</option>
                            <option value="urgente">Más urgente por semáforo</option>
                            <option value="reciente">Más reciente primero</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex justify-end" x-show="filtros.busqueda || filtros.estado || filtros.orden !== 'antiguo'">
                    <button type="button" @click="limpiarFiltros()" class="text-xs font-medium text-verde-profundo hover:underline">
                        Limpiar filtros
                    </button>
                </div>
            </div>
        </div>

        <template x-if="cargando && expedientes.length === 0">
            <div class="space-y-3">
                <div x-for="n in 3" :key="n" class="bg-white rounded-xl shadow-sm p-5">
                    <div class="h-4 w-1/3 bg-gris-claro rounded animate-pulse mb-3"></div>
                    <div class="h-3 w-2/3 bg-gris-claro rounded animate-pulse"></div>
                </div>
            </div>
        </template>

        <template x-if="!cargando && error">
            <div class="bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] rounded-xl p-5 text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                <span x-text="error"></span>
                <button @click="cargar(meta.current_page || 1)" class="ml-3 underline font-medium">Reintentar</button>
            </div>
        </template>

        <template x-if="!cargando && !error && expedientes.length === 0">
            <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gris">
                <i class="fa-solid fa-inbox text-4xl mb-3 opacity-40"></i>
                <p class="font-medium text-grafito">No tiene expedientes asignados</p>
                <p class="text-sm mt-1">Cuando se le asignen expedientes aparecerán aquí.</p>
            </div>
        </template>

        <template x-if="!cargando && !error && expedientes.length > 0 && expedientesFiltrados().length === 0">
            <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gris">
                <i class="fa-solid fa-filter-circle-xmark text-4xl mb-3 opacity-40"></i>
                <p class="font-medium text-grafito">No hay resultados con los filtros aplicados</p>
                <p class="text-sm mt-1">Pruebe con otro estado, búsqueda u orden.</p>
            </div>
        </template>

        <div x-show="!error && expedientesFiltrados().length > 0" class="space-y-3">
            <template x-for="exp in expedientesFiltrados()" :key="exp.id">
                <article @click="window.location.href = `/expedientes/${exp.id}`" role="button" tabindex="0"
                    @keydown.enter="window.location.href = `/expedientes/${exp.id}`"
                    class="bg-white rounded-xl shadow-sm border-l-4 p-5 hover:shadow-md transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-verde-institucional/40"
                    :class="semBorde(exp.sem_plazo)">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="font-semibold text-grafito" x-text="exp.nurej_code"></span>
                                <span x-show="exp.estado_actual"
                                    class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-medium"
                                    :class="claseEstado(exp.estado_actual?.codigo)"
                                    x-text="exp.estado_actual?.nombre || exp.estado_actual?.codigo"></span>
                                <span x-show="exp.sem_plazo" class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-bold text-white"
                                    :class="claseSemaforoBadge(exp.sem_plazo)"
                                    x-text="semTexto(exp.sem_plazo)"></span>
                            </div>

                            <h2 class="text-sm text-grafito line-clamp-2" x-text="exp.resumen_hechos || 'Sin resumen de hechos'"></h2>

                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-gris">
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-gavel w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="exp.reglamento ? exp.reglamento.nombre : 'Sin reglamento'"></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-route w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="exp.via || 'Vía no indicada'"></span>
                                </div>
                                <div class="flex items-center gap-1.5 sm:col-span-2" x-show="textoPartes(exp)">
                                    <i class="fa-solid fa-users w-3.5 text-center text-verde-institucional"></i>
                                    <span class="truncate" x-text="'Partes: ' + textoPartes(exp)"></span>
                                </div>
                                <div class="flex items-center gap-1.5 sm:col-span-2" x-show="exp.creador">
                                    <i class="fa-solid fa-user-pen w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="'Ingresado por ' + nombreCompleto(exp.creador)"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex lg:flex-col items-center lg:items-end gap-2 lg:gap-1 shrink-0 text-xs">
                            <span class="text-gris" x-text="fechaCorta(exp.fecha_ingreso)"></span>
                            <span x-show="exp.sem_plazo && exp.sem_plazo.es_fuera_de_plazo" class="text-[#B53F12] font-medium">
                                <i class="fa-solid fa-circle-exclamation mr-1"></i>Fuera de plazo
                            </span>
                        </div>
                    </div>
                </article>
            </template>

            <nav x-show="meta.last_page > 1" class="flex items-center justify-between pt-2 text-sm">
                <button @click="cargar(meta.current_page - 1)" :disabled="!meta.prev_page_url || cargando"
                    class="px-3 py-1.5 rounded-lg border border-gris-claro hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left mr-1"></i>Anterior
                </button>
                <span class="text-gris" x-text="'Página ' + (meta.current_page || 1) + ' de ' + (meta.last_page || 1)"></span>
                <button @click="cargar(meta.current_page + 1)" :disabled="!meta.next_page_url || cargando"
                    class="px-3 py-1.5 rounded-lg border border-gris-claro hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    Siguiente<i class="fa-solid fa-chevron-right ml-1"></i>
                </button>
            </nav>
        </div>
    </div>

    <script>
        (function () {
            try {
                const aviso = sessionStorage.getItem('avisoBandeja');
                if (aviso) {
                    sessionStorage.removeItem('avisoBandeja');
                    window.apiToast('exito', aviso);
                }
            } catch (e) {
                // sessionStorage no disponible: se omite el aviso.
            }
        })();

        function bandejaOperador() {
            return {
                expedientes: [],
                meta: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
                cargando: false,
                error: null,
                filtros: {
                    busqueda: '',
                    estado: '',
                    orden: 'antiguo',
                },

                async cargar(pagina = 1) {
                    if (pagina < 1) {
                        return;
                    }
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/bandeja?page=${pagina}`);
                        if (!ok) {
                            this.error = data?.message || 'No se pudo cargar la bandeja.';
                            this.expedientes = [];
                            return;
                        }
                        this.expedientes = data.data || [];
                        const m = data.meta || {};
                        this.meta = {
                            current_page: m.current_page || pagina || 1,
                            last_page: m.last_page || 1,
                            total: typeof m.total === 'number' ? m.total : (this.expedientes.length || 0),
                            prev_page_url: m.prev_page_url || null,
                            next_page_url: m.next_page_url || null,
                        };
                    } catch (e) {
                        this.error = e.message || 'Ocurrió un error al cargar la bandeja.';
                        this.expedientes = [];
                    } finally {
                        this.cargando = false;
                    }
                },

                limpiarFiltros() {
                    this.filtros.busqueda = '';
                    this.filtros.estado = '';
                    this.filtros.orden = 'antiguo';
                },

                expedientesFiltrados() {
                    let lista = [...(this.expedientes || [])];

                    if (this.filtros.estado) {
                        lista = lista.filter((exp) => (exp.estado_actual?.codigo || '') === this.filtros.estado);
                    }

                    const q = (this.filtros.busqueda || '').trim().toLowerCase();
                    if (q) {
                        lista = lista.filter((exp) => {
                            const nurej = (exp.nurej_code || '').toLowerCase();
                            const resumen = (exp.resumen_hechos || '').toLowerCase();
                            const partes = this.textoPartes(exp).toLowerCase();
                            return nurej.includes(q) || resumen.includes(q) || partes.includes(q);
                        });
                    }

                    const prioridad = { ROJO: 0, FUERA_DE_PLAZO: 1, AMARILLO: 2, VERDE: 3, SIN: 4 };
                    lista.sort((a, b) => {
                        if (this.filtros.orden === 'reciente') {
                            return new Date(b.fecha_ingreso || 0) - new Date(a.fecha_ingreso || 0);
                        }
                        if (this.filtros.orden === 'urgente') {
                            const pa = prioridad[a.sem_plazo?.codigo_color] ?? 4;
                            const pb = prioridad[b.sem_plazo?.codigo_color] ?? 4;
                            if (pa !== pb) return pa - pb;
                            const da = a.sem_plazo?.dias_restantes ?? 9999;
                            const db = b.sem_plazo?.dias_restantes ?? 9999;
                            if (da !== db) return da - db;
                            return new Date(a.fecha_ingreso || 0) - new Date(b.fecha_ingreso || 0);
                        }
                        return new Date(a.fecha_ingreso || 0) - new Date(b.fecha_ingreso || 0);
                    });

                    return lista;
                },

                textoPartes(exp) {
                    const partes = exp.partes_vigentes || exp.partes || [];
                    if (!Array.isArray(partes) || partes.length === 0) {
                        return '';
                    }
                    return partes
                        .map((p) => p.nombre_completo || p.nombre || '')
                        .filter(Boolean)
                        .join(', ');
                },

                nombreCompleto(persona) {
                    if (!persona) return '';
                    return [persona.nombres, persona.apellidos].filter(Boolean).join(' ').trim();
                },

                semBorde(sem) {
                    if (!sem) return 'border-gris-claro';
                    switch (sem.codigo_color) {
                        case 'VERDE': return 'border-[#285C3A]';
                        case 'AMARILLO': return 'border-amber-400';
                        case 'ROJO': return 'border-red-500';
                        case 'FUERA_DE_PLAZO': return 'border-grafito';
                        default: return 'border-gris-claro';
                    }
                },

                claseSemaforoBadge(sem) {
                    if (!sem) return 'bg-gris';
                    switch (sem.codigo_color) {
                        case 'VERDE': return 'bg-[#285C3A]';
                        case 'AMARILLO': return 'bg-amber-500';
                        case 'ROJO': return 'bg-red-500';
                        case 'FUERA_DE_PLAZO': return 'bg-grafito';
                        default: return 'bg-gris';
                    }
                },

                semTexto(sem) {
                    if (!sem) return '';
                    if (sem.codigo_color === 'FUERA_DE_PLAZO') return 'Fuera de plazo';
                    if (sem.dias_restantes === null || sem.dias_restantes === undefined) return sem.codigo_color;
                    return `${sem.codigo_color} · ${sem.dias_restantes} días`;
                },

                claseEstado(codigo) {
                    const map = {
                        EN_TRAMITE: 'bg-[#8CC63F]/20 text-[#3F5E1B]',
                        PENDIENTE_SORTEO: 'bg-[#F15A24]/20 text-[#B53F12]',
                        EN_EVALUACION: 'bg-blue-100 text-blue-800',
                        EN_PLANIFICACION: 'bg-indigo-100 text-indigo-800',
                        EN_EJECUCION: 'bg-cyan-100 text-cyan-800',
                        EN_SUBSANACION: 'bg-amber-100 text-amber-800',
                    };
                    return map[codigo] || 'bg-gris-claro text-gris';
                },

                fechaCorta(fecha) {
                    if (!fecha) return '';
                    const d = new Date(fecha);
                    if (Number.isNaN(d.getTime())) return '';
                    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
                },
            };
        }
    </script>
@endsection
