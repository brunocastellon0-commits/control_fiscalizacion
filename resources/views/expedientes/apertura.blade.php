@extends('layouts.app')

@php
    use App\Models\Expediente;
@endphp

@section('titulo', 'Apertura de causa')

@section('contenido')
    <div class="min-h-screen bg-[#F6F8F6] p-4 sm:p-4 lg:p-5" x-data="aperturaCausa()" x-init="init()">
        <div class="mx-auto max-w-[1480px]">
            <header class="mb-4">
                <h1 class="text-2xl font-bold tracking-tight text-grafito sm:text-[28px]">Apertura de causa</h1>
                <p class="mt-1 text-sm text-gris">
                    Complete la información del expediente, las partes procesales y el documento de respaldo.
                </p>
            </header>

            <form @submit.prevent="submit" class="grid grid-cols-1 gap-4 xl:grid-cols-12" x-show="!enviando">
                {{-- Datos generales --}}
                <section class="flex h-full flex-col overflow-hidden rounded-2xl border border-[#E3E8E4] bg-white shadow-sm xl:col-span-8">
                    <div class="sidebar-gradient flex items-start border-b border-white/10 px-6 py-3">
                        <div>
                            <h2 class="flex items-center gap-2 text-base font-bold text-white">
                                <i class="fa-solid fa-folder-open text-white/85"></i> Datos generales
                            </h2>
                        </div>
                    </div>

                    <div class="flex-1 space-y-3 px-6 py-3">
                        <div>
                            <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.16em] text-verde-profundo">
                                Clasificación
                            </p>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                                <div class="min-w-0">
                                    <label for="via" class="block text-xs font-semibold text-grafito">
                                        Origen de ingreso <span class="text-[#B53F12]">*</span>
                                    </label>
                                    <select id="via" x-model="form.via" required
                                            @change="if (form.via !== viaDistrital) form.numero_hoja_ruta = ''"
                                            class="mt-1.5 w-full min-w-0 rounded-lg border border-[#DDE5DF] bg-white px-3.5 py-2.5 text-sm text-grafito outline-none transition focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                        <option value="" disabled>-- Seleccione el origen --</option>
                                        <option value="{{ Expediente::VIA_INGRESO_VERBAL }}">Ingreso verbal</option>
                                        <option value="{{ Expediente::VIA_INGRESO_DISTRITAL }}">Ingreso desde distrital</option>
                                    </select>
                                </div>

                                <div class="min-w-0">
                                    <label for="naturaleza" class="block text-xs font-semibold text-grafito">
                                        Naturaleza <span class="text-[#B53F12]">*</span>
                                    </label>
                                    <select id="naturaleza" x-model="form.naturaleza" required
                                            class="mt-1.5 w-full min-w-0 rounded-lg border border-[#DDE5DF] bg-white px-3.5 py-2.5 text-sm text-grafito outline-none transition focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                        <option value="" disabled>-- Seleccione naturaleza --</option>
                                        <option value="JURISDICCIONAL">Jurisdiccional</option>
                                        <option value="ADMINISTRATIVA">Administrativa</option>
                                    </select>
                                </div>

                                <div class="min-w-0">
                                    <label for="reglamento_id" class="block text-xs font-semibold text-grafito">
                                        Reglamento <span class="text-[#B53F12]">*</span>
                                    </label>
                                    <select id="reglamento_id" x-model="form.reglamento_id" required
                                            class="mt-1.5 w-full min-w-0 rounded-lg border border-[#DDE5DF] bg-white px-3.5 py-2.5 text-sm text-grafito outline-none transition focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                        <option value="" disabled>-- Seleccione reglamento --</option>
                                        <template x-for="reg in reglamentos" :key="reg.id">
                                            <option :value="reg.id" x-text="reg.codigo + ' — ' + reg.nombre"></option>
                                        </template>
                                    </select>
                                    <p x-show="reglamentos.length === 0" class="mt-1.5 text-xs text-gris">
                                        <template x-if="cargandoReglamentos"><span><i class="fa-solid fa-spinner fa-spin mr-1"></i>Cargando reglamentos...</span></template>
                                        <template x-if="!cargandoReglamentos && errorReglamentos">No se pudieron cargar los reglamentos.</template>
                                    </p>
                                </div>

                                <div x-show="form.via === viaDistrital" x-transition class="md:col-span-3">
                                    <div class="rounded-xl border border-[#F2C9C9] bg-[#FFF7F7] p-2.5">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                            <div class="min-w-0">
                                                <label for="numero_hoja_ruta" class="block text-xs font-semibold text-grafito">
                                                    Número de hoja de ruta <span class="text-[#B53F12]">*</span>
                                                </label>
                                                <input id="numero_hoja_ruta" type="text" x-model="form.numero_hoja_ruta" maxlength="50"
                                                       :required="form.via === viaDistrital"
                                                       placeholder="Ej. HR-2026-000123"
                                                       :class="form.numero_hoja_ruta.trim().length > 0
                                                           ? 'border-[#BFE3CB] bg-[#F4FBF6] focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15'
                                                           : 'border-[#F2C9C9] bg-white focus:border-[#991B1B] focus:ring-2 focus:ring-[#991B1B]/15'"
                                                       class="mt-1.5 w-full min-w-0 rounded-lg border px-3.5 py-2 text-sm text-grafito outline-none transition placeholder:text-[#9AA7A1]">
                                            </div>
                                            <p class="text-xs text-gris sm:pb-2.5">Obligatorio cuando el ingreso proviene de la distrital.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-[#EDF1ED] pt-5">
                            <div class="mb-1.5 flex items-end justify-between gap-3">
                                <label for="resumen_hechos" class="block text-xs font-semibold text-grafito">
                                    Resumen de hechos <span class="text-[#B53F12]">*</span>
                                </label>
                                <span class="text-[11px] text-gris" x-text="(form.resumen_hechos || '').length + ' / 5000'"></span>
                            </div>
                            <textarea id="resumen_hechos" x-model="form.resumen_hechos" rows="3" required minlength="10" maxlength="5000"
                                      class="min-h-[72px] w-full resize-y rounded-xl border border-[#DDE5DF] bg-white px-3.5 py-2 text-sm leading-snug text-grafito outline-none transition placeholder:text-[#9AA7A1] focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15"
                                      placeholder="Describa brevemente los hechos denunciados o la materia del expediente."></textarea>
                        </div>
                    </div>
                </section>

                {{-- Documento de respaldo --}}
                <aside class="flex h-full flex-col overflow-hidden rounded-2xl border border-[#E3E8E4] bg-white shadow-sm xl:col-span-4">
                    <div class="sidebar-gradient flex items-start border-b border-white/10 px-6 py-3">
                        <div>
                            <h2 class="flex items-center gap-2 text-base font-bold text-white">
                                <i class="fa-solid fa-paperclip text-white/85"></i> Documento de respaldo
                            </h2>
                        </div>
                    </div>

                    <div class="flex-1 px-6 py-3">
                        <label for="adjunto_pdf"
                               class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#CFDCD3] bg-[#F7FAF8] px-5 py-4 text-center outline-none transition hover:border-[#1E7A52] hover:bg-[#F1F7F3] focus-within:ring-2 focus-within:ring-[#1E7A52]/20">
                            <span class="mb-1.5 flex h-8 w-8 items-center justify-center rounded-full bg-[#DCEBDD] text-verde-profundo transition group-hover:bg-white">
                                <i class="fa-solid fa-file-pdf text-lg"></i>
                            </span>
                            <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris">
                                Adjunto (PDF) <span class="text-[#B53F12]">*</span>
                            </span>
                            <span class="mt-1.5 text-sm font-semibold text-grafito">Seleccionar documento PDF</span>
                            <span class="mt-1 text-xs text-gris">Haga clic para explorar sus archivos</span>
                            <input id="adjunto_pdf" type="file" accept="application/pdf" @change="manejarAdjunto" required
                                   class="sr-only">
                        </label>

                        <div x-show="form.adjunto" x-transition
                             class="mt-2 flex items-center gap-3 rounded-xl border border-[#DCEBDD] bg-[#F4F9F5] p-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#B53F12]">
                                <i class="fa-solid fa-file-pdf"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p x-text="form.adjunto.name" class="truncate text-sm font-medium text-grafito"></p>
                                <p x-text="formatBytes(form.adjunto.size)" class="text-xs text-gris"></p>
                            </div>
                            <span class="rounded-md bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-verde-profundo">
                                PDF
                            </span>
                        </div>

                        <div class="mt-2 flex items-start gap-2 rounded-lg bg-[#F7F9F7] p-2 text-xs text-gris">
                            <i class="fa-solid fa-circle-info mt-0.5 text-verde-institucional"></i>
                            <p>Formato PDF. Tamaño máximo 20 MB.</p>
                        </div>
                    </div>
                </aside>

                {{-- Partes procesales --}}
                <section class="overflow-hidden rounded-2xl border border-[#E3E8E4] bg-white shadow-sm xl:col-span-12">
                    <div class="sidebar-gradient flex flex-col gap-4 border-b border-white/10 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="flex items-center gap-2 text-base font-bold text-white">
                                <i class="fa-solid fa-users text-white/85"></i> Partes procesales
                            </h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold text-white"
                                  x-text="'(' + partes.length + ')'"></span>
                            <button type="button" @click="agregarParte"
                                    x-show="partes.length < 10"
                                    class="inline-flex items-center gap-2 rounded-lg bg-white px-3.5 py-2 text-xs font-semibold text-verde-profundo transition hover:bg-white/90">
                                <i class="fa-solid fa-plus text-[11px]"></i> Agregar parte
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4">
                        <template x-if="partes.length === 0">
                            <div class="rounded-xl border border-dashed border-[#CFDCD3] bg-[#F7FAF8] py-10 text-center">
                                <p class="text-sm font-medium text-grafito">Agregue al menos una parte procesal.</p>
                                <p class="mt-1 text-xs text-gris">Use el botón “Agregar parte” para comenzar.</p>
                            </div>
                        </template>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-2">
                            <template x-for="(parte, idx) in partes" :key="idx">
                                <article :class="parte.tipo === 'DENUNCIANTE' ? 'border-[#BFE3CB] bg-[#F4FBF6]' : 'border-[#F2C9C9] bg-[#FFF7F7]'"
                                         class="rounded-xl border p-4 shadow-sm">
                                    <div class="mb-4 flex items-start justify-between gap-3">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span :class="parte.tipo === 'DENUNCIANTE' ? 'bg-[#DCEBDD] text-verde-profundo' : 'bg-[#FDE8E8] text-[#991B1B]'"
                                                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                                  x-text="String(idx + 1).padStart(2, '0')"></span>
                                            <div class="min-w-0">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gris"
                                                   x-text="'Parte ' + (idx + 1)"></p>
                                                <p class="truncate text-sm font-bold"
                                                   :class="parte.tipo === 'DENUNCIANTE' ? 'text-verde-profundo' : 'text-[#991B1B]'"
                                                   x-text="parte.tipo === 'DENUNCIANTE' ? 'Denunciante' : 'Denunciado'"></p>
                                            </div>
                                        </div>
                                        <button type="button" @click="quitarParte(idx)" title="Quitar parte"
                                                class="rounded-lg p-1.5 text-[#B53F12] transition hover:bg-[#FDE8E8]">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                            <span class="sr-only">Quitar parte</span>
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div class="min-w-0">
                                            <label :for="'parte-tipo-' + idx" class="block text-[11px] font-semibold text-grafito">
                                                Tipo <span class="text-[#B53F12]">*</span>
                                            </label>
                                            <select :id="'parte-tipo-' + idx" x-model="parte.tipo" required
                                                    class="mt-1 w-full min-w-0 rounded-lg border border-white/80 bg-white/90 px-3 py-2 text-sm text-grafito outline-none transition focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                                <option value="DENUNCIANTE">Denunciante</option>
                                                <option value="DENUNCIADO">Denunciado</option>
                                            </select>
                                        </div>
                                        <div class="min-w-0">
                                            <label :for="'parte-documento-' + idx" class="block text-[11px] font-semibold text-grafito">
                                                Documento (CI/NIT)
                                            </label>
                                            <input :id="'parte-documento-' + idx" type="text" x-model="parte.documento_identidad" maxlength="30"
                                                   class="mt-1 w-full min-w-0 rounded-lg border border-white/80 bg-white/90 px-3 py-2 text-sm text-grafito outline-none transition placeholder:text-[#9AA7A1] focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                        </div>
                                        <div class="min-w-0 sm:col-span-2">
                                            <label :for="'parte-nombre-' + idx" class="block text-[11px] font-semibold text-grafito">
                                                Nombre completo <span class="text-[#B53F12]">*</span>
                                            </label>
                                            <input :id="'parte-nombre-' + idx" type="text" x-model="parte.nombre_completo" required maxlength="200"
                                                   class="mt-1 w-full min-w-0 rounded-lg border border-white/80 bg-white/90 px-3 py-2 text-sm text-grafito outline-none transition placeholder:text-[#9AA7A1] focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                        </div>
                                        <div class="min-w-0 sm:col-span-2">
                                            <label :for="'parte-cargo-' + idx" class="block text-[11px] font-semibold text-grafito">
                                                Cargo / Institución
                                            </label>
                                            <input :id="'parte-cargo-' + idx" type="text" x-model="parte.cargo_institucion" maxlength="150"
                                                   class="mt-1 w-full min-w-0 rounded-lg border border-white/80 bg-white/90 px-3 py-2 text-sm text-grafito outline-none transition placeholder:text-[#9AA7A1] focus:border-[#1E7A52] focus:ring-2 focus:ring-[#1E7A52]/15">
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </div>
                </section>

                {{-- Acciones --}}
                <div class="flex flex-col gap-2 rounded-xl border border-[#E3E8E4] bg-white px-5 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between xl:col-span-12">
                    <p class="text-xs text-gris">Revise la información antes de registrar el expediente.</p>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="inline-flex justify-center rounded-lg bg-verde-profundo px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-verde-institucional">
                            Registrar expediente
                        </button>
                    </div>
                </div>
            </form>

            {{-- Overlay de envío --}}
            <div x-show="enviando" x-transition
                 class="mx-auto max-w-md rounded-2xl border border-[#E3E8E4] bg-white px-8 py-12 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[#DCEBDD]">
                    <i class="fa-solid fa-circle-notch fa-spin text-xl text-verde-institucional"></i>
                </div>
                <p class="text-sm font-semibold text-grafito">Registrando expediente...</p>
                <p class="mt-1 text-xs text-gris">Se generará el NURED y se guardará la documentación en la cadena de custodia.</p>
            </div>
        </div>
    </div>

    <script>
        function aperturaCausa() {
            return {
                viaVerbal: '{{ Expediente::VIA_INGRESO_VERBAL }}',
                viaDistrital: '{{ Expediente::VIA_INGRESO_DISTRITAL }}',
                form: {
                    via: '',
                    numero_hoja_ruta: '',
                    naturaleza: '',
                    reglamento_id: '',
                    resumen_hechos: '',
                    adjunto: null,
                },
                partes: [],
                reglamentos: [],
                cargandoReglamentos: false,
                errorReglamentos: false,
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
                        tipo: this.partes.length === 1 ? 'DENUNCIADO' : 'DENUNCIANTE',
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
                    if (!this.form.naturaleza) {
                        window.apiToast('error', 'Seleccione la naturaleza del expediente (jurisdiccional o administrativa).');
                        return;
                    }
                    if (this.form.via === this.viaDistrital && !this.form.numero_hoja_ruta.trim()) {
                        window.apiToast('error', 'El número de hoja de ruta es obligatorio para ingresos desde distrital.');
                        return;
                    }
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
                    if (this.form.via === this.viaDistrital) {
                        formData.append('numero_hoja_ruta', this.form.numero_hoja_ruta);
                    }
                    formData.append('naturaleza', this.form.naturaleza);
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
                            const boletaUrl = data?.data?.boleta_apertura?.url || data?.boleta_apertura?.url || null;
                            const volverABandeja = () => {
                                window.location.href = '/ventanilla/aperturas';
                            };

                            window.apiToast('exito', 'Expediente registrado correctamente.');

                            if (boletaUrl) {
                                window.imprimirPdf({
                                    url: boletaUrl,
                                    titulo: 'Boleta de recepción',
                                    alCerrar: volverABandeja,
                                });
                            } else {
                                setTimeout(volverABandeja, 900);
                            }
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
