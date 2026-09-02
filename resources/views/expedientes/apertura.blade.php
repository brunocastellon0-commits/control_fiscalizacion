@extends('layouts.app')

@section('titulo', 'Apertura de causa')

@section('contenido')
    <div class="p-6" x-data="aperturaCausa()" x-init="init()">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <h1 class="text-xl font-bold text-grafito">Apertura de causa</h1>
                <p class="text-sm text-gris">Registro de un nuevo expediente con sus partes y documento de respaldo.</p>
            </div>

            {{-- Alerta de error general --}}
            <template x-if="errorGral">
                <div class="bg-[#F15A24]/10 border border-[#F15A24]/30 text-[#B53F12] rounded-xl p-4 text-sm">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    <span x-text="errorGral" @click="errorGral = null" class="cursor-pointer"></span>
                </div>
            </template>

            <form @submit.prevent="submit" class="space-y-6" x-show="!enviando">
                {{-- Datos generales --}}
                <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-6 space-y-5">
                    <h3 class="text-sm font-bold text-grafito uppercase flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-verde-institucional"></i> Datos generales
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-grafito uppercase mb-1.5">Vía de ingreso <span class="text-[#F15A24]">*</span></label>
                            <select x-model="form.via" required class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2.5 bg-white focus:border-verde-institucional focus:ring-verde-institucional">
                                <option value="" disabled>-- Seleccione vía --</option>
                                <option value="TECNICO">Técnico</option>
                                <option value="JURIDICO">Jurídico</option>
                                <option value="FINANCIERO">Financiero</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-grafito uppercase mb-1.5">Reglamento <span class="text-[#F15A24]">*</span></label>
                            <select x-model="form.reglamento_id" required class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2.5 bg-white focus:border-verde-institucional focus:ring-verde-institucional">
                                <option value="" disabled>-- Seleccione reglamento --</option>
                                <template x-for="reg in reglamentos" :key="reg.id">
                                    <option :value="reg.id" x-text="reg.codigo + ' — ' + reg.nombre"></option>
                                </template>
                            </select>
                            <p x-show="reglamentos.length === 0" class="text-xs text-gris mt-1">
                                <template x-if="cargandoReglamentos"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Cargando reglamentos...</template>
                                <template x-if="!cargandoReglamentos && errorReglamentos">No se pudieron cargar los reglamentos.</template>
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-grafito uppercase mb-1.5">Resumen de hechos <span class="text-[#F15A24]">*</span></label>
                        <textarea x-model="form.resumen_hechos" rows="4" required minlength="10" maxlength="5000"
                                  class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2.5 focus:border-verde-institucional focus:ring-verde-institucional"
                                  placeholder="Describa brevemente los hechos denunciados o la materia del expediente."></textarea>
                        <p class="text-xs text-gris mt-1 text-right" x-text="(form.resumen_hechos || '').length + ' / 5000'"></p>
                    </div>
                </div>

                {{-- Partes procesales --}}
                <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-grafito uppercase flex items-center gap-2">
                            <i class="fa-solid fa-users text-verde-institucional"></i> Partes procesales
                            <span class="text-xs font-normal normal-case text-gris" x-text="'(' + partes.length + ')'"></span>
                        </h3>
                        <button type="button" @click="agregarParte"
                                x-show="partes.length < 10"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-verde-profundo hover:text-verde-institucional transition">
                            <i class="fa-solid fa-plus"></i> Agregar parte
                        </button>
                    </div>

                    <template x-if="partes.length === 0">
                        <p class="text-sm text-gris text-center py-6">
                            <i class="fa-solid fa-user-plus mr-2"></i>Agregue al menos una parte procesal.
                        </p>
                    </template>

                    <div class="space-y-3">
                        <template x-for="(parte, idx) in partes" :key="idx">
                            <div class="border border-gris-claro rounded-lg p-4 bg-gris-claro/40">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-bold text-grafito uppercase" x-text="'Parte ' + (idx + 1)"></span>
                                    <button type="button" @click="quitarParte(idx)"
                                            class="text-xs text-[#B53F12] hover:underline">
                                        <i class="fa-solid fa-trash-can mr-1"></i>Quitar
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-grafito uppercase mb-1">Tipo <span class="text-[#F15A24]">*</span></label>
                                        <select x-model="parte.tipo" required class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2 bg-white focus:border-verde-institucional focus:ring-verde-institucional">
                                            <option value="DENUNCIANTE">Denunciante</option>
                                            <option value="DENUNCIADO">Denunciado</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-grafito uppercase mb-1">Documento (CI/NIT)</label>
                                        <input type="text" x-model="parte.documento_identidad" maxlength="30"
                                               class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-grafito uppercase mb-1">Nombre completo <span class="text-[#F15A24]">*</span></label>
                                        <input type="text" x-model="parte.nombre_completo" required maxlength="200"
                                               class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-grafito uppercase mb-1">Cargo / Institución</label>
                                        <input type="text" x-model="parte.cargo_institucion" maxlength="150"
                                               class="w-full border border-gris-claro rounded-lg text-sm text-grafito px-3 py-2 focus:border-verde-institucional focus:ring-verde-institucional">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Documento de respaldo --}}
                <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-6 space-y-3">
                    <h3 class="text-sm font-bold text-grafito uppercase flex items-center gap-2">
                        <i class="fa-solid fa-paperclip text-verde-institucional"></i> Documento de respaldo
                    </h3>
                    <label class="block text-xs font-semibold text-grafito uppercase mb-1.5">Adjunto (PDF) <span class="text-[#F15A24]">*</span></label>
                    <input type="file" accept="application/pdf" @change="manejarAdjunto" required
                           class="w-full text-sm text-gris file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gris-claro file:text-grafito hover:file:bg-[#F15A24]/10">
                    <div x-show="form.adjunto" class="flex items-center gap-2 text-xs text-grafito bg-gris-claro/50 rounded-lg px-3 py-2">
                        <i class="fa-solid fa-file-pdf text-[#F15A24]"></i>
                        <span x-text="form.adjunto.name" class="truncate flex-1"></span>
                        <span x-text="formatBytes(form.adjunto.size)" class="text-gris shrink-0"></span>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="flex justify-end gap-3">
                    <a href="/expedientes" class="px-5 py-2.5 bg-gris-claro text-grafito rounded-lg text-sm font-medium hover:bg-[#F15A24]/10 transition">Cancelar</a>
                    <button type="submit"
                            class="px-6 py-2.5 bg-verde-profundo text-white rounded-lg text-sm font-semibold hover:bg-verde-institucional transition">
                        <i class="fa-solid fa-folder-plus mr-2"></i> Registrar expediente
                    </button>
                </div>
            </form>

            {{-- Overlay de envío --}}
            <div x-show="enviando" class="flex flex-col items-center justify-center py-20 text-center">
                <i class="fa-solid fa-circle-notch fa-spin text-4xl text-verde-institucional mb-4"></i>
                <p class="text-sm text-grafito font-medium">Registrando expediente...</p>
                <p class="text-xs text-gris">Se generará el NUREJ y se guardará la documentación en la cadena de custodia.</p>
            </div>
        </div>
    </div>

    <script>
        function aperturaCausa() {
            return {
                form: {
                    via: '',
                    reglamento_id: '',
                    resumen_hechos: '',
                    adjunto: null,
                },
                partes: [],
                reglamentos: [],
                cargandoReglamentos: false,
                errorReglamentos: false,
                errorGral: null,
                enviando: false,

                init() {
                    this.agregarParte();
                    this.cargarReglamentos();
                },

                async cargarReglamentos() {
                    this.cargandoReglamentos = true;
                    this.errorReglamentos = false;
                    try {
                        const { ok, data } = await window.apiFetch('/api/reglamentos');
                        if (ok) {
                            this.reglamentos = data.data || [];
                        } else {
                            this.errorReglamentos = true;
                            window.apiToast('error', 'No se pudieron cargar los reglamentos.');
                        }
                    } catch (e) {
                        console.error(e);
                        this.errorReglamentos = true;
                    } finally {
                        this.cargandoReglamentos = false;
                    }
                },

                agregarParte() {
                    if (this.partes.length >= 10) {
                        window.apiToast('error', 'Máximo 10 partes procesales por expediente.');
                        return;
                    }
                    this.partes.push({
                        tipo: 'DENUNCIANTE',
                        nombre_completo: '',
                        documento_identidad: '',
                        cargo_institucion: '',
                    });
                },

                quitarParte(idx) {
                    if (this.partes.length > 1) {
                        this.partes.splice(idx, 1);
                        return;
                    }
                    window.apiToast('error', 'El expediente requiere al menos una parte.');
                },

                manejarAdjunto(event) {
                    const archivo = event.target.files[0] || null;
                    this.form.adjunto = archivo;
                },

                submit() {
                    this.errorGral = null;

                    if (this.partes.length === 0) {
                        window.apiToast('error', 'Agregue al menos una parte procesal.');
                        return;
                    }
                    for (const [idx, parte] of this.partes.entries()) {
                        if (!parte.nombre_completo.trim()) {
                            window.apiToast('error', `La parte ${idx + 1} requiere nombre completo.`);
                            return;
                        }
                    }
                    if (!this.form.adjunto) {
                        window.apiToast('error', 'El documento de respaldo (PDF) es obligatorio.');
                        return;
                    }

                    this.enviando = true;

                    const formData = new FormData();
                    formData.append('via', this.form.via);
                    formData.append('reglamento_id', this.form.reglamento_id);
                    formData.append('resumen_hechos', this.form.resumen_hechos);
                    formData.append('adjunto', this.form.adjunto);

                    for (const [idx, parte] of this.partes.entries()) {
                        formData.append(`partes[${idx}][tipo]`, parte.tipo);
                        formData.append(`partes[${idx}][nombre_completo]`, parte.nombre_completo);
                        if (parte.documento_identidad) formData.append(`partes[${idx}][documento_identidad]`, parte.documento_identidad);
                        if (parte.cargo_institucion) formData.append(`partes[${idx}][cargo_institucion]`, parte.cargo_institucion);
                    }

                    window.apiFetch('/api/expedientes', {
                        method: 'POST',
                        body: formData,
                    }).then(({ ok, status, data }) => {
                        if (ok || status === 201) {
                            window.apiToast('exito', 'Expediente registrado correctamente.');
                            const id = data.data?.id;
                            setTimeout(() => {
                                window.location.href = id ? `/expedientes/${id}` : '/expedientes';
                            }, 900);
                        } else if (status === 403) {
                            window.apiToast('error', data.message || 'No tiene permisos para registrar expedientes.');
                        } else if (status === 422) {
                            window.apiToast('error', (data.message || 'Revise los campos marcados.'), data.errors);
                        } else {
                            window.apiToast('error', data.message || 'No se pudo registrar el expediente.');
                        }
                    }).catch((e) => {
                        console.error(e);
                        window.apiToast('error', e.message || 'Error inesperado al registrar.');
                    }).finally(() => {
                        this.enviando = false;
                    });
                },

                formatBytes(bytes) {
                    if (!bytes && bytes !== 0) return '';
                    const unidades = ['B', 'KB', 'MB', 'GB'];
                    let i = 0;
                    let size = bytes;
                    while (size >= 1024 && i < unidades.length - 1) {
                        size /= 1024;
                        i++;
                    }
                    return size.toFixed(size >= 10 || i === 0 ? 0 : 1) + ' ' + unidades[i];
                },
            };
        }
    </script>
@endsection