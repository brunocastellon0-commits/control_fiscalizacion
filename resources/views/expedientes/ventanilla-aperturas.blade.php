@extends('layouts.app')

@section('titulo', 'Mis aperturas')

@section('contenido')
    <div class="p-6" x-data="historialAperturasVentanilla()" x-init="cargar(1)" x-cloak>
        <div class="flex flex-col gap-4 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-grafito">Mis aperturas</h1>
                    <p class="text-sm text-gris">Historial de solo lectura de los expedientes que registró desde Ventanilla.</p>
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-grafito mb-1">Buscar</label>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gris text-xs"></i>
                            <input type="text" x-model="filtros.busqueda"
                                placeholder="NURED o resumen de hechos..."
                                class="w-full border border-gris-claro rounded-lg pl-9 pr-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-grafito mb-1">Estado</label>
                        <select x-model="filtros.estado"
                            class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm text-grafito focus:border-verde-institucional focus:ring-verde-institucional">
                            <option value="">Todos los estados</option>
                            <template x-for="estado in estadosDisponibles()" :key="estado.codigo">
                                <option :value="estado.codigo" x-text="estado.nombre || estado.codigo"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <template x-if="cargando && expedientes.length === 0">
            <div class="space-y-3">
                <template x-for="n in 3" :key="n">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <div class="h-4 w-1/3 bg-gris-claro rounded animate-pulse mb-3"></div>
                        <div class="h-3 w-2/3 bg-gris-claro rounded animate-pulse"></div>
                    </div>
                </template>
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
                <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-40"></i>
                <p class="font-medium text-grafito">Aún no registró expedientes</p>
                <p class="text-sm mt-1">Los expedientes que aperture aparecerán aquí para consulta.</p>
            </div>
        </template>

        <template x-if="!cargando && !error && expedientes.length > 0 && expedientesFiltrados().length === 0">
            <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gris">
                <i class="fa-solid fa-filter-circle-xmark text-4xl mb-3 opacity-40"></i>
                <p class="font-medium text-grafito">No hay resultados con los filtros aplicados</p>
            </div>
        </template>

        <div x-show="!error && expedientesFiltrados().length > 0" class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gris-claro text-sm">
                    <thead class="bg-gris-claro/60 text-left text-xs font-bold uppercase tracking-wide text-gris">
                        <tr>
                            <th class="px-4 py-3">NURED</th>
                            <th class="px-4 py-3">Fecha de apertura</th>
                            <th class="px-4 py-3">Origen de ingreso</th>
                            <th class="px-4 py-3">Reglamento</th>
                            <th class="px-4 py-3">Estado actual</th>
                            <th class="px-4 py-3">Responsable</th>
                            <th class="px-4 py-3 text-center">Boleta</th>
                            <th class="px-4 py-3 text-right">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gris-claro">
                        <template x-for="exp in expedientesFiltrados()" :key="exp.id">
                            <tr class="hover:bg-gris-claro/30 transition">
                                <td class="px-4 py-3 font-mono font-semibold text-grafito" x-text="exp.nurej_code"></td>
                                <td class="px-4 py-3 text-gris" x-text="formatoFecha(exp.fecha_ingreso)"></td>
                                <td class="px-4 py-3 text-gris" x-text="exp.via === 'INGRESO_DISTRITAL' ? 'Ingreso desde distrital' : (exp.via === 'INGRESO_VERBAL' ? 'Ingreso verbal' : (exp.via || '—'))"></td>
                                <td class="px-4 py-3 text-gris" x-text="exp.reglamento?.nombre || exp.reglamento?.codigo || '—'"></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded bg-gris-claro text-grafito text-xs font-medium"
                                        x-text="exp.estado_actual?.nombre || exp.estado_actual?.codigo || '—'"></span>
                                </td>
                                <td class="px-4 py-3 text-gris" x-text="nombreResponsable(exp.responsable)"></td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" x-show="exp.boleta_apertura"
                                        @click="imprimirDocumento(exp.boleta_apertura, 'Boleta de apertura')"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-verde-profundo hover:underline"
                                        title="Imprimir boleta de apertura">
                                        <i class="fa-solid fa-print"></i> Boleta
                                    </button>
                                    <a x-show="exp.boleta_apertura" :href="exp.boleta_apertura.url"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-verde-profundo hover:underline ml-2"
                                        title="Descargar boleta de apertura">
                                        <i class="fa-solid fa-file-arrow-down"></i> Descargar
                                    </a>
                                    <span x-show="!exp.boleta_apertura" class="text-gris">—</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" @click="abrirDetalle(exp.id)"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-verde-profundo hover:underline">
                                        Ver <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <nav class="flex flex-wrap items-center justify-end gap-3 px-4 py-3 border-t border-gris-claro text-sm">
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
        function historialAperturasVentanilla() {
            return {
                expedientes: [],
                meta: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
                cargando: false,
                error: null,
                filtros: { busqueda: '', estado: '' },

                async cargar(pagina = 1) {
                    if (pagina < 1) {
                        return;
                    }
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/ventanilla/aperturas?page=${pagina}`);
                        if (!ok) {
                            this.error = data?.message || 'No se pudo cargar el historial de aperturas.';
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
                        this.error = e.message || 'Ocurrió un error al cargar el historial.';
                        this.expedientes = [];
                    } finally {
                        this.cargando = false;
                    }
                },

                estadosDisponibles() {
                    const vistos = new Map();
                    (this.expedientes || []).forEach((exp) => {
                        const estado = exp.estado_actual;
                        if (estado?.codigo) {
                            vistos.set(estado.codigo, estado);
                        }
                    });
                    return Array.from(vistos.values());
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
                            return nurej.includes(q) || resumen.includes(q);
                        });
                    }

                    return lista;
                },

                nombreResponsable(responsable) {
                    if (!responsable?.usuario) {
                        return 'Sin asignar';
                    }
                    const nombre = [responsable.usuario.nombres, responsable.usuario.apellidos]
                        .filter(Boolean)
                        .join(' ')
                        .trim();
                    return nombre || 'Sin asignar';
                },

                formatoFecha(valor) {
                    if (!valor) {
                        return '—';
                    }
                    const fecha = new Date(valor);
                    if (Number.isNaN(fecha.getTime())) {
                        return valor;
                    }
                    return new Intl.DateTimeFormat('es-BO', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    }).format(fecha);
                },

                imprimirDocumento(documento, titulo) {
                    if (documento?.url) {
                        window.imprimirPdf({
                            url: documento.url,
                            titulo,
                        });
                    }
                },

                abrirDetalle(id) {
                    window.location.href = `/ventanilla/aperturas/${id}`;
                },
            };
        }
    </script>
@endsection
