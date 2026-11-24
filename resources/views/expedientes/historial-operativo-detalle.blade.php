@extends('layouts.app')

@section('titulo', 'Consulta del historial')

@section('contenido')
    <div class="p-6" x-data="detalleHistorialOperativo({{ (int) $expedienteId }})" x-init="cargar()" x-cloak>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-mono font-bold text-grafito" x-text="expediente?.nurej_code || 'Cargando...'"></h1>
                    <span class="px-2 py-1 text-xs font-bold rounded bg-gris-claro text-gris">Solo consulta</span>
                    <template x-if="expediente?.es_final">
                        <span class="px-2 py-1 text-xs font-bold rounded bg-verde-institucional/10 text-verde-profundo">Expediente finalizado</span>
                    </template>
                </div>
                <p class="text-sm text-gris mt-1">Información completa del expediente. Esta vista no permite modificarlo ni descargar adjuntos.</p>
            </div>
            <button type="button" @click="volver()"
                class="inline-flex items-center gap-2 self-start px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-grafito hover:bg-gris-claro/40">
                <i class="fa-solid fa-arrow-left"></i> Volver al historial
            </button>
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
            <div class="space-y-5">
                <template x-if="!expediente.es_final">
                    <section class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                        <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                            <h2 class="text-sm font-bold text-grafito uppercase">
                                <i class="fa-solid fa-list-check text-verde-institucional mr-2"></i> Etapa del expediente
                            </h2>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                                <template x-for="paso in expediente.pasos || []" :key="paso.codigo">
                                    <button type="button" @click="pasoSeleccionado = paso.codigo"
                                        class="rounded-lg border px-3 py-2 text-left text-xs transition"
                                        :class="pasoSeleccionado === paso.codigo
                                            ? 'border-verde-institucional bg-verde-institucional/10 text-verde-profundo'
                                            : 'border-gris-claro text-gris hover:border-verde-institucional/50'">
                                        <span class="block font-bold" x-text="`Paso ${paso.numero}: ${paso.nombre}`"></span>
                                        <span class="block mt-0.5" x-text="etiquetaPaso(paso.estado)"></span>
                                    </button>
                                </template>
                            </div>
                            <template x-if="pasoActual()">
                                <div class="mt-4 rounded-lg bg-gris-claro/60 px-4 py-3 text-sm text-grafito">
                                    <p class="font-semibold" x-text="`${pasoActual().nombre}: ${etiquetaPaso(pasoActual().estado)}`"></p>
                                    <p class="text-xs text-gris mt-0.5" x-text="pasoActual().descripcion"></p>
                                </div>
                            </template>
                        </div>
                    </section>
                </template>

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
                            <p class="text-xs text-gris uppercase tracking-wide">Fecha de ingreso</p>
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
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Responsable actual</p>
                            <p class="text-grafito" x-text="nombreResponsable(expediente.asignacion_activa)"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Registrado por</p>
                            <p class="text-grafito" x-text="nombreResponsable(expediente.creador)"></p>
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
                        <template x-if="(expediente.partes_vigentes || []).length === 0">
                            <p class="text-sm text-gris text-center py-2">No hay partes registradas.</p>
                        </template>
                        <template x-if="(expediente.partes_vigentes || []).length > 0">
                            <ul class="divide-y divide-gris-claro">
                                <template x-for="parte in expediente.partes_vigentes" :key="parte.id">
                                    <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-grafito" x-text="parte.nombre_completo || '—'"></span>
                                        <span class="px-2 py-0.5 rounded bg-gris-claro text-gris text-xs font-semibold" x-text="parte.tipo || '—'"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </section>

                <section class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                        <h2 class="text-sm font-bold text-grafito uppercase">
                            <i class="fa-solid fa-hourglass-half text-verde-institucional mr-2"></i> Plazos
                        </h2>
                    </div>
                    <div class="p-4">
                        <template x-if="(expediente.plazos || []).length === 0">
                            <p class="text-sm text-gris text-center py-2">No hay plazos registrados.</p>
                        </template>
                        <template x-if="(expediente.plazos || []).length > 0">
                            <ul class="divide-y divide-gris-claro">
                                <template x-for="plazo in expediente.plazos" :key="plazo.id">
                                    <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span class="font-medium text-grafito" x-text="plazo.tipo_plazo || 'Plazo'"></span>
                                        <span class="text-gris" x-text="`${formatoFecha(plazo.fecha_inicio)} al ${formatoFecha(plazo.fecha_limite)}`"></span>
                                        <span class="px-2 py-0.5 rounded bg-gris-claro text-gris text-xs font-semibold" x-text="plazo.estado || '—'"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </section>

                <section class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                    <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                        <h2 class="text-sm font-bold text-grafito uppercase">
                            <i class="fa-solid fa-clock text-verde-institucional mr-2"></i> Línea de tiempo de actuados
                        </h2>
                    </div>
                    <div class="p-4">
                        <template x-if="(expediente.actuados || []).length === 0">
                            <p class="text-sm text-gris text-center py-2">Aún no hay actuados registrados.</p>
                        </template>
                        <template x-if="(expediente.actuados || []).length > 0">
                            <ol class="space-y-4">
                                <template x-for="actuado in expediente.actuados" :key="actuado.id">
                                    <li class="flex items-start gap-3">
                                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-verde-profundo ring-2 ring-white"></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-grafito"
                                                x-text="actuado.tipo_actuado?.nombre || actuado.tipo_actuado?.codigo || 'Actuado'"></p>
                                            <p class="text-xs text-gris" x-text="formatoFecha(actuado.fecha_hora)"></p>
                                            <p class="text-sm text-grafito mt-0.5" x-text="actuado.descripcion || '—'"></p>
                                            <p class="text-xs text-gris mt-0.5"
                                                x-show="actuado.estado_nuevo?.nombre || actuado.estado_nuevo?.codigo"
                                                x-text="'Estado: ' + (actuado.estado_nuevo?.nombre || actuado.estado_nuevo?.codigo || '')"></p>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                        </template>
                    </div>
                </section>
            </div>
        </template>
    </div>

    <script>
        function detalleHistorialOperativo(id) {
            return {
                expedienteId: id,
                expediente: null,
                cargando: true,
                error: null,
                pasoSeleccionado: null,

                async cargar() {
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/operativo/historial/${this.expedienteId}`);
                        if (ok) {
                            this.expediente = data.data || data;
                            const activo = (this.expediente.pasos || []).find((paso) => paso.estado === 'activo');
                            const completados = (this.expediente.pasos || []).filter((paso) => paso.estado === 'completado');
                            this.pasoSeleccionado = activo?.codigo || completados[completados.length - 1]?.codigo || null;
                        } else {
                            this.error = data?.message || 'No se pudo cargar el detalle del expediente.';
                        }
                    } catch (e) {
                        this.error = e.message || 'Ocurrió un error al cargar el detalle.';
                    } finally {
                        this.cargando = false;
                    }
                },

                pasoActual() {
                    return (this.expediente?.pasos || []).find((paso) => paso.codigo === this.pasoSeleccionado) || null;
                },

                etiquetaPaso(estado) {
                    const etiquetas = { completado: 'Completado', activo: 'En curso', bloqueado: 'Bloqueado' };
                    return etiquetas[estado] || '—';
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

                nombreResponsable(dato) {
                    const usuario = dato?.usuario || dato;
                    if (!usuario?.nombres && !usuario?.apellidos) {
                        return 'Sin asignar';
                    }
                    return [usuario.nombres, usuario.apellidos].filter(Boolean).join(' ').trim();
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

                volver() {
                    window.location.href = '/operativo/historial';
                },
            };
        }
    </script>
@endsection
