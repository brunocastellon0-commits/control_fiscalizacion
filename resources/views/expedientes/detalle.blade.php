@extends('layouts.app')

@section('titulo', 'Detalle de Expediente')

@section('contenido')
    @php
        $rolUsuario = auth()->user()?->rol?->codigo;
    @endphp
    <div x-data="detalleExpediente({{ (int) $expedienteId }}, @js($rolUsuario))" x-init="init()" class="p-6" x-cloak>
        <div x-show="loading" class="flex justify-center py-20">
            <i class="fa-solid fa-circle-notch fa-spin text-4xl text-verde-institucional"></i>
        </div>

        <template x-if="!loading && error">
            <div class="bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] rounded-xl p-5 text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                <span x-text="error"></span>
                <a href="/expedientes" class="ml-3 underline font-medium">Volver a la bandeja</a>
            </div>
        </template>

        <template x-if="!loading && !error && expediente">
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-xl border border-gris-claro shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h1 class="text-2xl font-mono font-bold text-grafito" x-text="expediente.nurej_code"></h1>
                            <span class="px-3 py-1 text-xs font-bold rounded bg-gris-claro text-gris border border-gris-claro"
                                x-text="expediente.estado_actual?.nombre || expediente.estado_actual?.codigo || 'Sin estado'"></span>
                        </div>
                        <p class="text-sm text-gris">
                            Vía: <span class="font-medium text-grafito" x-text="expediente.via || '—'"></span>
                            <span class="mx-1">|</span>
                            Naturaleza: <span class="font-medium text-grafito" x-text="expediente.naturaleza || '—'"></span>
                            <span class="mx-1">|</span>
                            Ingreso: <span x-text="formatDate(expediente.fecha_ingreso)"></span>
                        </p>
                        <template x-if="expediente.reglamento">
                            <p class="text-xs text-gris mt-1">
                                Reglamento: <span class="font-semibold text-grafito" x-text="(expediente.reglamento.codigo || '') + (expediente.reglamento.nombre ? ' · ' + expediente.reglamento.nombre : '')"></span>
                            </p>
                        </template>
                        <template x-if="expediente.asignacion_activa?.usuario">
                            <p class="text-xs text-gris mt-1">
                                Asignado a: <span class="font-semibold text-grafito" x-text="(expediente.asignacion_activa.usuario.nombres || '') + ' ' + (expediente.asignacion_activa.usuario.apellidos || '')"></span>
                                <span class="ml-1 px-2 py-0.5 rounded bg-gris-claro text-gris" x-text="expediente.asignacion_activa.rol?.nombre || expediente.asignacion_activa.rol?.codigo || ''"></span>
                            </p>
                        </template>
                    </div>

                    <template x-if="expediente.sem_plazo">
                        <div class="flex items-center gap-3 px-4 py-3 rounded-lg border shadow-sm shrink-0" :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                            <span class="relative inline-flex rounded-full h-3.5 w-3.5" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold uppercase tracking-wide" x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                                <span class="text-xs font-medium" x-show="expediente.sem_plazo.dias_restantes !== null && expediente.sem_plazo.dias_restantes !== undefined">
                                    <span x-text="expediente.sem_plazo.dias_restantes"></span> días hábiles restantes
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="space-y-6 lg:col-span-1">
                        <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                            <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                                <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-list text-verde-institucional mr-2"></i> Hechos</h3>
                            </div>
                            <div class="p-4 text-sm text-gris leading-relaxed whitespace-pre-line" x-text="expediente.resumen_hechos || 'Sin resumen registrado.'"></div>
                        </div>

                        <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                            <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                                <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-users text-verde-institucional mr-2"></i> Partes procesales</h3>
                            </div>
                            <ul class="divide-y divide-gris-claro">
                                <template x-if="(expediente.partes_vigentes || []).length > 0">
                                    <template x-for="parte in expediente.partes_vigentes" :key="parte.id || parte.nombre_completo">
                                        <li class="p-4 flex flex-col gap-1">
                                            <span class="text-[10px] font-bold text-verde-institucional uppercase tracking-wider" x-text="parte.tipo || 'Parte'"></span>
                                            <span class="text-sm font-semibold text-grafito" x-text="parte.nombre_completo"></span>
                                            <span class="text-xs text-gris" x-text="parte.documento_identidad || 'Sin CI/NIT'"></span>
                                            <span class="text-xs text-gris" x-show="parte.cargo_institucion" x-text="parte.cargo_institucion"></span>
                                        </li>
                                    </template>
                                </template>
                                <template x-if="!(expediente.partes_vigentes || []).length">
                                    <li class="p-4 text-sm text-gris">Sin partes registradas.</li>
                                </template>
                            </ul>
                        </div>

                        @include('expedientes.detalle.evaluacion')
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        @include('expedientes.detalle.acciones-operativas')

                        <template x-if="(expediente.plazos || []).length > 0">
                            <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                                <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                                    <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-hourglass-half text-verde-institucional mr-2"></i> Plazos</h3>
                                </div>
                                <ul class="divide-y divide-gris-claro">
                                    <template x-for="plazo in plazosOrdenados()" :key="plazo.id || plazo.tipo_plazo + plazo.fecha_limite">
                                        <li class="p-4 flex flex-col md:flex-row md:items-center gap-2">
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
                                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded text-xs font-bold border shrink-0" :class="getSemaforoClass(plazo.sem_plazo.codigo_color)">
                                                    <span class="rounded-full h-2.5 w-2.5" :class="getSemaforoDotClass(plazo.sem_plazo.codigo_color)"></span>
                                                    <span x-text="semEtiqueta(plazo.sem_plazo.codigo_color)"></span>
                                                </span>
                                            </template>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                            <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro flex items-center justify-between">
                                <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-timeline text-verde-institucional mr-2"></i> Historial de actuados</h3>
                                <span class="text-xs text-gris" x-text="(expediente.actuados || []).length + ' registro(s)'"></span>
                            </div>
                            <div class="p-6 relative">
                                <div class="absolute left-[23px] top-8 bottom-8 w-0.5 bg-gris-claro" x-show="(expediente.actuados || []).length"></div>
                                <template x-if="(expediente.actuados || []).length > 0">
                                    <div class="space-y-6 relative">
                                        <template x-for="actuado in expediente.actuados" :key="actuado.id">
                                            <div class="flex gap-4 items-start">
                                                <div class="flex-shrink-0 w-5 h-5 rounded-full bg-verde-profundo border-2 border-verde-institucional relative z-10 mt-1"></div>
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

                                                    <div class="bg-grafito rounded p-2.5 mt-3 text-[10px] font-mono text-gris-claro break-all leading-tight" x-show="hashVisible === actuado.id">
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="text-xs text-white/80">Cadena de Custodia (SHA-256)</span>
                                                            <button type="button" @click="hashVisible = null" class="text-white/60 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
                                                        </div>
                                                        <div><span class="text-verde-institucional">HASH_ANTERIOR:</span> <span x-text="actuado.hash_anterior || '-'"></span></div>
                                                        <div class="mt-1"><span class="text-verde-institucional">HASH_ACTUADO:</span> <span x-text="actuado.hash_actuado || '-'"></span></div>
                                                    </div>
                                                    <button type="button" x-show="hashVisible !== actuado.id && (actuado.hash_actuado || actuado.hash_anterior)" @click="hashVisible = actuado.id"
                                                        class="mt-2 text-xs text-verde-institucional hover:underline">
                                                        <i class="fa-solid fa-lock mr-1"></i>Ver cadena de custodia
                                                    </button>

                                                    <template x-if="(actuado.adjuntos || []).length > 0">
                                                        <div class="mt-3 pt-3 border-t border-gris-claro flex flex-wrap gap-2">
                                                            <template x-for="adj in actuado.adjuntos" :key="adj.id">
                                                                <a :href="`/api/adjuntos/${adj.id}/descargar`" target="_blank"
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
        </template>

        @include('expedientes.detalle.modal-actuado')
        @include('expedientes.detalle.modal-enmienda')
        @include('expedientes.detalle.remision-unidad')
    </div>

    <script>
        function detalleExpediente(id, rolInicial = null) {
            return {
                expedienteId: id,
                rolInicial: rolInicial,
                expediente: null,
                loading: true,
                error: null,
                hashVisible: null,

                // Actuados / catálogo (B4.5)
                modalActuadoOpen: false,
                catalogoActuados: [],
                catalogoCargado: false,
                cargandoCatalogoActuados: false,
                requiereAdjunto: false,
                archivoSeleccionado: null,
                submittingActuado: false,
                errorActuado: null,
                formActuado: { catalogo_actuado_id: '', descripcion: '' },

                // Evaluación (B4.1)
                requisitos: [],
                cargandoRequisitos: false,
                errorRequisitos: null,
                submittingEvaluacion: false,
                resultadoEvaluacion: null,

                // Enmienda (B4.2 / CTR-09)
                modalEnmiendaOpen: false,
                submittingEnmienda: false,
                errorEnmienda: null,
                formEnmienda: { campo_modificado: '', valor_nuevo: '', motivo: '' },

                // Remisión/recepción (B4.9 / CTR-04,05)
                modalRemisionOpen: false,
                modoRemision: 'remitir',
                submittingRemision: false,
                errorRemision: null,
                archivoRemision: null,
                formRemision: { unidad_id: '', motivo: '' },

                // Incompetencia (B4.2 / CTR-10)
                modalIncompetenciaOpen: false,
                submittingIncompetencia: false,
                formIncompetencia: { unidad_destino_id: '', motivo: '' },

                async init() {
                    await this.cargarExpediente();
                    await this.cargarCatalogoActuados();
                    await this.cargarRequisitos();
                },

                rolUsuarioActual() {
                    return this.rolInicial || '';
                },

                async cargarExpediente() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}`);
                        if (ok) {
                            this.expediente = data.data || data;
                        } else if (status === 403) {
                            window.apiToast('error', data?.message || 'No tiene permisos para ver este expediente.');
                            setTimeout(() => window.location.href = '/expedientes', 1500);
                        } else {
                            this.error = data?.message || 'No se pudo cargar el expediente.';
                        }
                    } catch (e) {
                        this.error = e.message || 'Error al cargar el expediente.';
                    } finally {
                        this.loading = false;
                    }
                },

                async cargarCatalogoActuados() {
                    this.cargandoCatalogoActuados = true;
                    try {
                        const params = new URLSearchParams();
                        params.set('expediente_id', String(this.expedienteId));
                        const estadoOrigenId = this.expediente?.estado_actual?.id || this.expediente?.estado_actual_id;
                        if (estadoOrigenId) params.set('estado_origen_id', String(estadoOrigenId));
                        const { ok, data } = await window.apiFetch(`/api/catalogo/actuados?${params.toString()}`);
                        if (ok) {
                            this.catalogoActuados = data.data || [];
                        } else {
                            this.catalogoActuados = [];
                        }
                    } catch (e) {
                        this.catalogoActuados = [];
                    } finally {
                        this.cargandoCatalogoActuados = false;
                        this.catalogoCargado = true;
                    }
                },

                async abrirModalActuado() {
                    this.modalActuadoOpen = true;
                    this.errorActuado = null;
                    this.formActuado = { catalogo_actuado_id: '', descripcion: '' };
                    this.archivoSeleccionado = null;
                    this.requiereAdjunto = false;
                    const input = document.getElementById('adjuntoActuado');
                    if (input) input.value = '';
                    await this.cargarCatalogoActuados();
                },
                cerrarModalActuado() { this.modalActuadoOpen = false; },

                verificarRequisitoAdjunto() {
                    const seleccionado = this.catalogoActuados.find(c => String(c.id) === String(this.formActuado.catalogo_actuado_id));
                    this.requiereAdjunto = !!(seleccionado && (seleccionado.requiere_adjunto || seleccionado.requiereAdjunto));
                },
                manejarArchivoActuado(event) { this.archivoSeleccionado = event.target.files[0] || null; },

                async submitActuado() {
                    this.submittingActuado = true;
                    this.errorActuado = null;
                    try {
                        const formData = new FormData();
                        formData.append('catalogo_actuado_id', this.formActuado.catalogo_actuado_id);
                        formData.append('descripcion', this.formActuado.descripcion || '');
                        if (this.requiereAdjunto && this.archivoSeleccionado) {
                            formData.append('adjunto', this.archivoSeleccionado);
                        }
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/actuados`, {
                            method: 'POST',
                            body: formData,
                        });
                        if (ok || status === 201) {
                            window.apiToast('exito', data?.message || 'Actuado registrado correctamente.');
                            this.modalActuadoOpen = false;
                            await this.cargarExpediente();
                            await this.cargarCatalogoActuados();
                        } else {
                            this.errorActuado = data?.message || 'No se pudo registrar el actuado.';
                            if (data?.errors) window.apiToast('error', this.errorActuado, data.errors);
                        }
                    } catch (e) {
                        this.errorActuado = e.message || 'Error al registrar el actuado.';
                    } finally {
                        this.submittingActuado = false;
                    }
                },

                async cargarRequisitos() {
                    this.cargandoRequisitos = true;
                    this.errorRequisitos = null;
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/requisitos`);
                        if (ok) {
                            const lista = data.data || data || [];
                            this.requisitos = (Array.isArray(lista) ? lista : []).map(r => ({
                                ...r,
                                _cumplido: !!(r.cumplido ?? r.cumple ?? false),
                                _observacion: r.observacion || '',
                            }));
                        } else if (status === 403 || status === 404) {
                            this.requisitos = [];
                            this.errorRequisitos = data?.message || null;
                        } else {
                            this.errorRequisitos = data?.message || 'No se pudieron cargar los requisitos.';
                        }
                    } catch (e) {
                        this.errorRequisitos = e.message || 'Error al cargar requisitos.';
                        this.requisitos = [];
                    } finally {
                        this.cargandoRequisitos = false;
                    }
                },

                async submitEvaluacion() {
                    this.submittingEvaluacion = true;
                    this.resultadoEvaluacion = null;
                    try {
                        const payload = {
                            evaluaciones: this.requisitos.map(r => ({
                                requisito_id: r.id,
                                cumplido: !!r._cumplido,
                                observacion: r._cumplido ? null : (r._observacion || null),
                            })),
                            requisitos: this.requisitos.map(r => ({
                                requisito_id: r.id,
                                cumplido: !!r._cumplido,
                                observacion: r._cumplido ? null : (r._observacion || null),
                            })),
                        };
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/evaluacion`, {
                            method: 'POST',
                            body: payload,
                        });
                        if (ok || status === 201) {
                            this.resultadoEvaluacion = data?.resumen || data?.data || data || { resultado: 'Registrado' };
                            window.apiToast('exito', 'Evaluación de admisibilidad registrada.');
                            await this.cargarExpediente();
                            await this.cargarCatalogoActuados();
                            await this.cargarRequisitos();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo registrar la evaluación.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al registrar la evaluación.');
                    } finally {
                        this.submittingEvaluacion = false;
                    }
                },

                abrirModalEnmienda() {
                    this.formEnmienda = { campo_modificado: '', valor_nuevo: '', motivo: '' };
                    this.errorEnmienda = null;
                    this.modalEnmiendaOpen = true;
                },
                cerrarModalEnmienda() { this.modalEnmiendaOpen = false; },
                async submitEnmienda() {
                    this.submittingEnmienda = true;
                    this.errorEnmienda = null;
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/enmienda`, {
                            method: 'POST',
                            body: { ...this.formEnmienda },
                        });
                        if (ok || status === 201 || status === 200) {
                            window.apiToast('exito', data?.message || 'Enmienda registrada correctamente.');
                            this.modalEnmiendaOpen = false;
                            await this.cargarExpediente();
                        } else {
                            this.errorEnmienda = data?.message || 'No se pudo registrar la enmienda.';
                        }
                    } catch (e) {
                        this.errorEnmienda = e.message || 'Error al registrar la enmienda.';
                    } finally {
                        this.submittingEnmienda = false;
                    }
                },

                abrirModalIncompetencia() {
                    this.formIncompetencia = { unidad_destino_id: '', motivo: '' };
                    this.modalIncompetenciaOpen = true;
                },
                async submitIncompetenciaSolicitud() {
                    if (!this.formIncompetencia.motivo) {
                        window.apiToast('error', 'Ingrese el motivo o justificación.');
                        return;
                    }
                    this.submittingIncompetencia = true;
                    try {
                        const payload = {
                            unidad_destino_id: this.formIncompetencia.unidad_destino_id || null,
                            motivo: this.formIncompetencia.motivo,
                            justificacion: this.formIncompetencia.motivo,
                        };
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/incompetencia/solicitar`, {
                            method: 'POST',
                            body: payload,
                        });
                        if (ok || status === 201) {
                            window.apiToast('exito', data?.message || 'Solicitud de incompetencia enviada.');
                            this.modalIncompetenciaOpen = false;
                            await this.cargarExpediente();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo enviar la solicitud de incompetencia.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al solicitar incompetencia.');
                    } finally {
                        this.submittingIncompetencia = false;
                    }
                },
                async resolverIncompetencia(aprobado) {
                    const observaciones = aprobado ? null : prompt('Observaciones (opcional):');
                    if (!aprobado && observaciones === null) return;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/incompetencia/visto-bueno`, {
                            method: 'POST',
                            body: { aprobado: !!aprobado, observaciones: observaciones || null },
                        });
                        if (ok) {
                            window.apiToast('exito', data?.message || (aprobado ? 'Incompetencia aprobada.' : 'Incompetencia rechazada.'));
                            await this.cargarExpediente();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo resolver la incompetencia.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al resolver la incompetencia.');
                    }
                },

                abrirModalRemision(modo) {
                    this.modoRemision = modo;
                    this.formRemision = { unidad_id: '', motivo: '' };
                    this.archivoRemision = null;
                    this.errorRemision = null;
                    const input = document.getElementById('adjuntoRemision');
                    if (input) input.value = '';
                    this.modalRemisionOpen = true;
                },
                cerrarModalRemision() { this.modalRemisionOpen = false; },
                manejarArchivoRemision(event) { this.archivoRemision = event.target.files[0] || null; },
                async submitRemision() {
                    this.submittingRemision = true;
                    this.errorRemision = null;
                    try {
                        const endpoint = this.modoRemision === 'remitir'
                            ? `/api/expedientes/${this.expedienteId}/remitir-unidad`
                            : `/api/expedientes/${this.expedienteId}/registrar-recepcion`;

                        const formData = new FormData();
                        if (this.modoRemision === 'remitir') {
                            formData.append('unidad_id', this.formRemision.unidad_id);
                        } else {
                            formData.append('unidad_origen_id', this.formRemision.unidad_id);
                        }
                        formData.append('motivo', this.formRemision.motivo);
                        if (this.archivoRemision) formData.append('adjunto', this.archivoRemision);

                        const { ok, status, data } = await window.apiFetch(endpoint, { method: 'POST', body: formData });
                        if (ok || status === 201) {
                            window.apiToast('exito', data?.message || (this.modoRemision === 'remitir' ? 'Causa remitida correctamente.' : 'Recepción registrada correctamente.'));
                            this.modalRemisionOpen = false;
                            await this.cargarExpediente();
                        } else {
                            this.errorRemision = data?.message || 'No se pudo completar la operación.';
                            if (data?.errors) window.apiToast('error', this.errorRemision, data.errors);
                        }
                    } catch (e) {
                        this.errorRemision = e.message || 'Error al procesar la solicitud.';
                    } finally {
                        this.submittingRemision = false;
                    }
                },

                plazosOrdenados() {
                    return [...(this.expediente?.plazos || [])].sort((a, b) => new Date(a.fecha_limite || 0) - new Date(b.fecha_limite || 0));
                },
                semEtiqueta(color) {
                    const map = { VERDE: 'En plazo', AMARILLO: 'Próximo a vencer', ROJO: 'Vence pronto', FUERA_DE_PLAZO: 'Fuera de plazo' };
                    return map[color] || (color || 'Sin semáforo');
                },
                getSemaforoClass(color) {
                    const map = {
                        VERDE: 'bg-[#8CC63F]/20 text-[#3F5E1B] border-[#8CC63F]/40',
                        AMARILLO: 'bg-amber-50 text-amber-800 border-amber-200',
                        ROJO: 'bg-[#F15A24]/10 text-[#B53F12] border-[#F15A24]/30',
                        FUERA_DE_PLAZO: 'bg-grafito/10 text-grafito border-grafito/30'
                    };
                    return map[color] || 'bg-gris-claro text-gris border-gris-claro';
                },
                getSemaforoDotClass(color) {
                    const map = { VERDE: 'bg-[#8CC63F]', AMARILLO: 'bg-amber-500', ROJO: 'bg-[#F15A24]', FUERA_DE_PLAZO: 'bg-grafito' };
                    return map[color] || 'bg-gris';
                },
                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return isNaN(d) ? '' : d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
                },
                formatDateTime(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return isNaN(d) ? '' : d.toLocaleString('es-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                }
            };
        }
    </script>
@endsection
