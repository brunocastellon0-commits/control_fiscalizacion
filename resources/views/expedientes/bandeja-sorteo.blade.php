@extends('layouts.app')

@section('titulo', 'Sorteo de expedientes')

@section('contenido')
    <div class="p-6" x-data="bandejaSorteo()" x-init="cargar(1)">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div>
                <h1 class="text-xl font-bold text-grafito">Sorteo de expedientes</h1>
                <p class="text-sm text-gris">El sistema asigna cada causa al funcionario de menor carga del rol según la vía.</p>
            </div>
            <div class="flex items-center justify-end gap-3">
                <span class="text-sm text-gris">
                    <span x-show="cargando" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i> Actualizando...
                    </span>
                    <span x-show="!cargando && meta.total" x-text="meta.total + ' pendiente(s)'"></span>
                </span>
                <button @click="sortearTodos"
                        x-show="!cargando && meta.total > 0 && !sortearTodosEnviando"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium hover:bg-verde-institucional transition">
                    <i class="fa-solid fa-shuffle"></i> Sortear todo
                </button>
                <button disabled
                        x-show="sortearTodosEnviando"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium opacity-60 cursor-not-allowed">
                    <i class="fa-solid fa-spinner fa-spin"></i> Sorteando todo...
                </button>
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
                <i class="fa-solid fa-shuffle text-4xl mb-3 opacity-40"></i>
                <p class="font-medium text-grafito">No hay expedientes pendientes de sorteo</p>
                <p class="text-sm mt-1">Cuando se registren causas quedarán listadas aquí.</p>
            </div>
        </template>

        <div x-show="expedientes.length > 0" class="space-y-3">
            <template x-for="exp in expedientes" :key="exp.id">
                <article class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="font-semibold text-grafito" x-text="exp.nurej_code"></span>
                                <span x-show="exp.estado_actual"
                                      class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide font-medium bg-[#F15A24]/20 text-[#B53F12]"
                                      x-text="exp.estado_actual?.nombre"></span>
                            </div>
                            <h2 class="text-sm text-grafito line-clamp-2" x-text="exp.resumen_hechos || 'Sin resumen'"></h2>
                            <div class="mt-2 text-xs text-gris space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-gavel w-3.5 text-center text-verde-institucional"></i>
                                    <span x-text="exp.reglamento ? exp.reglamento.nombre : 'Sin reglamento'"></span>
                                </div>
                                <div x-show="exp.creador">
                                    <span x-text="'Ingresado por ' + exp.creador.nombres + ' ' + exp.creador.apellidos + ' · ' + fechaCorta(exp.fecha_ingreso)"></span>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <button @click="abrirSorteo(exp)"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium hover:bg-verde-institucional transition"
                                    :disabled="sorteandoId === exp.id">
                                <i class="fa-solid fa-shuffle"></i>
                                <span x-text="sorteandoId === exp.id ? 'Asignando...' : 'Sortear'"></span>
                            </button>
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

        <!-- Modal de sorteo -->
        <div x-show="seleccionado" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="seleccionado = null"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-grafito">Sortear expediente</h3>
                        <p class="text-sm text-gris">
                            <span x-text="seleccionado?.nurej_code"></span> · Reglamento
                            <span x-text="seleccionado?.reglamento ? seleccionado.reglamento.codigo : '—'"></span>
                        </p>
                    </div>
                    <button @click="seleccionado = null" class="text-gris hover:text-grafito">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="rounded-lg bg-gris-claro/50 border border-gris-claro p-4 text-sm text-grafito space-y-2">
                    <p>
                        <i class="fa-solid fa-shuffle mr-2 text-verde-institucional"></i>
                        Sorteo probabilístico a ciegas: el sistema asigna este expediente
                        al funcionario de menor carga del rol según la vía
                        (<span x-text="seleccionado?.via"></span>), sin selección manual.
                    </p>
                </div>

                <label class="block text-sm font-medium text-grafito mt-4 mb-1">Descripción (opcional)</label>
                <textarea x-model="descripcion" rows="2"
                          class="w-full rounded-lg border border-gris-claro px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-verde-institucional"
                          maxlength="1000" placeholder="Observaciones del proveído"></textarea>

                <p x-show="errorSorteo" class="mt-3 text-sm text-[#B53F12]">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="errorSorteo"></span>
                </p>

                <div class="flex justify-end gap-3 mt-5">
                    <button @click="seleccionado = null"
                            class="px-4 py-2 rounded-lg border border-gris-claro text-sm text-grafito hover:bg-gris-claro">
                        Cancelar
                    </button>
                    <button @click="confirmarSorteo"
                            :disabled="sortearEnviando"
                            class="px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-medium hover:bg-verde-institucional transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-shuffle mr-1"></i>
                        <span x-text="sortearEnviando ? 'Sorteando...' : 'Ejecutar Sorteo Probabilístico'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function bandejaSorteo() {
            return {
                expedientes: [],
                meta: { current_page: 1, last_page: 1 },
                cargando: false,
                error: null,

                seleccionado: null,
                descripcion: '',
                errorSorteo: null,
                sortearEnviando: false,
                sorteandoId: null,
                sortearTodosEnviando: false,

                async cargar(pagina) {
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/bandeja/sorteo?page=${pagina}`);
                        if (!ok) {
                            this.error = 'No se pudo cargar la bandeja de sorteo.';
                            return;
                        }
                        this.expedientes = data.data;
                        const m = data.meta || {};
                        this.meta = {
                            current_page: m.current_page || 1,
                            last_page: m.last_page || 1,
                            total: m.total || 0,
                            prev_page_url: m.prev_page_url || null,
                            next_page_url: m.next_page_url || null,
                        };
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.cargando = false;
                    }
                },

                async abrirSorteo(exp) {
                    this.seleccionado = exp;
                    this.descripcion = '';
                    this.errorSorteo = null;
                },

                async confirmarSorteo() {
                    this.sortearEnviando = true;
                    this.errorSorteo = null;
                    this.sorteandoId = this.seleccionado.id;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.seleccionado.id}/sortear`, {
                            method: 'POST',
                            body: {
                                descripcion: this.descripcion || null,
                            },
                        });
                        if (ok) {
                            const ganador = data?.data?.asignacion_activa?.usuario;
                            const nombre = ganador
                                ? ganador.apellidos + ', ' + ganador.nombres
                                : 'funcionario asignado';
                            window.apiToast('exito', 'Sorteo completado. Asignado a: ' + nombre + '.');
                            this.seleccionado = null;
                            await this.cargar(this.meta.current_page);
                        } else {
                            const errores = data?.errors;
                            this.errorSorteo = errores
                                ? Object.values(errores).flat().join(' ')
                                : (data?.message || 'No se pudo realizar el sorteo.');
                        }
                    } catch (e) {
                        this.errorSorteo = e.message;
                    } finally {
                        this.sortearEnviando = false;
                        this.sorteandoId = null;
                    }
                },

                fechaCorta(fecha) {
                    if (!fecha) return '';
                    const d = new Date(fecha);
                    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
                },

                async sortearTodos() {
                    if (!window.confirm('¿Sortear todas las causas pendientes de una sola vez?')) return;
                    this.sortearTodosEnviando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch('/api/bandeja/sorteo/todos', { method: 'POST' });
                        if (ok) {
                            window.apiToast('exito', (data?.total ?? 0) + ' causa(s) sorteada(s) correctamente.');
                            await this.cargar(1);
                        } else {
                            const errores = data?.errors;
                            this.error = errores
                                ? Object.values(errores).flat().join(' ')
                                : (data?.message || 'No se pudo realizar el sorteo en lote.');
                        }
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.sortearTodosEnviando = false;
                    }
                },
            };
        }
    </script>
@endsection
