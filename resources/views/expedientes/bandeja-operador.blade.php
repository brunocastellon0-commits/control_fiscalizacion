@extends('layouts.app')

@section('titulo', 'Bandeja de entrada')

@section('contenido')
    <div class="p-6" x-data="bandejaOperador()" x-init="cargar(1)">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div>
                <h1 class="text-xl font-bold text-grafito">Bandeja de entrada</h1>
                <p class="text-sm text-gris">Expedientes asignados a su operación.</p>
            </div>
            <div class="text-sm text-gris">
                <span x-show="cargando" class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin"></i> Actualizando...
                </span>
                <span x-show="!cargando" x-text="meta.total ? meta.total + ' expediente(s)' : ''"></span>
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
                <button @click="cargar(meta.current_page)" class="ml-3 underline">Reintentar</button>
            </div>
        </template>

        <template x-if="!cargando && !error && expedientes.length === 0">
            <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gris">
                <i class="fa-solid fa-inbox text-4xl mb-3 opacity-40"></i>
                <p class="font-medium text-grafito">No tiene expedientes asignados</p>
                <p class="text-sm mt-1">Cuando se le asignen expedientes aparecerán aquí.</p>
            </div>
        </template>

        <div x-show="expedientes.length > 0" class="space-y-3">
            <template x-for="exp in expedientes" :key="exp.id">
                <article @click="window.location.href = `/expedientes/${exp.id}`" role="button" tabindex="0"
                         @keydown.enter="window.location.href = `/expedientes/${exp.id}`"
                         class="bg-white rounded-xl shadow-sm border-l-4 p-5 hover:shadow-md transition cursor-pointer"
                         :class="semBorde(exp.sem_plazo, false)">
                    <div class="flex flex-col lg:flex-row lg:items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="font-semibold text-grafito" x-text="exp.nurej_code"></span>
                                <span x-show="exp.estado_actual"
                                      class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-medium"
                                      :class="{
                                          'bg-[#8CC63F]/20 text-[#3F5E1B]': exp.estado_actual?.codigo === 'EN_TRAMITE',
                                          'bg-[#F15A24]/20 text-[#B53F12]': exp.estado_actual?.codigo === 'PENDIENTE_SORTEO',
                                          'bg-gris-claro text-gris': true
                                      }"
                                      x-text="exp.estado_actual?.nombre"></span>
                                <span x-show="exp.sem_plazo" class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-bold text-white"
                                      :class="{
                                          'bg-[#285C3A]': exp.sem_plazo?.codigo_color === 'VERDE',
                                          'bg-amber-400': exp.sem_plazo?.codigo_color === 'AMARILLO',
                                          'bg-red-500': exp.sem_plazo?.codigo_color === 'ROJO',
                                          'bg-grafito': exp.sem_plazo?.codigo_color === 'FUERA_DE_PLAZO'
                                      }"
                                      x-text="semTexto(exp.sem_plazo)"></span>
                            </div>

                            <h2 class="text-sm text-grafito line-clamp-2" x-text="exp.resumen_hechos || 'Sin resumen'"></h2>

                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-gris">
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-gavel w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="exp.reglamento ? exp.reglamento.nombre : 'Sin reglamento'"></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-route w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="exp.via || 'Via no indicada'"></span>
                                </div>
                                <div class="flex items-center gap-1.5 sm:col-span-2" x-show="exp.creador">
                                    <i class="fa-solid fa-user-pen w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="'Creado por ' + exp.creador.nombres + ' ' + exp.creador.apellidos"></span>
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
                <button @click="cargar(meta.current_page - 1)" :disabled="!meta.prev_page_url"
                        class="px-3 py-1.5 rounded-lg border border-gris-claro hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left mr-1"></i>Anterior
                </button>
                <span class="text-gris" x-text="'Página ' + meta.current_page + ' de ' + meta.last_page"></span>
                <button @click="cargar(meta.current_page + 1)" :disabled="!meta.next_page_url"
                        class="px-3 py-1.5 rounded-lg border border-gris-claro hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    Siguiente<i class="fa-solid fa-chevron-right ml-1"></i>
                </button>
            </nav>
        </div>
    </div>

    <script>
        function bandejaOperador() {
            return {
                expedientes: [],
                meta: { current_page: 1, last_page: 1 },
                cargando: false,
                error: null,

                async cargar(pagina) {
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/bandeja?page=${pagina}`);
                        if (!ok) {
                            this.error = 'No se pudo cargar la bandeja.';
                            return;
                        }
                        this.expedientes = data.data;
                        const m = data.meta || {};
                        this.meta = {
                            current_page: m.current_page || 1,
                            last_page: m.last_page || 1,
                            prev_page_url: m.prev_page_url || null,
                            next_page_url: m.next_page_url || null,
                        };
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.cargando = false;
                    }
                },

                semBorde(sem, ligero) {
                    if (!sem) return 'border-gris-claro';
                    switch (sem.codigo_color) {
                        case 'VERDE': return 'border-[#285C3A]';
                        case 'AMARILLO': return 'border-amber-400';
                        case 'ROJO': return 'border-red-500';
                        case 'FUERA_DE_PLAZO': return 'border-grafito';
                        default: return 'border-gris-claro';
                    }
                },

                semTexto(sem) {
                    if (!sem) return '';
                    if (sem.codigo_color === 'FUERA_DE_PLAZO') return 'Fuera de plazo';
                    return `${sem.codigo_color} · ${sem.dias_restantes} días`;
                },

                fechaCorta(fecha) {
                    if (!fecha) return '';
                    const d = new Date(fecha);
                    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
                },
            };
        }
    </script>
@endsection
