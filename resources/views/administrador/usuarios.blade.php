@extends('layouts.app')

@section('titulo', 'Gestión de Usuarios')

@section('contenido')
<div class="p-6" x-data="gestionUsuarios()" x-init="cargar()">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">

        <div>
            <h1 class="text-xl font-bold text-grafito">
                Gestión de usuarios
            </h1>
            <p class="text-sm text-gris">
                Administración de los usuarios y sus roles dentro del sistema.
            </p>
        </div>

        <button
            @click="abrirCrear()"
            class="bg-verde-profundo hover:bg-verde-profundo/90 text-white
                   px-4 py-2.5 rounded-lg text-sm font-medium transition">
            <i class="fa-solid fa-user-plus mr-2"></i>
            Nuevo usuario
        </button>

    </div>

    <!-- FILTROS -->
    <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-4 mb-5">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-grafito mb-1">
                    Buscar
                </label>

                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gris"></i>

                    <input
                        type="text"
                        x-model="filtros.buscar"
                        @input.debounce.400ms="cargar()"
                        placeholder="Nombre, apellido o usuario..."
                        class="w-full pl-9 border-gris-claro rounded-lg text-sm
                               focus:border-verde-institucional focus:ring-verde-institucional">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1">
                    Rol
                </label>

                <select
                    x-model="filtros.rol"
                    @change="cargar()"
                    class="w-full border-gris-claro rounded-lg text-sm
                           focus:border-verde-institucional focus:ring-verde-institucional">

                    <option value="">Todos</option>
                    <option value="ADMINISTRADOR">Administrador</option>
                    <option value="ENCARGADA">Encargada</option>
                    <option value="TECNICO">Técnico</option>
                    <option value="AUDITOR">Auditor</option>

                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1">
                    Estado
                </label>

                <select
                    x-model="filtros.estado"
                    @change="cargar()"
                    class="w-full border-gris-claro rounded-lg text-sm">

                    <option value="">Todos</option>
                    <option value="ACTIVO">Activo</option>
                    <option value="INACTIVO">Inactivo</option>

                </select>
            </div>

        </div>

    </div>

    <!-- TABLA -->
    <div class="bg-white rounded-xl border border-gris-claro shadow-sm overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gris-claro/60 border-b border-gris-claro">

                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-grafito">
                            Usuario
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-grafito">
                            Rol
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-grafito">
                            Estado
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-grafito">
                            Último acceso
                        </th>

                        <th class="text-right px-5 py-3 text-xs font-semibold text-grafito">
                            Acciones
                        </th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-gris-claro">

                    <template x-for="usuario in usuarios" :key="usuario.id">

                        <tr class="hover:bg-gris-claro/30">

                            <td class="px-5 py-4">

                                <div class="flex items-center gap-3">

                                    <div class="w-9 h-9 rounded-full bg-verde-institucional/20
                                                flex items-center justify-center">
                                        <i class="fa-solid fa-user text-verde-profundo"></i>
                                    </div>

                                    <div>
                                        <p class="font-semibold text-grafito"
                                           x-text="usuario.nombre"></p>

                                        <p class="text-xs text-gris"
                                           x-text="usuario.username"></p>
                                    </div>

                                </div>

                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="px-2.5 py-1 rounded-full bg-azul-petroleo/10
                                           text-azul-petroleo text-[10px] font-semibold"
                                    x-text="usuario.rol">
                                </span>
                            </td>

                            <td class="px-5 py-4">

                                <span
                                    class="px-2.5 py-1 rounded-full text-[10px] font-semibold"
                                    :class="usuario.activo
                                        ? 'bg-[#8CC63F]/20 text-[#3F5E1B]'
                                        : 'bg-red-100 text-red-700'">

                                    <span x-text="usuario.activo ? 'Activo' : 'Inactivo'"></span>

                                </span>

                            </td>

                            <td class="px-5 py-4 text-xs text-gris"
                                x-text="usuario.ultimo_acceso || 'Nunca'">
                            </td>

                            <td class="px-5 py-4">

                                <div class="flex justify-end gap-2">

                                    <button
                                        @click="editar(usuario)"
                                        class="w-8 h-8 rounded-lg border border-gris-claro
                                               hover:bg-gris-claro text-grafito">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>

                                    <button
                                        @click="cambiarEstado(usuario)"
                                        class="w-8 h-8 rounded-lg border border-gris-claro
                                               hover:bg-gris-claro"
                                        :class="usuario.activo ? 'text-red-600' : 'text-verde-profundo'">

                                        <i
                                            :class="usuario.activo
                                                ? 'fa-solid fa-user-slash'
                                                : 'fa-solid fa-user-check'"
                                            class="text-xs">
                                        </i>

                                    </button>

                                </div>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>

        <div x-show="usuarios.length === 0"
             class="p-10 text-center text-gris">
            <i class="fa-solid fa-users-slash text-3xl mb-2"></i>
            <p>No se encontraron usuarios.</p>
        </div>

    </div>

    <!-- MODAL -->
    <div
        x-show="modal"
        x-cloak
        class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">

        <div
            @click.outside="modal = false"
            class="bg-white rounded-xl shadow-xl w-full max-w-lg">

            <div class="p-5 border-b border-gris-claro flex justify-between">

                <div>
                    <h2 class="font-bold text-grafito"
                        x-text="modo === 'crear' ? 'Nuevo usuario' : 'Editar usuario'">
                    </h2>

                    <p class="text-xs text-gris mt-1">
                        Complete la información del usuario.
                    </p>
                </div>

                <button @click="modal = false"
                        class="text-gris hover:text-grafito">
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>

            <div class="p-5 space-y-4">

                <div class="grid grid-cols-2 gap-3">

                    <div>
                        <label class="text-xs font-medium text-grafito">
                            Nombres
                        </label>
                        <input
                            x-model="form.nombres"
                            class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                    </div>

                    <div>
                        <label class="text-xs font-medium text-grafito">
                            Apellidos
                        </label>
                        <input
                            x-model="form.apellidos"
                            class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                    </div>

                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Usuario
                    </label>
                    <input
                        x-model="form.username"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Rol
                    </label>

                    <select
                        x-model="form.rol"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">

                        <option value="TECNICO">Técnico</option>
                        <option value="AUDITOR">Auditor</option>
                        <option value="ENCARGADA">Encargada</option>
                        <option value="ADMINISTRADOR">Administrador</option>

                    </select>

                </div>

            </div>

            <div class="p-5 border-t border-gris-claro flex justify-end gap-2">

                <button
                    @click="modal = false"
                    class="px-4 py-2 rounded-lg border border-gris-claro text-sm">
                    Cancelar
                </button>

                <button
                    @click="guardar()"
                    class="px-4 py-2 rounded-lg bg-verde-profundo text-white text-sm">
                    Guardar
                </button>

            </div>

        </div>

    </div>

</div>

<script>
function gestionUsuarios() {
    return {

        usuarios: [],

        filtros: {
            buscar: '',
            rol: '',
            estado: ''
        },

        modal: false,
        modo: 'crear',

        form: {
            id: null,
            nombres: '',
            apellidos: '',
            username: '',
            rol: 'TECNICO'
        },

        async cargar() {

            /*
             * Posteriormente:
             * GET /api/administrador/usuarios
             */

            this.usuarios = [
                {
                    id: 1,
                    nombre: 'Juan Pérez',
                    username: 'jperez',
                    rol: 'TECNICO',
                    activo: true,
                    ultimo_acceso: '08/09/2026 09:32'
                },
                {
                    id: 2,
                    nombre: 'María López',
                    username: 'mlopez',
                    rol: 'AUDITOR',
                    activo: true,
                    ultimo_acceso: '08/09/2026 10:15'
                },
                {
                    id: 3,
                    nombre: 'Carlos Rodríguez',
                    username: 'crodriguez',
                    rol: 'TECNICO',
                    activo: false,
                    ultimo_acceso: '05/09/2026 15:21'
                }
            ];
        },

        abrirCrear() {
            this.modo = 'crear';
            this.form = {
                id: null,
                nombres: '',
                apellidos: '',
                username: '',
                rol: 'TECNICO'
            };
            this.modal = true;
        },

        editar(usuario) {
            this.modo = 'editar';
            this.form = {
                id: usuario.id,
                nombres: usuario.nombre.split(' ')[0],
                apellidos: usuario.nombre.split(' ').slice(1).join(' '),
                username: usuario.username,
                rol: usuario.rol
            };
            this.modal = true;
        },

        async guardar() {
            this.modal = false;
            this.$dispatch('toast', {
                tipo: 'exito',
                mensaje: 'Usuario guardado correctamente.'
            });
            await this.cargar();
        },

        async cambiarEstado(usuario) {

            const accion = usuario.activo ? 'inactivar' : 'activar';

            if (!confirm(`¿Desea ${accion} este usuario?`)) return;

            usuario.activo = !usuario.activo;

            this.$dispatch('toast', {
                tipo: 'exito',
                mensaje: `Usuario ${usuario.activo ? 'activado' : 'inactivado'}.`
            });
        }
    }
}
</script>
@endsection