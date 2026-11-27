@extends('layouts.app')

@section('titulo', 'Detalle de Expediente')

@section('contenido')
    @php
        $rolUsuario = auth()->user()?->rol?->codigo;
    @endphp
    <div x-data="detalleExpediente({{ (int) $expedienteId }}, @js($rolUsuario))" x-init="init()" @keydown.escape.window="hashActuado = null; avisoPaso = null" class="p-6" x-cloak>
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
                <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                    <div class="border-b border-gris-claro p-5">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-verde-institucional mb-1">Carátula del expediente</p>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-mono font-bold text-grafito" x-text="expediente.nurej_code"></h1>
                                <span class="px-3 py-1 text-xs font-bold rounded bg-gris-claro text-gris border border-gris-claro"
                                    x-text="expediente.estado_actual?.nombre || expediente.estado_actual?.codigo || 'Sin estado'"></span>
                            </div>
                            <p class="text-sm text-gris">
                                Origen:
                                <span class="font-medium text-grafito" x-text="expediente.via === 'INGRESO_DISTRITAL' ? 'Ingreso desde distrital' : (expediente.via === 'INGRESO_VERBAL' ? 'Ingreso verbal' : (expediente.via || '—'))"></span>
                                <template x-if="expediente.numero_hoja_ruta">
                                    <span> (Hoja de ruta: <span class="font-medium text-grafito" x-text="expediente.numero_hoja_ruta"></span>)</span>
                                </template>
                                <span class="mx-1">|</span>
                                Ingreso: <span x-text="formatDate(expediente.fecha_ingreso)"></span>
                            </p>
                            <template x-if="expediente.asignacion_activa?.usuario">
                                <p class="text-xs text-gris">
                                    Responsable:
                                    <span class="font-semibold text-grafito" x-text="(expediente.asignacion_activa.usuario.nombres || '') + ' ' + (expediente.asignacion_activa.usuario.apellidos || '')"></span>
                                    <span class="ml-1 px-2 py-0.5 rounded bg-gris-claro text-gris" x-text="expediente.asignacion_activa.rol?.nombre || expediente.asignacion_activa.rol?.codigo || ''"></span>
                                </p>
                            </template>
                        </div>
                    </div>

                    <div class="border-b border-gris-claro px-4 py-3 sm:px-5">
                        @include('expedientes.detalle.pasos')
                    </div>

                    <div x-show="avisoPaso" x-cloak class="border-t border-amber-300 bg-amber-50 px-5 py-3 flex items-start gap-3 text-sm text-amber-900">
                        <i class="fa-solid fa-circle-info mt-0.5 shrink-0"></i>
                        <div class="flex-1">
                            <p class="font-semibold" x-text="avisoPaso?.titulo"></p>
                            <p class="mt-0.5" x-text="avisoPaso?.mensaje"></p>
                        </div>
                        <button type="button" @click="avisoPaso = null" title="Cerrar aviso" aria-label="Cerrar aviso" class="text-amber-700 hover:text-amber-900">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gris-claro shadow-sm" x-show="pasoActivo === 'recepcion'" x-cloak>
                    <div class="flex gap-1 px-4 overflow-x-auto border-b border-gris-claro" role="tablist" aria-label="Secciones del expediente">
                        <template x-for="seccion in secciones" :key="seccion.codigo">
                            <button type="button" role="tab"
                                @click="seleccionarSeccion(seccion.codigo)"
                                :aria-selected="seccionActiva === seccion.codigo ? 'true' : 'false'"
                                :title="seccion.titulo"
                                class="px-4 py-3 text-xs font-bold uppercase tracking-wider border-b-2 transition-colors whitespace-nowrap"
                                :class="seccionActiva === seccion.codigo
                                    ? 'border-verde-institucional text-verde-profundo'
                                    : 'border-transparent text-gris hover:text-grafito hover:border-gris-claro'">
                                <i class="fa-solid mr-1.5" :class="seccion.icono"></i>
                                <span x-text="seccion.etiqueta"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
                    <div class="xl:col-span-2 flex flex-col gap-6">
                        <div x-show="pasoActivo === 'recepcion'">
                            @include('expedientes.detalle.recepcion')
                        </div>

                        <template x-if="seccionActiva === 'resumen'">
                            <div class="flex flex-col gap-6">
                                <template x-if="pasoActivo === 'admisibilidad'">
                                    <div>@include('expedientes.detalle.evaluacion')</div>
                                </template>

                                <template x-if="pasoActivo === 'planificacion'">
                                    <div>@include('expedientes.detalle.planificacion')</div>
                                </template>

                                <template x-if="pasoActivo === 'ejecucion'">
                                    <div>@include('expedientes.detalle.ejecucion')</div>
                                </template>

                                <template x-if="pasoActivo === 'cierre'">
                                    <div>@include('expedientes.detalle.cierre')</div>
                                </template>
                            </div>
                        </template>

                        <div>@include('expedientes.detalle.acciones-operativas')</div>

                        <div>@include('expedientes.detalle.historial-actuados')</div>

                        <template x-if="(trazabilidadAbierta || seccionActiva === 'historial') && esAdmin()">
                            <div>@include('expedientes.detalle.trazabilidad')</div>
                        </template>
                    </div>

                    <aside class="space-y-6">
                        @include('expedientes.detalle.estado-expediente')
                    </aside>
                </div>

            </div>
        </template>

        @include('expedientes.detalle.modal-actuado')
        @include('expedientes.detalle.modal-enmienda')
        @include('expedientes.detalle.remision-unidad')
        @include('expedientes.detalle.modal-hash')
    </div>

    <script>
        function detalleExpediente(id, rolInicial = null) {
            return {
                expedienteId: id,
                rolInicial: rolInicial,
                expediente: null,
                loading: true,
                error: null,
                hashActuado: null,

                pasoActivo: 'recepcion',
                seccionActiva: 'resumen',
                avisoPaso: null,
                trazabilidadAbierta: false,
                seguimientoPestana: 'plazos',

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

                // Planificación (paso 3)
                submittingPlanificacion: false,
                errorPlanificacion: null,
                formPlanificacion: { descripcion: '', fecha_limite_propuesta: '' },
                archivoPlanificacion: null,
                submittingVistoBuenoPlanificacion: false,
                formVistoBuenoPlanificacion: { descripcion: '' },
                submittingDevolverPlanificacion: false,
                formDevolverPlanificacion: { justificacion: '' },

                // Ejecución (paso 4)
                submittingAmpliacion: false,
                formAmpliacion: { justificacion: '' },
                submittingDescargos: false,
                formDescargos: { comunicar: { descripcion: '' }, recibir: { descripcion: '' } },
                archivoComunicacion: null,
                archivoRecepcion: null,

                // Cierre (paso 5)
                submittingVistoBuenoFinal: false,
                formVistoBuenoFinal: { descripcion: '' },
                submittingReparto: false,
                formReparto: { destino: '', justificacion: '' },
                destinosReparto: ['Juzgado Disciplinario', 'Sumariante', 'Asesoría Legal', 'Asesoría Jurídica'],

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

                get pasos() {
                    return this.expediente?.pasos || [];
                },

                get secciones() {
                    return [
                        { codigo: 'resumen', etiqueta: 'Resumen', icono: 'fa-th-large', titulo: 'Muestra la carátula, las partes y los hechos del expediente' },
                        { codigo: 'partes', etiqueta: 'Partes Procesales', icono: 'fa-users', titulo: 'Muestra denunciante y denunciado en una sola sección' },
                        { codigo: 'hechos', etiqueta: 'Hechos', icono: 'fa-file-lines', titulo: 'Muestra el resumen de los hechos denunciados' },
                        { codigo: 'historial', etiqueta: 'Historial', icono: 'fa-clock', titulo: 'Muestra plazos y línea de tiempo de actuados' },
                    ];
                },

                async init() {
                    await this.cargarExpediente();
                    await this.cargarCatalogoActuados();
                    await this.cargarRequisitos();
                },

                rolUsuarioActual() {
                    return this.rolInicial || '';
                },

                esEncargada() {
                    return this.rolUsuarioActual() === 'ENCARGADA';
                },

                esAdmin() {
                    return this.rolUsuarioActual() === 'ADMIN';
                },

                estadoPrincipal() {
                    const estado = this.expediente?.estado_actual;
                    return estado?.estado_padre?.nombre || estado?.nombre || 'Sin estado';
                },

                subestado() {
                    const estado = this.expediente?.estado_actual;
                    return estado?.estado_padre ? (estado?.nombre || '—') : '—';
                },

                plazoVigenteFase() {
                    return [...(this.expediente?.plazos || [])]
                        .filter(plazo => plazo.estado === 'VIGENTE')
                        .sort((a, b) => new Date(a.fecha_limite || 0) - new Date(b.fecha_limite || 0))[0] || null;
                },

                actuadosDescendentes() {
                    return [...(this.expediente?.actuados || [])]
                        .sort((a, b) => (b.id || 0) - (a.id || 0));
                },

                abrirAdjuntoActuado(actuado) {
                    const adjunto = (actuado?.adjuntos || [])[0];
                    if (!adjunto) {
                        return;
                    }
                    window.open(`/api/adjuntos/${adjunto.id}/descargar`, '_blank');
                },

                esAc022() {
                    return this.expediente?.reglamento?.codigo === 'AC_022_2018';
                },

                esAc055() {
                    return this.expediente?.reglamento?.codigo === 'AC_055_2018';
                },

                estadoCodigo() {
                    return this.expediente?.estado_actual?.codigo || '';
                },

                estadoFinal() {
                    return this.expediente?.estado_actual?.es_final === true
                        || ['CONCLUIDO', 'CONCLUIDO_REMITIDO', 'DERIVADO_TRANSPARENCIA', 'ARCHIVO_DEFINITIVO', 'ARCHIVO_POR_ABANDONO']
                            .includes(this.estadoCodigo());
                },

                puedeEnmendar() {
                    return !this.esEncargada() && this.pasoEditable();
                },

                puedeSolicitarIncompetencia() {
                    return !this.esEncargada() && this.pasoEditable();
                },

                pasoPorCodigo(codigo) {
                    return this.pasos.find(paso => paso.codigo === codigo) || null;
                },

                etiquetaPaso(codigo) {
                    const etiquetas = { completado: 'Completado', activo: 'En curso', bloqueado: 'Bloqueado' };
                    return etiquetas[this.pasoPorCodigo(codigo)?.estado] || '—';
                },

                pasoActivoServicio() {
                    return this.pasos.find(paso => paso.estado === 'activo') || null;
                },

                progresoPasos() {
                    const total = this.pasos.length;
                    if (total === 0) {
                        return 0;
                    }
                    if (total === 1) {
                        return this.pasos[0].estado === 'completado' ? 100 : 0;
                    }

                    const indiceActivo = this.pasos.findIndex(paso => paso.estado === 'activo');
                    if (indiceActivo >= 0) {
                        return Math.min(100, Math.max(0, (indiceActivo / (total - 1)) * 100));
                    }

                    const completados = this.pasos.filter(paso => paso.estado === 'completado').length;
                    if (completados === 0) {
                        return 0;
                    }

                    return Math.min(100, ((completados - 1) / (total - 1)) * 100);
                },

                etiquetaProgresoPasos() {
                    const paso = this.pasoActivoServicio();
                    if (paso) {
                        return `Paso ${paso.numero} de ${this.pasos.length} · ${paso.nombre}`;
                    }

                    const completados = this.pasos.filter(paso => paso.estado === 'completado');
                    const ultimo = completados[completados.length - 1];

                    return ultimo
                        ? `Paso ${ultimo.numero} de ${this.pasos.length} · ${ultimo.nombre} · completado`
                        : '';
                },

                pasoCompletado(codigo = this.pasoActivo) {
                    return this.pasoPorCodigo(codigo)?.estado === 'completado';
                },

                pasoEditable() {
                    const paso = this.pasoPorCodigo(this.pasoActivo);
                    return !!paso
                        && paso.codigo !== 'recepcion'
                        && paso.estado === 'activo'
                        && !this.estadoFinal();
                },

                resultadoEvaluacionGuardado() {
                    if (this.tieneActuado('ACT_ADMISION')) {
                        return { resultado: 'ACT_ADMISION' };
                    }
                    if (this.tieneActuado('ACT_OBSERVACION')) {
                        return {
                            resultado: 'ACT_OBSERVACION',
                            requisitos_faltantes: (this.requisitos || []).filter(req => !req._cumplido).length,
                        };
                    }
                    if (this.tieneActuado('ACT_RECHAZO')) {
                        return {
                            resultado: 'ACT_RECHAZO',
                            faltantes_criticos: (this.requisitos || []).filter(req => req.es_critico && !req._cumplido).map(req => req.id),
                        };
                    }
                    return null;
                },

                seleccionarPaso(paso) {
                    if (!paso) {
                        return;
                    }
                    if (paso.estado === 'bloqueado') {
                        this.avisoPaso = {
                            titulo: `Paso ${paso.numero} bloqueado: ${paso.nombre}`,
                            mensaje: paso.mensaje_bloqueo || 'Complete el paso anterior para continuar.',
                        };
                        return;
                    }
                    this.pasoActivo = paso.codigo;
                    this.seccionActiva = 'resumen';
                    this.trazabilidadAbierta = false;
                    this.avisoPaso = paso.estado === 'completado' && paso.codigo !== 'recepcion'
                        ? {
                            titulo: `Paso ${paso.numero} completado: ${paso.nombre}`,
                            mensaje: 'Este paso solo puede consultarse. Los datos registrados se muestran bloqueados para conservar la trazabilidad.',
                        }
                        : null;
                },

                seleccionarSeccion(seccion) {
                    if (this.pasoActivo === 'recepcion') {
                        this.seccionActiva = seccion;
                    }
                    this.avisoPaso = null;
                    if (seccion === 'historial') {
                        this.trazabilidadAbierta = true;
                        this.seguimientoPestana = 'trazabilidad';
                    } else {
                        this.trazabilidadAbierta = false;
                    }
                },

                pasoSiguiente() {
                    const actual = this.pasoPorCodigo(this.pasoActivo);
                    if (!actual) {
                        return null;
                    }
                    const indice = this.pasos.findIndex(paso => paso.codigo === actual.codigo);
                    return indice >= 0 && indice + 1 < this.pasos.length ? this.pasos[indice + 1] : null;
                },

                puedeAvanzarPaso() {
                    const actual = this.pasoPorCodigo(this.pasoActivo);

                    return !!actual
                        && !!this.pasoSiguiente()
                        && actual.estado === 'completado';
                },

                etiquetaSiguientePaso() {
                    const siguiente = this.pasoSiguiente();
                    return siguiente ? `Siguiente paso: ${siguiente.nombre}` : '';
                },

                irASiguientePaso() {
                    if (!this.puedeAvanzarPaso()) {
                        return;
                    }

                    const siguiente = this.pasoSiguiente();
                    if (!siguiente) {
                        return;
                    }
                    this.seleccionarPaso(siguiente);
                    if (siguiente.estado === 'bloqueado') {
                        this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                    }
                },

                etiquetaResultadoEvaluacion(codigo) {
                    const etiquetas = {
                        ACT_ADMISION: 'Admisión',
                        ACT_OBSERVACION: 'Observación',
                        ACT_RECHAZO: 'Rechazo',
                    };
                    return etiquetas[codigo] || codigo || 'Registrado';
                },

                detalleResultadoEvaluacion(resumen) {
                    if (!resumen) {
                        return '';
                    }
                    if (resumen.resultado === 'ACT_ADMISION') {
                        return 'Todos los requisitos de admisibilidad cumplidos.';
                    }
                    if (resumen.resultado === 'ACT_OBSERVACION') {
                        return `${Number(resumen.requisitos_faltantes || 0)} requisito(s) no crítico(s) sin cumplir.`;
                    }
                    if (resumen.resultado === 'ACT_RECHAZO') {
                        return `${(resumen.faltantes_criticos || []).length} requisito(s) crítico(s) ausente(s).`;
                    }
                    return '';
                },

                partesPorTipo(tipo) {
                    return (this.expediente?.partes_vigentes || [])
                        .filter(parte => (parte.tipo || '').toUpperCase() === tipo);
                },

                partesOtras() {
                    return (this.expediente?.partes_vigentes || [])
                        .filter(parte => !['DENUNCIANTE', 'DENUNCIADO'].includes((parte.tipo || '').toUpperCase()));
                },

                actuadosRecientes(limite = 3) {
                    const actuados = this.expediente?.actuados || [];
                    return actuados.slice(-limite).reverse();
                },

                sincronizarPasoActivo({ forzar = false } = {}) {
                    const actual = this.pasoPorCodigo(this.pasoActivo);
                    if (!forzar && actual && actual.estado === 'activo') {
                        return;
                    }
                    const activo = this.pasoActivoServicio();
                    if (activo) {
                        this.pasoActivo = activo.codigo;
                        this.avisoPaso = null;
                        return;
                    }
                    const completados = this.pasos.filter(paso => paso.estado === 'completado');
                    if (completados.length > 0) {
                        this.pasoActivo = completados[completados.length - 1].codigo;
                        this.avisoPaso = null;
                    }
                },

                tieneActuado(codigo) {
                    return (this.expediente?.actuados || []).some(actuado => actuado.tipo_actuado?.codigo === codigo);
                },

                actuadoDe(codigo) {
                    return (this.expediente?.actuados || []).find(actuado => actuado.tipo_actuado?.codigo === codigo) || null;
                },

                tieneInformeFinal() {
                    return (this.expediente?.actuados || []).some(actuado => (actuado.tipo_actuado?.codigo || '').startsWith('ACT_INFORME'));
                },

                actuadosFinales() {
                    return (this.catalogoActuados || []).filter(actuado => (actuado.codigo || '').startsWith('ACT_INFORME'));
                },

                async cargarExpediente() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}`);
                        if (ok) {
                            this.expediente = data.data || data;
                            this.sincronizarPasoActivo({ forzar: true });
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

                async recargarTrasAccion() {
                    await this.cargarExpediente();
                    await this.cargarCatalogoActuados();
                    await this.cargarRequisitos();
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

                async abrirModalActuado(catalogoId = null) {
                    this.modalActuadoOpen = true;
                    this.errorActuado = null;
                    this.formActuado = { catalogo_actuado_id: catalogoId ? String(catalogoId) : '', descripcion: '' };
                    this.archivoSeleccionado = null;
                    this.requiereAdjunto = false;
                    const input = document.getElementById('adjuntoActuado');
                    if (input) input.value = '';
                    await this.cargarCatalogoActuados();
                    if (catalogoId) this.verificarRequisitoAdjunto();
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
                            await this.recargarTrasAccion();
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
                            requisitos: this.requisitos.map(r => ({
                                requisito_id: r.id,
                                cumple: !!r._cumplido,
                            })),
                        };
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/evaluacion`, {
                            method: 'POST',
                            body: payload,
                        });
                        if (ok || status === 201) {
                            this.resultadoEvaluacion = data?.resumen || data?.data || data || { resultado: 'Registrado' };
                            window.apiToast('exito', 'Evaluación de admisibilidad registrada.');
                            await this.recargarTrasAccion();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo registrar la evaluación.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al registrar la evaluación.');
                    } finally {
                        this.submittingEvaluacion = false;
                    }
                },

                async submitPlanificacion() {
                    this.submittingPlanificacion = true;
                    this.errorPlanificacion = null;
                    try {
                        const formData = new FormData();
                        formData.append('descripcion', this.formPlanificacion.descripcion);
                        if (!this.esAc022()) {
                            formData.append('fecha_limite_propuesta', this.formPlanificacion.fecha_limite_propuesta);
                        }
                        if (this.archivoPlanificacion) {
                            formData.append('adjunto', this.archivoPlanificacion);
                        }
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/planificacion`, {
                            method: 'POST',
                            body: formData,
                        });
                        if (ok || status === 201) {
                            window.apiToast('exito', data?.message || 'Planificación registrada correctamente.');
                            this.formPlanificacion = { descripcion: '', fecha_limite_propuesta: '' };
                            this.archivoPlanificacion = null;
                            await this.recargarTrasAccion();
                        } else {
                            this.errorPlanificacion = data?.message || 'No se pudo registrar la planificación.';
                            window.apiToast('error', this.errorPlanificacion, data?.errors);
                        }
                    } catch (e) {
                        this.errorPlanificacion = e.message || 'Error al registrar la planificación.';
                    } finally {
                        this.submittingPlanificacion = false;
                    }
                },

                async submitVistoBuenoPlanificacion() {
                    this.submittingVistoBuenoPlanificacion = true;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/planificacion/visto-bueno`, {
                            method: 'POST',
                            body: { descripcion: this.formVistoBuenoPlanificacion.descripcion },
                        });
                        if (ok) {
                            window.apiToast('exito', data?.message || 'Planificación aprobada.');
                            await this.recargarTrasAccion();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo aprobar la planificación.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al aprobar la planificación.');
                    } finally {
                        this.submittingVistoBuenoPlanificacion = false;
                    }
                },

                async submitDevolverPlanificacion() {
                    this.submittingDevolverPlanificacion = true;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/planificacion/devolver`, {
                            method: 'POST',
                            body: { justificacion: this.formDevolverPlanificacion.justificacion },
                        });
                        if (ok) {
                            window.apiToast('exito', data?.message || 'Planificación devuelta con observaciones.');
                            await this.recargarTrasAccion();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo devolver la planificación.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al devolver la planificación.');
                    } finally {
                        this.submittingDevolverPlanificacion = false;
                    }
                },

                async submitAmpliacion() {
                    this.submittingAmpliacion = true;
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/ampliacion`, {
                            method: 'POST',
                            body: { justificacion: this.formAmpliacion.justificacion },
                        });
                        if (ok || status === 201) {
                            const mensaje = data?.message || 'Solicitud de ampliación enviada. El expediente quedó en la bandeja de la Encargada hasta su aprobación.';
                            this.formAmpliacion = { justificacion: '' };
                            try {
                                sessionStorage.setItem('avisoBandeja', mensaje);
                            } catch (e) {
                                window.apiToast('exito', mensaje);
                            }
                            window.location.href = '/expedientes';
                            return;
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo solicitar la ampliación.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al solicitar la ampliación.');
                    } finally {
                        this.submittingAmpliacion = false;
                    }
                },

                async submitDescargos(tipo) {
                    this.submittingDescargos = true;
                    try {
                        const formData = new FormData();
                        formData.append('descripcion', this.formDescargos[tipo].descripcion);
                        const archivo = tipo === 'comunicar' ? this.archivoComunicacion : this.archivoRecepcion;
                        if (archivo) {
                            formData.append('adjunto', archivo);
                        }
                        const endpoint = tipo === 'comunicar' ? 'descargos/comunicar' : 'descargos/recibir';
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/${endpoint}`, {
                            method: 'POST',
                            body: formData,
                        });
                        if (ok || status === 201) {
                            window.apiToast('exito', data?.message || 'Operación registrada correctamente.');
                            this.formDescargos[tipo].descripcion = '';
                            if (tipo === 'comunicar') {
                                this.archivoComunicacion = null;
                            } else {
                                this.archivoRecepcion = null;
                            }
                            await this.recargarTrasAccion();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo registrar la operación.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al registrar la operación.');
                    } finally {
                        this.submittingDescargos = false;
                    }
                },

                async submitVistoBuenoFinal() {
                    this.submittingVistoBuenoFinal = true;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/cierre/visto-bueno`, {
                            method: 'POST',
                            body: { descripcion: this.formVistoBuenoFinal.descripcion },
                        });
                        if (ok) {
                            window.apiToast('exito', data?.message || 'Visto Bueno Final registrado.');
                            await this.recargarTrasAccion();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo registrar el Visto Bueno Final.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al registrar el Visto Bueno Final.');
                    } finally {
                        this.submittingVistoBuenoFinal = false;
                    }
                },

                async submitReparto() {
                    this.submittingReparto = true;
                    try {
                        const { ok, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/cierre/reparto`, {
                            method: 'POST',
                            body: { ...this.formReparto },
                        });
                        if (ok) {
                            window.apiToast('exito', data?.message || 'Reparto institucional ejecutado.');
                            await this.recargarTrasAccion();
                        } else {
                            window.apiToast('error', data?.message || 'No se pudo ejecutar el reparto.', data?.errors);
                        }
                    } catch (e) {
                        window.apiToast('error', e.message || 'Error al ejecutar el reparto.');
                    } finally {
                        this.submittingReparto = false;
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
