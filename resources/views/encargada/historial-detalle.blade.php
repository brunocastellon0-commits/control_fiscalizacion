@extends('layouts.app')

@section('titulo', 'Detalle del historial general')

@section('contenido')
    <div class="p-6" x-data="detalleHistorialEncargada({{ (int) $expedienteId }})" x-init="cargar()" x-cloak>
        <div class="flex flex-col gap-4 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-mono font-bold text-grafito" x-text="expediente?.nurej_code || 'Cargando...'"></h1>
                        <span class="px-2 py-1 text-xs font-bold rounded bg-gris-claro text-gris">Solo lectura</span>
                    </div>
                    <p class="text-sm text-gris mt-1">Información general y estado del expediente para supervisión.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 self-start">
                    <button type="button" x-show="expediente?.puede_sortear" @click="irASortear()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#B53F12] text-white text-sm font-semibold hover:opacity-90">
                        <i class="fa-solid fa-shuffle"></i> Sortear
                    </button>
                    <button type="button" x-show="expediente?.boleta_apertura" @click="descargarDocumento(expediente.boleta_apertura.url)"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-grafito hover:bg-gris-claro/40">
                        <i class="fa-solid fa-file-arrow-down"></i> Boleta
                    </button>
                    <button type="button" x-show="expediente?.ficha_sorteo" @click="descargarDocumento(expediente.ficha_sorteo.url)"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-grafito hover:bg-gris-claro/40">
                        <i class="fa-solid fa-file-arrow-down"></i> Ficha
                    </button>
                    <button type="button" x-show="expediente && !expediente.ficha_sorteo && !expediente.puede_sortear" @click="generarFicha()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-[#B53F12] hover:bg-gris-claro/40">
                        <i class="fa-solid fa-rotate"></i> Generar ficha
                    </button>
                    <button type="button" @click="volver()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gris-claro bg-white text-sm font-semibold text-grafito hover:bg-gris-claro/40">
                        <i class="fa-solid fa-arrow-left"></i> Volver al historial general
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
                            <p class="text-xs text-gris uppercase tracking-wide">Registrado por</p>
                            <p class="text-grafito" x-text="nombrePersona(expediente.creador)"></p>
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
                            <i class="fa-solid fa-circle-check text-verde-institucional mr-2"></i> Estado del expediente
                        </h2>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Estado actual</p>
                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                <span class="px-2 py-0.5 rounded bg-gris-claro text-grafito text-xs font-semibold"
                                    x-text="expediente.estado_actual?.nombre || expediente.estado_actual?.codigo || '—'"></span>
                                <span x-show="expediente.estado_actual?.es_final"
                                    class="px-2 py-0.5 rounded bg-green-50 border border-green-200 text-green-800 text-xs font-semibold">
                                    Finalizado
                                </span>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-gris uppercase tracking-wide">Responsable actual</p>
                            <p class="text-grafito" x-text="nombreResponsable(expediente.responsable)"></p>
                            <p class="text-xs text-gris mt-0.5" x-text="expediente.responsable?.rol?.nombre || ''"></p>
                        </div>
                        <template x-if="expediente.sem_plazo">
                            <div class="md:col-span-2">
                                <p class="text-xs text-gris uppercase tracking-wide">Situación del plazo</p>
                                <div class="flex flex-wrap items-center gap-3 mt-1">
                                    <span class="px-3 py-1.5 rounded-lg border text-xs font-semibold"
                                        :class="claseSemaforo(expediente.sem_plazo.codigo_color)"
                                        x-text="etiquetaSemaforo(expediente.sem_plazo.codigo_color)"></span>
                                    <span class="text-xs text-gris"
                                        x-text="'Fecha límite: ' + formatoFecha(expediente.sem_plazo.fecha_limite)"></span>
                                    <template x-if="expediente.sem_plazo.dias_restantes !== null">
                                        <span class="text-xs text-gris"
                                            x-text="expediente.sem_plazo.dias_restantes + ' día(s) restante(s)'"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>
        </template>
    </div>

    <script>
        function detalleHistorialEncargada(id) {
            return {
                expedienteId: id,
                expediente: null,
                cargando: true,
                error: null,

                async cargar() {
                    this.cargando = true;
                    this.error = null;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/encargada/historial/${this.expedienteId}`);
                        if (ok) {
                            this.expediente = data.data || data;
                        } else {
                            this.error = data?.message || 'No se pudo cargar el detalle del expediente.';
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

                nombrePersona(persona) {
                    if (!persona) {
                        return '—';
                    }
                    return [persona.nombres, persona.apellidos].filter(Boolean).join(' ').trim() || '—';
                },

                nombreResponsable(responsable) {
                    if (!responsable?.usuario) {
                        return 'Sin asignar';
                    }
                    return this.nombrePersona(responsable.usuario);
                },

                etiquetaSemaforo(color) {
                    const mapa = {
                        VERDE: 'En plazo',
                        AMARILLO: 'Próximo a vencer',
                        ROJO: 'Vence pronto',
                        FUERA_DE_PLAZO: 'Fuera de plazo',
                    };
                    return mapa[color] || color || 'Sin semáforo';
                },

                claseSemaforo(color) {
                    const mapa = {
                        VERDE: 'bg-[#8CC63F]/20 text-[#3F5E1B] border-[#8CC63F]/40',
                        AMARILLO: 'bg-amber-50 text-amber-800 border-amber-200',
                        ROJO: 'bg-[#F15A24]/10 text-[#B53F12] border-[#F15A24]/30',
                        FUERA_DE_PLAZO: 'bg-grafito/10 text-grafito border-grafito/30',
                    };
                    return mapa[color] || 'bg-gris-claro text-gris border-gris-claro';
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

                descargarDocumento(url) {
                    if (url) {
                        window.open(url, '_blank');
                    }
                },

                async generarFicha() {
                    if (!this.expediente) {
                        return;
                    }

                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.expediente.id}/ficha-sorteo/regenerar`, {
                            method: 'POST',
                        });

                        if (ok) {
                            this.expediente.ficha_sorteo = data?.data || null;
                            window.apiToast('exito', 'Ficha de sorteo generada correctamente.');
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo generar la ficha de sorteo.');
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'No se pudo generar la ficha de sorteo.');
                    }
                },

                irASortear() {
                    if (this.expediente?.id) {
                        window.location.href = `/bandeja/sorteo?expediente=${this.expediente.id}`;
                    }
                },

                volver() {
                    window.location.href = '/encargada/historial';
                },
            };
        }
    </script>
@endsection
