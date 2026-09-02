@extends('layouts.app')

@section('titulo', 'Detalle de Expediente')

@section('contenido')
    <div x-data="detalleExpediente({{ $expedienteId }})" x-init="init()" class="p-6" x-cloak>

        {{-- Spinner Global --}}
        <div x-show="loading" class="flex justify-center py-20">
            <i class="fa-solid fa-circle-notch fa-spin text-4xl text-verde-institucional"></i>
        </div>

        <template x-if="!loading && error">
            <div class="bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] rounded-xl p-5 text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                <span x-text="error"></span>
            </div>
        </template>

        <template x-if="!loading && !error && expediente">
            <div class="space-y-6">
                {{-- ENCABEZADO Y SEMÁFORO --}}
                <div class="bg-white p-6 rounded-xl border border-gris-claro shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-2xl font-mono font-bold text-grafito" x-text="expediente.nurej_code"></h1>
                            <span class="px-3 py-1 text-xs font-bold rounded bg-gris-claro text-gris border border-gris-claro"
                                  x-text="expediente.estado_actual?.nombre"></span>
                        </div>
                        <p class="text-sm text-gris">
                            Vía: <span class="font-medium text-grafito" x-text="expediente.via"></span>
                            | Ingreso: <span x-text="formatDate(expediente.fecha_ingreso)"></span>
                        </p>
                        <template x-if="expediente.reglamento">
                            <p class="text-xs text-gris mt-1">
                                Reglamento: <span class="font-semibold text-grafito" x-text="expediente.reglamento.codigo + ' · ' + expediente.reglamento.nombre"></span>
                            </p>
                        </template>
                        <template x-if="expediente.asignacion_activa?.usuario">
                            <p class="text-xs text-gris mt-1">
                                Asignado a: <span class="font-semibold text-grafito" x-text="expediente.asignacion_activa.usuario.nombres + ' ' + expediente.asignacion_activa.usuario.apellidos"></span>
                                <span class="ml-1 px-2 py-0.5 rounded bg-gris-claro text-gris" x-text="expediente.asignacion_activa.rol?.nombre"></span>
                            </p>
                        </template>
                    </div>

                    {{-- Semáforo Principal --}}
                    <template x-if="expediente.sem_plazo">
                        <div class="flex items-center gap-3 px-4 py-3 rounded-lg border shadow-sm" :class="getSemaforoClass(expediente.sem_plazo.codigo_color)">
                            <span class="relative inline-flex rounded-full h-3.5 w-3.5" :class="getSemaforoDotClass(expediente.sem_plazo.codigo_color)"></span>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold uppercase tracking-wide" x-text="semEtiqueta(expediente.sem_plazo.codigo_color)"></span>
                                <span class="text-xs font-medium" x-show="expediente.sem_plazo.dias_restantes !== null">
                                    <span x-text="expediente.sem_plazo.dias_restantes"></span> días hábiles restantes
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- COLUMNA IZQUIERDA: Resumen y Partes --}}
                    <div class="space-y-6 lg:col-span-1">
                        {{-- Tarjeta de Resumen --}}
                        <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                            <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                                <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-list text-verde-institucional mr-2"></i> Hechos</h3>
                            </div>
                            <div class="p-4 text-sm text-gris leading-relaxed whitespace-pre-line" x-text="expediente.resumen_hechos"></div>
                        </div>

                        {{-- Tarjeta de Partes --}}
                        <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                            <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                                <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-users text-verde-institucional mr-2"></i> Partes Procesales</h3>
                            </div>
                            <ul class="divide-y divide-gris-claro">
                                <template x-if="expediente.partes_vigentes && expediente.partes_vigentes.length > 0">
                                    <template x-for="parte in expediente.partes_vigentes" :key="parte.id">
                                        <li class="p-4 flex flex-col gap-1">
                                            <span class="text-[10px] font-bold text-verde-institucional uppercase tracking-wider" x-text="parte.tipo"></span>
                                            <span class="text-sm font-semibold text-grafito" x-text="parte.nombre_completo"></span>
                                            <span class="text-xs text-gris" x-text="parte.documento_identidad || 'Sin CI/NIT'"></span>
                                        </li>
                                    </template>
                                </template>
                                <template x-if="!expediente.partes_vigentes || expediente.partes_vigentes.length === 0">
                                    <li class="p-4 text-sm text-gris">Sin partes registradas.</li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    {{-- COLUMNA DERECHA: Cadena de Custodia (Actuados) --}}
                    <div class="lg:col-span-2 space-y-4">
                        {{-- Tarjeta de Plazos --}}
                        <template x-if="expediente.plazos && expediente.plazos.length > 0">
                            <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">
                                <div class="bg-gris-claro px-4 py-3 border-b border-gris-claro">
                                    <h3 class="text-sm font-bold text-grafito uppercase"><i class="fa-solid fa-hourglass-half text-verde-institucional mr-2"></i> Plazos</h3>
                                </div>
                                <ul class="divide-y divide-gris-claro">
                                    <template x-for="plazo in plazosOrdenados()" :key="plazo.id">
                                        <li class="p-4 flex flex-col md:flex-row md:items-center gap-2">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-xs font-bold text-grafito uppercase" x-text="plazo.tipo_plazo"></span>
                                                    <span class="text-[10px] px-2 py-0.5 rounded bg-gris-claro text-gris uppercase" x-text="plazo.estado"></span>
                                                </div>
                                                <p class="text-xs text-gris mt-1">
                                                    <span x-text="formatDate(plazo.fecha_inicio)"></span> → <span x-text="formatDate(plazo.fecha_limite)"></span>
                                                    · <span x-text="plazo.dias_habiles_otorgados"></span> días hábiles
                                                </p>
                                            </div>
                                            <template x-if="plazo.sem_plazo">
                                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded text-xs font-bold border shrink-0"
                                                      :class="getSemaforoClass(plazo.sem_plazo.codigo_color)">
                                                    <span class="rounded-full h-2.5 w-2.5" :class="getSemaforoDotClass(plazo.sem_plazo.codigo_color)"></span>
                                                    <span x-text="semEtiqueta(plazo.sem_plazo.codigo_color)"></span>
                                                    <span x-show="plazo.sem_plazo.dias_restantes !== null && !plazo.sem_plazo.es_fuera_de_plazo">
                                                        · <span x-text="plazo.sem_plazo.dias_restantes"></span> días
                                                    </span>
                                                </span>
                                            </template>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-grafito"><i class="fa-solid fa-timeline text-verde-institucional mr-2"></i> Cadena de Custodia</h3>
                            <button x-show="catalogoCargado && catalogoActuados.length > 0" @click="abrirModalActuado()"
                                    class="inline-flex items-center px-4 py-2 bg-verde-profundo hover:bg-verde-institucional text-white text-sm font-semibold rounded-lg shadow-sm transition">
                                <i class="fa-solid fa-plus mr-2"></i> Emitir Actuado
                            </button>
                        </div>

                        {{-- Timeline de Actuados --}}
                        <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-6 relative">
                            <div class="absolute left-[23px] top-8 bottom-8 w-0.5 bg-gris-claro"></div>

                            <template x-if="expediente.actuados && expediente.actuados.length > 0">
                                <div class="space-y-8 relative">
                                    <template x-for="actuado in expediente.actuados" :key="actuado.id">
                                        <div class="flex gap-4 items-start group">
                                            <div class="flex-shrink-0 w-5 h-5 rounded-full bg-verde-profundo border-2 border-verde-institucional relative z-10 mt-1"></div>
                                            <div class="flex-1 bg-gris-claro rounded-lg p-4 border border-gris-claro hover:border-verde-institucional transition-colors">
                                                <div class="flex justify-between items-start mb-2 gap-3">
                                                    <span class="text-sm font-bold text-grafito" x-text="actuado.tipo_actuado?.nombre"></span>
                                                    <span class="text-xs text-gris shrink-0" x-text="formatDateTime(actuado.fecha_hora)"></span>
                                                </div>

                                                <template x-if="actuado.estado_nuevo">
                                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-gris-claro text-gris"
                                                              x-text="actuado.estado_anterior?.nombre ?? 'INGRESO'"></span>
                                                        <i class="fa-solid fa-arrow-right text-[10px] text-gris"></i>
                                                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-verde-profundo/10 text-verde-profundo border border-verde-institucional/30"
                                                              x-text="actuado.estado_nuevo?.nombre"></span>
                                                    </div>
                                                </template>

                                                <p class="text-sm text-gris mb-3 whitespace-pre-line" x-text="actuado.descripcion"></p>

                                                {{-- Visualización de Hashes (Seguridad) --}}
                                                <div class="bg-grafito rounded p-2.5 mt-3 text-[10px] font-mono text-gris-claro break-all leading-tight"
                                                     x-show="hashVisible === actuado.id">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-xs text-white/80">Cadena de custodia (SHA-256)</span>
                                                        <button @click="hashVisible = null" class="text-white/60 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
                                                    </div>
                                                    <div><span class="text-verde-institucional">HASH_ANTERIOR:</span> <span x-text="actuado.hash_anterior || '-'"></span></div>
                                                    <div class="mt-1"><span class="text-verde-institucional">HASH_ACTUADO:</span> <span x-text="actuado.hash_actuado"></span></div>
                                                </div>
                                                <button x-show="hashVisible !== actuado.id" @click="hashVisible = actuado.id"
                                                        class="mt-2 text-xs text-verde-institucional hover:underline">
                                                    <i class="fa-solid fa-lock mr-1"></i>Ver cadena de custodia
                                                </button>

                                                {{-- Adjuntos --}}
                                                <template x-if="actuado.adjuntos && actuado.adjuntos.length > 0">
                                                    <div class="mt-3 pt-3 border-t border-gris-claro flex gap-2">
                                                        <template x-for="adj in actuado.adjuntos" :key="adj.id">
                                                            <a :href="`/api/adjuntos/${adj.id}/descargar`" target="_blank"
                                                               class="inline-flex items-center px-3 py-1.5 bg-white border border-gris-claro text-xs font-medium text-grafito rounded hover:bg-[#F15A24]/10 hover:text-[#B53F12] hover:border-[#F15A24]/30 transition">
                                                                <i class="fa-solid fa-file-pdf mr-1.5 text-[#F15A24]"></i> Ver PDF
                                                            </a>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!expediente.actuados || expediente.actuados.length === 0">
                                <p class="text-sm text-gris text-center py-8">Aún no hay actuados registrados en este expediente.</p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Modal Emitir Actuado --}}
    <div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-grafito/60 p-4" x-cloak>
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-gris-claro" @click.away="modalOpen = false">
            <div class="bg-verde-profundo text-white px-6 py-4 flex justify-between items-center">
                <h3 class="font-bold text-base"><i class="fa-solid fa-file-signature text-verde-institucional mr-2"></i> Emitir Nuevo Actuado</h3>
                <button @click="modalOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form @submit.prevent="submitActuado" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1">Tipo de Actuado <span class="text-[#F15A24]">*</span></label>
                    <select x-model="form.catalogo_actuado_id" required @change="verificarRequisitoAdjunto"
                            class="w-full border-gris-claro rounded-lg text-sm focus:border-verde-institucional focus:ring-verde-institucional text-grafito">
                        <option value="">-- Seleccione Acción --</option>
                        <template x-for="cat in catalogoActuados" :key="cat.id">
                            <option :value="cat.id" x-text="cat.nombre"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1">Descripción / Proveído <span class="text-[#F15A24]">*</span></label>
                    <textarea x-model="form.descripcion" required rows="3"
                              class="w-full border-gris-claro rounded-lg text-sm focus:border-verde-institucional focus:ring-verde-institucional text-grafito"></textarea>
                </div>

                <div x-show="requiereAdjunto">
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1">Documento Respaldo (PDF) <span class="text-[#F15A24]">*</span></label>
                    <input type="file" id="adjuntoActuado" accept="application/pdf" @change="manejarArchivo"
                           :required="requiereAdjunto"
                           class="w-full text-sm text-gris file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gris-claro file:text-grafito hover:file:bg-[#F15A24]/10">
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gris-claro mt-6">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2 bg-gris-claro text-grafito rounded-lg text-sm font-medium hover:bg-[#F15A24]/10">Cancelar</button>
                    <button type="submit" :disabled="submitting" class="px-6 py-2 bg-verde-profundo text-white rounded-lg text-sm font-semibold hover:bg-verde-institucional disabled:opacity-50">
                        <i class="fa-solid fa-save mr-2" :class="{'fa-spin fa-spinner': submitting}"></i> Guardar Actuado
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function detalleExpediente(id) {
            return {
                expedienteId: id,
                expediente: null,
                loading: true,
                error: null,
                modalOpen: false,
                submitting: false,
                catalogoActuados: [],
                catalogoCargado: false,
                requiereAdjunto: false,
                archivoSeleccionado: null,
                hashVisible: null,

                form: {
                    catalogo_actuado_id: '',
                    descripcion: ''
                },

                async init() {
                    await this.cargarExpediente();
                    await this.cargarCatalogo();
                },

                async cargarExpediente() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}`);
                        if (ok) {
                            this.expediente = data.data;
                        } else if (status === 403) {
                            window.apiToast('error', 'No tiene permisos para ver este expediente.');
                            setTimeout(() => window.location.href = '/expedientes', 2000);
                        } else {
                            this.error = 'No se pudo cargar el expediente.';
                        }
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async cargarCatalogo() {
                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/catalogo/actuados`);
                        if (ok) {
                            this.catalogoActuados = data.data;
                        } else if (status === 403) {
                            window.apiToast('error', 'No tiene permisos para emitir actuados.');
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.catalogoCargado = true;
                    }
                },

                abrirModalActuado() {
                    this.form.catalogo_actuado_id = '';
                    this.form.descripcion = '';
                    this.archivoSeleccionado = null;
                    this.requiereAdjunto = false;
                    const input = document.getElementById('adjuntoActuado');
                    if (input) input.value = '';
                    this.modalOpen = true;
                },

                verificarRequisitoAdjunto() {
                    const seleccionado = this.catalogoActuados.find(c => Number(c.id) === Number(this.form.catalogo_actuado_id));
                    this.requiereAdjunto = seleccionado ? seleccionado.requiere_adjunto : false;
                },

                manejarArchivo(event) {
                    this.archivoSeleccionado = event.target.files[0];
                },

                async submitActuado() {
                    this.submitting = true;
                    const formData = new FormData();
                    formData.append('catalogo_actuado_id', this.form.catalogo_actuado_id);
                    formData.append('descripcion', this.form.descripcion);

                    if (this.requiereAdjunto && this.archivoSeleccionado) {
                        formData.append('adjunto', this.archivoSeleccionado);
                    }

                    try {
                        const { ok, status, data } = await window.apiFetch(`/api/expedientes/${this.expedienteId}/actuados`, {
                            method: 'POST',
                            body: formData,
                        });

                        if (ok || status === 201) {
                            window.apiToast('exito', 'Actuado emitido y registrado en cadena de custodia.');
                            this.modalOpen = false;
                            await this.cargarExpediente();
                            await this.cargarCatalogo();
                        } else if (status === 403) {
                            window.apiToast('error', data.message || 'No tiene permisos para emitir este actuado.');
                        } else if (status === 422) {
                            window.apiToast('error', (data.message || 'Error de validación.'), data.errors);
                        } else {
                            window.apiToast('error', data.message || 'Error al emitir el actuado.');
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.submitting = false;
                    }
                },

                plazosOrdenados() {
                    return [...(this.expediente?.plazos || [])].sort((a, b) => new Date(a.fecha_limite) - new Date(b.fecha_limite));
                },

                semEtiqueta(color) {
                    const map = {
                        VERDE: 'En plazo',
                        AMARILLO: 'Próximo a vencer',
                        ROJO: 'Vence pronto',
                        FUERA_DE_PLAZO: 'Fuera de plazo'
                    };
                    return map[color] || color;
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
                    const map = {
                        VERDE: 'bg-[#8CC63F]',
                        AMARILLO: 'bg-amber-500',
                        ROJO: 'bg-[#F15A24]',
                        FUERA_DE_PLAZO: 'bg-grafito'
                    };
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
