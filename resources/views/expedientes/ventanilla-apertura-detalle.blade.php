@extends('layouts.app')

@section('titulo', 'Detalle de apertura')

@section('contenido')
    <div class="p-6" x-data="detalleAperturaVentanilla({{ (int) $expedienteId }})" x-init="cargar()" x-cloak>
        <div class="flex flex-col gap-4 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-mono font-bold text-grafito" x-text="expediente?.nurej_code || 'Cargando...'"></h1>
                        <span class="px-2 py-1 text-xs font-bold rounded bg-gris-claro text-gris">Solo lectura</span>
                    </div>
                    <p class="text-sm text-gris mt-1">Información general de la causa registrada desde Ventanilla.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 self-start">
                    <button type="button" x-show="expediente?.boleta_apertura" @click="imprimirBoleta()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm font-semibold hover:opacity-90">
                        <i class="fa-solid fa-print"></i> Imprimir boleta
                    </button>
                    <a x-show="expediente?.boleta_apertura" :href="expediente.boleta_apertura.url"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-grafito hover:bg-gris-claro/40"
                        title="Descargar boleta de apertura">
                        <i class="fa-solid fa-file-arrow-down"></i> Descargar
                    </a>
                    <button type="button" @click="volver()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-grafito hover:bg-gris-claro/40">
                        <i class="fa-solid fa-arrow-left"></i> Volver a mis aperturas
                    </button>
                </div>
            </div>
        </div>

        <template x-if="cargando">
            <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gris">
                <i class="fa-solid fa-spinner fa-spin text-2xl mr-2"></i> Cargando información...
            </div>
        </template>

        <template x-if="!cargando && error">
            <div class="bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] rounded-xl p-5 text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                <span x-text="error"></span>
                <button @click="cargar()" class="ml-3 underline font-medium">Reintentar</button>
            </div>
        </template>

        <template x-if="!cargando && !error && expediente">
            <div class="grid grid-cols-1 gap-5">
                <section class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                        <h2 class="text-sm font-bold text-grafito uppercase">
                            <i class="fa-solid fa-circle-info text-verde-institucional mr-2"></i> Datos generales
                        </h2>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">NURED</p>
                            <p class="font-mono font-semibold text-grafito" x-text="expediente.nurej_code || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Fecha de apertura</p>
                            <p class="text-grafito" x-text="formatoFecha(expediente.fecha_ingreso)"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Vía</p>
                            <p class="text-grafito" x-text="expediente.via || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Naturaleza</p>
                            <p class="text-grafito" x-text="etiquetaNaturaleza(expediente.naturaleza)"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Reglamento</p>
                            <p class="text-grafito" x-text="expediente.reglamento?.nombre || expediente.reglamento?.codigo || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Estado actual</p>
                            <p class="text-grafito" x-text="expediente.estado_actual?.nombre || expediente.estado_actual?.codigo || '—'"></p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs text-gris uppercase tracking-wide">Resumen de hechos</p>
                            <p class="text-grafito whitespace-pre-line" x-text="expediente.resumen_hechos || '—'"></p>
                        </div>
                    </div>
                </section>

                <section class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                        <h2 class="text-sm font-bold text-grafito uppercase">
                            <i class="fa-solid fa-users text-verde-institucional mr-2"></i> Partes procesales
                        </h2>
                    </div>
                    <div class="p-4">
                        <template x-if="(expediente.partes || []).length === 0">
                            <p class="text-sm text-gris text-center py-2">No hay partes registradas.</p>
                        </template>
                        <template x-if="(expediente.partes || []).length > 0">
                            <ul class="divide-y divide-gris-claro">
                                <template x-for="parte in expediente.partes" :key="parte.id">
                                    <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-grafito" x-text="parte.nombre_completo || '—'"></span>
                                        <span class="px-2 py-0.5 rounded bg-gris-claro text-gris text-xs font-semibold"
                                            x-text="parte.tipo || '—'"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </section>
            </div>
        </template>
    </div>

    <script>
        function detalleAperturaVentanilla(id) {
            return {
                expedienteId: id,
                expediente: null,
                cargando: true,
                error: null,

                async cargar() {
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/ventanilla/aperturas/${this.expedienteId}`);
                        if (ok) {
                            this.expediente = data.data || data;
                        } else {
                            this.error = data?.message || 'No se pudo cargar el detalle de la apertura.';
                        }
                    } catch (e) {
                        this.error = e.message || 'Ocurrió un error al cargar el detalle.';
                    } finally {
                        this.cargando = false;
                    }
                },

                etiquetaNaturaleza(naturaleza) {
                    if (naturaleza === 'JURISDICCIONAL') {
                        return 'Jurisdiccional';
                    }
                    if (naturaleza === 'ADMINISTRATIVA') {
                        return 'Administrativa';
                    }
                    return '—';
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

                imprimirBoleta() {
                    const boleta = this.expediente?.boleta_apertura;

                    if (boleta?.url) {
                        window.imprimirPdf({
                            url: boleta.url,
                            titulo: 'Boleta de recepción',
                        });
                    }
                },

                volver() {
                    window.location.href = '/ventanilla/aperturas';
                },
            };
        }
    </script>
@endsection
