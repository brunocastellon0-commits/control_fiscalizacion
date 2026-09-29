@extends('layouts.app')

@section('titulo', 'Gestión de Feriados')

@section('contenido')

<div
    class="p-6"
    x-data="gestionFeriados()"
    x-init="cargar()"
>

    <!-- ENCABEZADO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">

        <div>
            <h1 class="text-xl font-bold text-grafito">
                Gestión de feriados
            </h1>

            <p class="text-sm text-gris">
                Administre los días que no deben ser considerados dentro de los plazos.
            </p>
        </div>

        <button
            @click="abrirCrear()"
            class="bg-verde-profundo text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-verde-institucional transition"
        >
            <i class="fa-solid fa-calendar-plus mr-2"></i>
            Registrar feriado
        </button>

    </div>


    <!-- INFORMACIÓN -->
    <div class="bg-azul-petroleo/10 border border-azul-petroleo/20
                rounded-xl p-4 mb-5 text-sm text-azul-petroleo">

        <i class="fa-solid fa-circle-info mr-2"></i>

        Los feriados registrados serán considerados automáticamente
        para el cálculo de los plazos de los expedientes.

    </div>


    <!-- FILTROS -->
    <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-4 mb-5">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

            <!-- BUSCAR -->
            <div>
                <label class="block text-xs font-medium text-grafito mb-1">
                    Buscar
                </label>

                <div class="relative">

                    <i class="fa-solid fa-magnifying-glass
                              absolute left-3 top-1/2 -translate-y-1/2
                              text-gris text-xs">
                    </i>

                    <input
                        type="text"
                        x-model="filtros.buscar"
                        @input="onFiltroTexto()"
                        placeholder="Descripción o ámbito..."
                        class="w-full border border-gris-claro rounded-lg
                               pl-9 pr-3 py-2 text-sm"
                    >

                </div>
            </div>


            <!-- ÁMBITO -->
            <div>

                <label class="block text-xs font-medium text-grafito mb-1">
                    Ámbito
                </label>

                <select
                    x-model="filtros.ambito"
                    @change="cargar()"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >

                    <option value="">Todos los ámbitos</option>

                    <template x-for="ambito in ambitos" :key="ambito">

                        <option
                            :value="ambito"
                            x-text="formatearAmbito(ambito)"
                        ></option>

                    </template>

                </select>

            </div>


            <!-- AÑO -->
            <div>

                <label class="block text-xs font-medium text-grafito mb-1">
                    Año
                </label>

                <select
                    x-model="filtros.anio"
                    @change="cargar()"
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >

                    <option value="">Todos los años</option>

                    <template x-for="anio in anios" :key="anio">

                        <option
                            :value="anio"
                            x-text="anio"
                        ></option>

                    </template>

                </select>

            </div>

        </div>

    </div>


    <!-- ERROR -->
    <template x-if="error">

        <div class="bg-red-50 border border-red-200 text-red-700
                    rounded-xl p-4 mb-5 text-sm">

            <i class="fa-solid fa-circle-exclamation mr-2"></i>

            <span x-text="error"></span>

        </div>

    </template>


    <!-- TABLA -->
    <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gris-claro/60 border-b border-gris-claro">

                    <tr>

                        <th class="text-left px-5 py-3 text-xs font-semibold">
                            Fecha
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold">
                            Descripción
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold">
                            Ámbito
                        </th>

                        <th class="text-right px-5 py-3 text-xs font-semibold">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gris-claro">

                    <!-- CARGANDO -->

                    <template x-if="cargando">

                        <tr>

                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-gris"
                            >

                                <i class="fa-solid fa-spinner fa-spin text-lg"></i>

                                <p class="mt-2">
                                    Cargando feriados...
                                </p>

                            </td>

                        </tr>

                    </template>


                    <!-- SIN DATOS -->

                    <template x-if="!cargando && feriados.length === 0">

                        <tr>

                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-gris"
                            >

                                <i class="fa-regular fa-calendar-xmark text-2xl"></i>

                                <p class="mt-2 font-medium">
                                    No se encontraron feriados.
                                </p>

                            </td>

                        </tr>

                    </template>


                    <!-- DATOS -->

                    <template
                        x-for="feriado in feriados"
                        :key="feriado.id"
                    >

                        <tr class="hover:bg-gris-claro/30 transition">

                            <!-- FECHA -->

                            <td class="px-5 py-4">

                                <span
                                    class="font-semibold text-grafito"
                                    x-text="formatearFecha(feriado.fecha)"
                                ></span>

                            </td>


                            <!-- DESCRIPCIÓN -->

                            <td class="px-5 py-4">

                                <p
                                    class="font-medium text-grafito"
                                    x-text="feriado.descripcion"
                                ></p>

                            </td>


                            <!-- ÁMBITO -->

                            <td class="px-5 py-4">

                                <span
                                    class="px-2 py-1 rounded-full
                                           bg-azul-petroleo/10
                                           text-azul-petroleo
                                           text-[10px] font-semibold"
                                    x-text="formatearAmbito(feriado.ambito)"
                                ></span>

                            </td>


                            <!-- ACCIONES -->

                            <td class="px-5 py-4">

                                <div class="flex justify-end gap-2">

                                    <button
                                        @click="abrirEditar(feriado)"
                                        title="Editar"
                                        class="w-8 h-8 rounded-lg
                                               border border-gris-claro
                                               hover:bg-gris-claro transition"
                                    >

                                        <i class="fa-solid fa-pen text-xs"></i>

                                    </button>


                                    <button
                                        @click="eliminar(feriado)"
                                        title="Eliminar"
                                        class="w-8 h-8 rounded-lg
                                               border border-red-200
                                               text-red-600
                                               hover:bg-red-50 transition"
                                    >

                                        <i class="fa-solid fa-trash text-xs"></i>

                                    </button>

                                </div>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>

    </div>


    <!-- MODAL -->
    <div
        x-show="modalAbierto"
        x-cloak
        class="fixed inset-0 z-50 bg-black/40
               flex items-center justify-center p-4"
    >

        <div
            @click.outside="cerrarModal()"
            class="bg-white rounded-xl shadow-xl
                   w-full max-w-md"
        >

            <!-- CABECERA MODAL -->

            <div
                class="p-5 border-b border-gris-claro
                       flex items-center justify-between"
            >

                <div>

                    <h2 class="font-bold text-grafito">

                        <span
                            x-text="modoEdicion
                                ? 'Editar feriado'
                                : 'Registrar feriado'"
                        ></span>

                    </h2>

                    <p class="text-xs text-gris mt-1">
                        Complete los datos del feriado.
                    </p>

                </div>


                <button @click="cerrarModal()">

                    <i class="fa-solid fa-xmark text-gris"></i>

                </button>

            </div>


            <!-- FORMULARIO -->

            <form
                @submit.prevent="guardar()"
                class="p-5 space-y-4"
            >

                <!-- FECHA -->

                <div>

                    <label
                        class="block text-xs font-medium text-grafito mb-1"
                    >
                        Fecha
                    </label>

                    <input
                        type="date"
                        x-model="form.fecha"
                        required
                        class="w-full border border-gris-claro
                               rounded-lg px-3 py-2 text-sm"
                    >

                </div>


                <!-- DESCRIPCIÓN -->

                <div>

                    <label
                        class="block text-xs font-medium text-grafito mb-1"
                    >
                        Descripción
                    </label>

                    <input
                        type="text"
                        x-model="form.descripcion"
                        required
                        maxlength="255"
                        placeholder="Ej. Día de la Independencia de Bolivia"
                        class="w-full border border-gris-claro
                               rounded-lg px-3 py-2 text-sm"
                    >

                </div>


                <!-- ÁMBITO -->

                <div>

                    <label
                        class="block text-xs font-medium text-grafito mb-1"
                    >
                        Ámbito
                    </label>

                    <select
                        x-model="form.ambito"
                        required
                        class="w-full border border-gris-claro
                               rounded-lg px-3 py-2 text-sm"
                    >

                        <option value="NACIONAL">
                            Nacional
                        </option>

                        <option value="DEPARTAMENTAL">
                            Departamental
                        </option>

                        <option value="INSTITUCIONAL">
                            Institucional
                        </option>

                    </select>

                </div>


                <!-- ERRORES -->

                <template x-if="erroresForm">

                    <div
                        class="p-3 rounded-lg
                               bg-red-50 border border-red-200
                               text-red-700 text-xs
                               whitespace-pre-line"
                        x-text="erroresForm"
                    ></div>

                </template>


                <!-- BOTONES -->

                <div
                    class="flex justify-end gap-2
                           pt-2"
                >

                    <button
                        type="button"
                        @click="cerrarModal()"
                        class="px-4 py-2 rounded-lg
                               border border-gris-claro
                               text-sm text-grafito
                               hover:bg-gris-claro"
                    >
                        Cancelar
                    </button>


                    <button
                        type="submit"
                        :disabled="guardando"
                        class="px-4 py-2 rounded-lg
                               bg-verde-profundo
                               text-white text-sm
                               hover:bg-verde-institucional
                               disabled:opacity-50"
                    >

                        <i
                            class="fa-solid fa-spinner fa-spin mr-1.5"
                            x-show="guardando"
                        ></i>

                        <span
                            x-text="modoEdicion
                                ? 'Guardar cambios'
                                : 'Registrar feriado'"
                        ></span>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

function gestionFeriados() {

    return {

        feriados: [],

        ambitos: [
            'NACIONAL',
            'DEPARTAMENTAL',
            'INSTITUCIONAL'
        ],

        anios: [],

        filtros: {
            buscar: '',
            ambito: '',
            anio: ''
        },

        cargando: true,

        error: '',

        _debounce: null,

        modalAbierto: false,

        modoEdicion: false,

        guardando: false,

        erroresForm: '',

        form: {
            id: null,
            fecha: '',
            descripcion: '',
            ambito: 'NACIONAL'
        },


        async cargar() {

            this.cargando = true;
            this.error = '';

            try {

                const params = new URLSearchParams();

                if (this.filtros.buscar) {
                    params.set(
                        'buscar',
                        this.filtros.buscar
                    );
                }

                if (this.filtros.ambito) {
                    params.set(
                        'ambito',
                        this.filtros.ambito
                    );
                }

                if (this.filtros.anio) {
                    params.set(
                        'anio',
                        this.filtros.anio
                    );
                }


                const url = `/api/admin/feriados?${params.toString()}`;

                const respuesta =
                    await window.apiFetch(url);


                if (!respuesta.ok) {

                    throw new Error(
                        respuesta.data?.message ||
                        'No se pudieron cargar los feriados.'
                    );

                }


                this.feriados =
                    respuesta.data?.data ?? [];


                if (respuesta.data?.ambitos) {

                    this.ambitos =
                        respuesta.data.ambitos;

                }


                this.generarAnios();

            } catch (e) {

                console.error(
                    'ERROR FERiados:',
                    e
                );

                this.error = e.message;

                this.feriados = [];

            } finally {

                this.cargando = false;

            }

        },


        generarAnios() {

            const actual =
                new Date().getFullYear();

            const lista = [];

            for (
                let i = actual - 5;
                i <= actual + 5;
                i++
            ) {

                lista.push(i);

            }

            this.anios = lista;

        },


        onFiltroTexto() {

            clearTimeout(this._debounce);

            this._debounce =
                setTimeout(
                    () => this.cargar(),
                    350
                );

        },


        abrirCrear() {

            this.modoEdicion = false;

            this.erroresForm = '';

            this.form = {

                id: null,

                fecha: '',

                descripcion: '',

                ambito: 'NACIONAL'

            };

            this.modalAbierto = true;

        },


        abrirEditar(feriado) {

            this.modoEdicion = true;

            this.erroresForm = '';

            this.form = {

                id: feriado.id,

                fecha: feriado.fecha
                    ? feriado.fecha.substring(0, 10)
                    : '',

                descripcion:
                    feriado.descripcion ?? '',

                ambito:
                    feriado.ambito ?? 'NACIONAL'

            };

            this.modalAbierto = true;

        },


        cerrarModal() {

            if (this.guardando) {
                return;
            }

            this.modalAbierto = false;

        },


        async guardar() {

            this.guardando = true;

            this.erroresForm = '';

            try {

                const payload = {

                    fecha: this.form.fecha,

                    descripcion:
                        this.form.descripcion,

                    ambito:
                        this.form.ambito

                };


                const url = this.modoEdicion

                    ? `/api/admin/feriados/${this.form.id}`

                    : '/api/admin/feriados';


                const method = this.modoEdicion
                    ? 'PUT'
                    : 'POST';


                const respuesta =
                    await window.apiFetch(
                        url,
                        {
                            method,
                            body: payload
                        }
                    );


                if (!respuesta.ok) {

                    if (respuesta.data?.errors) {

                        this.erroresForm =
                            Object.values(
                                respuesta.data.errors
                            )
                            .flat()
                            .join('\n');

                    }

                    throw new Error(
                        respuesta.data?.message ||
                        'No se pudo guardar el feriado.'
                    );

                }


                window.apiToast(
                    'exito',
                    respuesta.data?.message ||
                    'Feriado guardado correctamente.'
                );


                this.cerrarModal();

                await this.cargar();

            } catch (e) {

                if (!this.erroresForm) {

                    window.apiToast(
                        'error',
                        e.message
                    );

                }

            } finally {

                this.guardando = false;

            }

        },


        async eliminar(feriado) {

            const confirmado = window.confirm(
                `¿Está seguro de eliminar el feriado del ${this.formatearFecha(feriado.fecha)}?`
            );


            if (!confirmado) {
                return;
            }


            try {

                const respuesta =
                    await window.apiFetch(
                        `/api/admin/feriados/${feriado.id}`,
                        {
                            method: 'DELETE'
                        }
                    );


                if (!respuesta.ok) {

                    throw new Error(
                        respuesta.data?.message ||
                        'No se pudo eliminar el feriado.'
                    );

                }


                window.apiToast(
                    'exito',
                    respuesta.data?.message ||
                    'Feriado eliminado correctamente.'
                );


                await this.cargar();

            } catch (e) {

                window.apiToast(
                    'error',
                    e.message
                );

            }

        },


        formatearFecha(fecha) {

            if (!fecha) {
                return '';
            }

            const partes =
                fecha.substring(0, 10).split('-');

            if (partes.length !== 3) {
                return fecha;
            }

            return `${partes[2]}/${partes[1]}/${partes[0]}`;

        },


        formatearAmbito(ambito) {

            const nombres = {

                NACIONAL: 'Nacional',

                DEPARTAMENTAL:
                    'Departamental',

                INSTITUCIONAL:
                    'Institucional'

            };

            return nombres[ambito] ?? ambito;

        }

    };

}

</script>

@endsection