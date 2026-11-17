@extends('layouts.app')

@section('titulo', 'Requisitos de admisibilidad')

@section('contenido')

<div
    class="p-6"
    x-data="gestionRequisitos()"
    x-init="cargar()"
>

    <!-- ENCABEZADO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">

        <div>
            <h1 class="text-xl font-bold text-grafito">
                Requisitos de admisibilidad
            </h1>

            <p class="text-sm text-gris">
                Administre el catálogo de requisitos por reglamento sin modificar el código.
            </p>
        </div>

        <button
            @click="abrirCrear()"
            class="bg-verde-profundo text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-verde-institucional transition"
        >
            <i class="fa-solid fa-plus mr-1.5"></i>
            Nuevo requisito
        </button>

    </div>

    <!-- FILTROS -->
    <div class="bg-white rounded-xl border border-gris-claro shadow p-4 mb-5">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-grafito mb-1">
                    Buscar
                </label>

                <input
                    type="text"
                    x-model="filtros.buscar"
                    @input.debounce.400ms="cargar()"
                    placeholder="Descripción del requisito"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1">
                    Reglamento
                </label>

                <select
                    x-model="filtros.reglamento_id"
                    @change="cargar()"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Todos</option>

                    <template x-for="reglamento in reglamentos" :key="reglamento.id">
                        <option
                            :value="reglamento.id"
                            x-text="`${reglamento.codigo} — ${reglamento.nombre}`"
                        ></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1">
                    Estado
                </label>

                <select
                    x-model="filtros.activo"
                    @change="cargar()"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>

        </div>

    </div>

    <!-- ERROR / AVISO -->
    <template x-if="error">
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>
            <span x-text="error"></span>
        </div>
    </template>

    <!-- TABLA -->
    <div class="bg-white rounded-xl border border-gris-claro shadow overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-fondo border-b border-gris-claro">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-grafito">Orden</th>
                        <th class="text-left px-4 py-3 font-semibold text-grafito">Requisito</th>
                        <th class="text-left px-4 py-3 font-semibold text-grafito">Reglamento</th>
                        <th class="text-center px-4 py-3 font-semibold text-grafito">Crítico</th>
                        <th class="text-center px-4 py-3 font-semibold text-grafito">Estado</th>
                        <th class="text-right px-4 py-3 font-semibold text-grafito">Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    <!-- CARGANDO -->
                    <template x-if="cargando">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gris">
                                <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                                Cargando requisitos...
                            </td>
                        </tr>
                    </template>

                    <!-- VACÍO -->
                    <template x-if="!cargando && requisitos.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gris">
                                No hay requisitos registrados con los filtros seleccionados.
                            </td>
                        </tr>
                    </template>

                    <!-- FILAS -->
                    <template x-for="requisito in requisitos" :key="requisito.id">
                        <tr class="border-b border-gris-claro last:border-0 hover:bg-fondo/60">
                            <td class="px-4 py-3 text-gris" x-text="requisito.orden"></td>

                            <td class="px-4 py-3 text-grafito" x-text="requisito.descripcion"></td>

                            <td class="px-4 py-3 text-gris">
                                <span
                                    x-text="requisito.reglamento
                                        ? `${requisito.reglamento.codigo} — ${requisito.reglamento.nombre}`
                                        : 'Sin reglamento'"
                                ></span>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="requisito.es_critico
                                        ? 'bg-red-100 text-red-700'
                                        : 'bg-gray-100 text-gray-600'"
                                    x-text="requisito.es_critico ? 'Sí' : 'No'"
                                ></span>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="requisito.activo
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-600'"
                                    x-text="requisito.activo ? 'Activo' : 'Inactivo'"
                                ></span>
                            </td>

                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button
                                    @click="abrirEditar(requisito)"
                                    class="text-verde-profundo hover:text-verde-institucional px-2 py-1"
                                    title="Editar requisito"
                                >
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <button
                                    x-show="requisito.activo"
                                    @click="desactivar(requisito)"
                                    class="text-red-600 hover:text-red-800 px-2 py-1"
                                    title="Desactivar requisito"
                                >
                                    <i class="fa-solid fa-ban"></i>
                                </button>

                                <button
                                    x-show="!requisito.activo"
                                    @click="reactivar(requisito)"
                                    class="text-verde-profundo hover:text-verde-institucional px-2 py-1"
                                    title="Reactivar requisito"
                                >
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </td>
                        </tr>
                    </template>

                </tbody>

            </table>

        </div>

    </div>

    <!-- MODAL CREAR / EDITAR -->
    <template x-if="modalAbierto">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">

            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6" @click.outside="cerrarModal()">

                <div class="flex items-center justify-between mb-4">
                    <h2
                        class="text-lg font-bold text-grafito"
                        x-text="editando ? 'Editar requisito' : 'Nuevo requisito'"
                    ></h2>

                    <button @click="cerrarModal()" class="text-gris hover:text-grafito">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="guardar()" class="space-y-4">

                    <div>
                        <label class="block text-xs font-medium text-grafito mb-1">
                            Reglamento <span class="text-red-600">*</span>
                            <span class="text-gris cursor-help" title="Reglamento al que pertenece el requisito; determina qué expedientes lo exigen." aria-label="Ayuda: reglamento del requisito">
                                <i class="fa-regular fa-circle-question"></i>
                            </span>
                        </label>

                        <select
                            x-model="formulario.reglamento_id"
                            required
                            title="Reglamento al que pertenece el requisito; determina qué expedientes lo exigen."
                            class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                        >
                            <option value="">Seleccione un reglamento</option>

                            <template x-for="reglamento in reglamentos" :key="reglamento.id">
                                <option
                                    :value="reglamento.id"
                                    x-text="`${reglamento.codigo} — ${reglamento.nombre}`"
                                ></option>
                            </template>
                        </select>

                        <p x-show="errores.reglamento_id" class="text-xs text-red-600 mt-1" x-text="errores.reglamento_id"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-grafito mb-1">
                            Descripción del requisito <span class="text-red-600">*</span>
                            <span class="text-gris cursor-help" title="Texto que verá el operador al evaluar la admisibilidad. Debe ser claro y verificable." aria-label="Ayuda: descripción del requisito">
                                <i class="fa-regular fa-circle-question"></i>
                            </span>
                        </label>

                        <textarea
                            x-model="formulario.descripcion"
                            rows="3"
                            maxlength="300"
                            required
                            title="Texto que verá el operador al evaluar la admisibilidad. Debe ser claro y verificable."
                            class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                        ></textarea>

                        <p x-show="errores.descripcion" class="text-xs text-red-600 mt-1" x-text="errores.descripcion"></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                        <div>
                            <label class="block text-xs font-medium text-grafito mb-1">
                                Orden
                                <span class="text-gris cursor-help" title="Posición del requisito dentro del reglamento (menor número aparece primero)." aria-label="Ayuda: orden del requisito">
                                    <i class="fa-regular fa-circle-question"></i>
                                </span>
                            </label>

                            <input
                                type="number"
                                min="0"
                                max="32767"
                                x-model.number="formulario.orden"
                                title="Posición del requisito dentro del reglamento (menor número aparece primero)."
                                class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                            >

                            <p x-show="errores.orden" class="text-xs text-red-600 mt-1" x-text="errores.orden"></p>
                        </div>

                        <label class="flex items-center gap-2 mt-5 text-sm text-grafito cursor-help" title="Si el requisito falta o no se cumple, la evaluación puede rechazar el expediente.">
                            <input type="checkbox" x-model="formulario.es_critico" class="rounded border-gris-claro">
                            Requisito crítico
                            <i class="fa-regular fa-circle-question text-gris"></i>
                        </label>

                        <label class="flex items-center gap-2 mt-5 text-sm text-grafito cursor-help" title="Solo los requisitos activos se exigen en nuevas evaluaciones de admisibilidad.">
                            <input type="checkbox" x-model="formulario.activo" class="rounded border-gris-claro">
                            Activo
                            <i class="fa-regular fa-circle-question text-gris"></i>
                        </label>

                    </div>

                    <div class="flex justify-end gap-2 pt-2">

                        <button
                            type="button"
                            @click="cerrarModal()"
                            class="px-4 py-2 rounded-lg text-sm border border-gris-claro text-grafito hover:bg-fondo"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            :disabled="guardando"
                            class="bg-verde-profundo text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-verde-institucional disabled:opacity-60"
                        >
                            <i class="fa-solid fa-floppy-disk mr-1.5" x-show="!guardando"></i>
                            <i class="fa-solid fa-spinner fa-spin mr-1.5" x-show="guardando"></i>
                            <span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
                        </button>

                    </div>

                </form>

            </div>

        </div>
    </template>

</div>

<script>
function gestionRequisitos() {
    return {
        requisitos: [],
        reglamentos: [],
        cargando: false,
        guardando: false,
        error: null,
        modalAbierto: false,
        editando: null,
        errores: {},
        filtros: {
            buscar: '',
            reglamento_id: '',
            activo: '',
        },
        formulario: {
            reglamento_id: '',
            descripcion: '',
            orden: 0,
            es_critico: false,
            activo: true,
        },

        async cargar() {
            this.cargando = true;
            this.error = null;

            try {
                const parametros = new URLSearchParams();

                if (this.filtros.buscar) {
                    parametros.set('buscar', this.filtros.buscar);
                }

                if (this.filtros.reglamento_id) {
                    parametros.set('reglamento_id', this.filtros.reglamento_id);
                }

                if (this.filtros.activo !== '') {
                    parametros.set('activo', this.filtros.activo);
                }

                const consulta = parametros.toString();
                const url = '/api/admin/requisitos' + (consulta ? `?${consulta}` : '');
                const { ok, data } = await window.apiFetch(url);

                if (!ok) {
                    throw new Error(data?.message || 'No se pudieron cargar los requisitos.');
                }

                this.requisitos = data.data || [];
                this.reglamentos = data.reglamentos || [];
            } catch (e) {
                this.error = e.message;
            } finally {
                this.cargando = false;
            }
        },

        abrirCrear() {
            this.editando = null;
            this.errores = {};
            this.formulario = {
                reglamento_id: this.filtros.reglamento_id || '',
                descripcion: '',
                orden: 0,
                es_critico: false,
                activo: true,
            };
            this.modalAbierto = true;
        },

        abrirEditar(requisito) {
            this.editando = requisito;
            this.errores = {};
            this.formulario = {
                reglamento_id: requisito.reglamento?.id || '',
                descripcion: requisito.descripcion,
                orden: requisito.orden,
                es_critico: Boolean(requisito.es_critico),
                activo: Boolean(requisito.activo),
            };
            this.modalAbierto = true;
        },

        cerrarModal() {
            if (this.guardando) {
                return;
            }

            this.modalAbierto = false;
            this.errores = {};
        },

        async guardar() {
            this.guardando = true;
            this.errores = {};

            try {
                const url = this.editando
                    ? `/api/admin/requisitos/${this.editando.id}`
                    : '/api/admin/requisitos';

                const { ok, data } = await window.apiFetch(url, {
                    method: this.editando ? 'PUT' : 'POST',
                    body: {
                        reglamento_id: Number(this.formulario.reglamento_id),
                        descripcion: this.formulario.descripcion,
                        orden: Number(this.formulario.orden) || 0,
                        es_critico: Boolean(this.formulario.es_critico),
                        activo: Boolean(this.formulario.activo),
                    },
                });

                if (!ok) {
                    if (data?.errors) {
                        this.errores = Object.fromEntries(
                            Object.entries(data.errors).map(([campo, mensajes]) => [campo, mensajes[0]])
                        );
                    }

                    throw new Error(data?.message || 'No se pudo guardar el requisito.');
                }

                this.modalAbierto = false;

                if (window.apiToast) {
                    window.apiToast('exito', data.message || 'Requisito guardado.');
                }

                await this.cargar();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.guardando = false;
            }
        },

        async desactivar(requisito) {
            if (!confirm(`¿Desactivar el requisito "${requisito.descripcion}"?`)) {
                return;
            }

            await this.cambiarEstado(requisito, 'DELETE');
        },

        async reactivar(requisito) {
            await this.cambiarEstado(requisito, 'PUT', { activo: true });
        },

        async cambiarEstado(requisito, metodo, cuerpo = null) {
            this.error = null;

            try {
                const { ok, data } = await window.apiFetch(`/api/admin/requisitos/${requisito.id}`, {
                    method: metodo,
                    body: cuerpo,
                });

                if (!ok) {
                    throw new Error(data?.message || 'No se pudo actualizar el requisito.');
                }

                if (window.apiToast) {
                    window.apiToast('exito', data.message || 'Requisito actualizado.');
                }

                await this.cargar();
            } catch (e) {
                this.error = e.message;
            }
        },
    };
}
</script>

@endsection
