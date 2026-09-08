@extends('layouts.app')

@section('titulo', 'Gestión de Feriados')

@section('contenido')
<div class="p-6" x-data="gestionFeriados()" x-init="cargar()">

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
            @click="abrirModal()"
            class="bg-verde-profundo text-white px-4 py-2.5 rounded-lg text-sm font-medium">
            <i class="fa-solid fa-calendar-plus mr-2"></i>
            Registrar feriado
        </button>

    </div>

    <!-- AVISO -->
    <div class="bg-azul-petroleo/10 border border-azul-petroleo/20
                rounded-xl p-4 mb-5 text-sm text-azul-petroleo">

        <i class="fa-solid fa-circle-info mr-2"></i>

        Los feriados registrados serán considerados automáticamente
        para el cálculo de los plazos de los expedientes.

    </div>

    <!-- TABLA -->
    <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gris-claro/60 border-b border-gris-claro">

                    <tr>
                        <th class="text-left px-5 py-3 text-xs">Fecha</th>
                        <th class="text-left px-5 py-3 text-xs">Descripción</th>
                        <th class="text-left px-5 py-3 text-xs">Tipo</th>
                        <th class="text-left px-5 py-3 text-xs">Estado</th>
                        <th class="text-right px-5 py-3 text-xs">Acciones</th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-gris-claro">

                    <template x-for="feriado in feriados" :key="feriado.id">

                        <tr class="hover:bg-gris-claro/30">

                            <td class="px-5 py-4 font-semibold text-grafito"
                                x-text="feriado.fecha">
                            </td>

                            <td class="px-5 py-4">
                                <p class="font-medium text-grafito"
                                   x-text="feriado.nombre"></p>

                                <p class="text-xs text-gris"
                                   x-text="feriado.descripcion || ''"></p>
                            </td>

                            <td class="px-5 py-4">

                                <span class="px-2 py-1 rounded-full bg-azul-petroleo/10
                                             text-azul-petroleo text-[10px] font-semibold"
                                      x-text="feriado.tipo">
                                </span>

                            </td>

                            <td class="px-5 py-4">

                                <span class="px-2 py-1 rounded-full text-[10px] font-semibold"
                                      :class="feriado.activo
                                        ? 'bg-[#8CC63F]/20 text-[#3F5E1B]'
                                        : 'bg-gris-claro text-gris'">

                                    <span x-text="feriado.activo ? 'Activo' : 'Inactivo'"></span>

                                </span>

                            </td>

                            <td class="px-5 py-4">

                                <div class="flex justify-end gap-2">

                                    <button
                                        @click="editar(feriado)"
                                        class="w-8 h-8 rounded-lg border border-gris-claro">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>

                                    <button
                                        @click="eliminar(feriado)"
                                        class="w-8 h-8 rounded-lg border border-gris-claro text-red-600">
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
    <div x-show="modal" x-cloak
         class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">

        <div @click.outside="modal = false"
             class="bg-white rounded-xl shadow-xl w-full max-w-md">

            <div class="p-5 border-b border-gris-claro flex justify-between">

                <div>
                    <h2 class="font-bold text-grafito">
                        <span x-text="form.id ? 'Editar feriado' : 'Registrar feriado'"></span>
                    </h2>
                </div>

                <button @click="modal = false">
                    <i class="fa-solid fa-xmark text-gris"></i>
                </button>

            </div>

            <div class="p-5 space-y-4">

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Fecha
                    </label>

                    <input type="date"
                           x-model="form.fecha"
                           class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Nombre
                    </label>

                    <input type="text"
                           x-model="form.nombre"
                           placeholder="Ej. Día de la Independencia"
                           class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Tipo
                    </label>

                    <select x-model="form.tipo"
                            class="w-full mt-1 border-gris-claro rounded-lg text-sm">

                        <option value="NACIONAL">Nacional</option>
                        <option value="DEPARTAMENTAL">Departamental</option>
                        <option value="INSTITUCIONAL">Institucional</option>

                    </select>
                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Descripción
                    </label>

                    <textarea
                        x-model="form.descripcion"
                        rows="3"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                    </textarea>
                </div>

            </div>

            <div class="p-5 border-t border-gris-claro flex justify-end gap-2">

                <button
                    @click="modal = false"
                    class="px-4 py-2 border border-gris-claro rounded-lg text-sm">
                    Cancelar
                </button>

                <button
                    @click="guardar()"
                    class="px-4 py-2 bg-verde-profundo text-white rounded-lg text-sm">
                    Guardar
                </button>

            </div>

        </div>

    </div>

</div>

<script>
function gestionFeriados() {

    return {

        feriados: [],

        modal: false,

        form: {
            id: null,
            fecha: '',
            nombre: '',
            tipo: 'NACIONAL',
            descripcion: ''
        },

        async cargar() {

            /*
             * GET /api/administrador/feriados
             */

            this.feriados = [
                {
                    id: 1,
                    fecha: '2026-08-06',
                    nombre: 'Día de la Independencia de Bolivia',
                    descripcion: 'Feriado nacional',
                    tipo: 'NACIONAL',
                    activo: true
                }
            ];
        },

        abrirModal() {

            this.form = {
                id: null,
                fecha: '',
                nombre: '',
                tipo: 'NACIONAL',
                descripcion: ''
            };

            this.modal = true;
        },

        editar(feriado) {

            this.form = { ...feriado };

            this.modal = true;
        },

        async guardar() {

            this.modal = false;

            this.$dispatch('toast', {
                tipo: 'exito',
                mensaje: 'Feriado guardado correctamente.'
            });

            await this.cargar();
        },

        async eliminar(feriado) {

            if (!confirm('¿Desea eliminar este feriado?')) return;

            this.$dispatch('toast', {
                tipo: 'exito',
                mensaje: 'Feriado eliminado correctamente.'
            });

            await this.cargar();
        }
    }
}
</script>
@endsection