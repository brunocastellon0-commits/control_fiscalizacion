@extends('layouts.app')

@section('titulo', 'Gestión de Usuarios')

@section('contenido')

    <div x-data="usuariosAdmin()" x-init="cargarUsuarios()" class="space-y-6">

        {{-- ENCABEZADO --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    Gestión de Usuarios
                </h1>

                <p class="text-sm text-gray-500 mt-1">
                    Administración de usuarios y sus roles dentro del sistema.
                </p>
            </div>

            <button @click="cargarUsuarios()" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                Actualizar
            </button>

        </div>


        {{-- MENSAJE DE ERROR --}}
        <template x-if="error">
            <div class="p-4 rounded-lg bg-red-100 text-red-700">
                <span x-text="error"></span>
            </div>
        </template>


        {{-- BUSCADOR --}}
        <div class="bg-white rounded-xl shadow p-5">

            <div class="flex flex-col md:flex-row gap-4">

                <div class="flex-1">

                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Buscar usuario
                    </label>

                    <input type="text" x-model="busqueda" placeholder="Buscar por CI, nombre, apellido o usuario..."
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">

                </div>

                <div class="md:w-56">

                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Estado
                    </label>

                    <select x-model="filtroEstado" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                        <option value="TODOS">Todos</option>
                        <option value="ACTIVOS">Activos</option>
                        <option value="INACTIVOS">Inactivos</option>
                    </select>

                </div>

            </div>

        </div>


        {{-- TABLA --}}
        <div class="bg-white rounded-xl shadow overflow-hidden">

            <div class="overflow-x-auto">

                <table class="w-full text-sm">

                    <thead class="bg-gray-100">

                        <tr>

                            <th class="px-4 py-3 text-left">
                                CI
                            </th>

                            <th class="px-4 py-3 text-left">
                                Nombre
                            </th>
                            <th class="px-4 py-3 text-left">
                                Apellidos
                            </th>

                            <th class="px-4 py-3 text-left">
                                Usuario
                            </th>

                            <th class="px-4 py-3 text-left">
                                Cargo
                            </th>

                            <th class="px-4 py-3 text-left">
                                Rol
                            </th>

                            <th class="px-4 py-3 text-center">
                                Estado
                            </th>

                            <th class="px-4 py-3 text-center">
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        {{-- CARGANDO --}}
                        <template x-if="cargando">

                            <tr>

                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Cargando usuarios...
                                </td>

                            </tr>

                        </template>


                        {{-- USUARIOS --}}
                        <template x-for="usuario in usuariosFiltrados" :key="usuario.id">

                            <tr class="border-t hover:bg-gray-50">

                                <td class="px-4 py-3" x-text="usuario.ci ?? '-'"></td>


                                <!-- <td class="px-4 py-3">

                                    <div class="font-medium text-gray-800"
                                        x-text="`${usuario.nombres ?? ''} ${usuario.apellidos ?? ''}`">
                                    </div>

                                </td> -->


                                <td class="px-4 py-3" x-text="usuario.nombres ?? '-'"></td>

                                <td class="px-4 py-3" x-text="usuario.apellidos ?? '-'"></td>
                                
                                <td class="px-4 py-3" x-text="usuario.username ?? '-'"></td>

                                <td class="px-4 py-3" x-text="usuario.cargo ?? '-'"></td>


                                <td class="px-4 py-3">

                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
                                        x-text="usuario.rol?.nombre ?? usuario.rol?.codigo ?? '-'"></span>

                                </td>


                                <td class="px-4 py-3 text-center">

                                    <span x-show="usuario.activo"
                                        class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        Activo
                                    </span>

                                    <span x-show="!usuario.activo"
                                        class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        Inactivo
                                    </span>

                                </td>


                                <td class="px-4 py-3 text-center">

                                    <button x-show="usuario.activo" @click="inactivarUsuario(usuario)"
                                        class="px-3 py-1 rounded-lg bg-red-600 text-white text-xs hover:bg-red-700">
                                        Inactivar
                                    </button>

                                    <span x-show="!usuario.activo" class="text-xs text-gray-400">
                                        Sin acciones
                                    </span>

                                </td>

                            </tr>

                        </template>


                        {{-- SIN RESULTADOS --}}
                        <template x-if="!cargando && usuariosFiltrados.length === 0">

                            <tr>

                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No se encontraron usuarios.
                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <script>

        function usuariosAdmin() {

            return {

                usuarios: [],

                busqueda: '',

                filtroEstado: 'TODOS',

                cargando: true,

                error: '',


                async cargarUsuarios() {

                    this.cargando = true;
                    this.error = '';

                    try {

                        const respuesta = await window.apiFetch(
                            '/api/usuarios'
                        );

                        console.log(
                            'RESPUESTA USUARIOS:',
                            respuesta
                        );

                        if (!respuesta.ok) {

                            throw new Error(
                                respuesta.data?.message ||
                                'No se pudieron cargar los usuarios.'
                            );

                        }

                        /*
                         * Laravel puede devolver los datos
                         * directamente o dentro de "data".
                         */
                        const resultado = respuesta.data;

                        this.usuarios =
                            resultado.data ??
                            resultado.usuarios ??
                            resultado ??
                            [];

                    } catch (error) {

                        console.error(
                            'ERROR USUARIOS:',
                            error
                        );

                        this.error = error.message;

                    } finally {

                        this.cargando = false;

                    }

                },


                get usuariosFiltrados() {

                    const texto =
                        this.busqueda
                            .toLowerCase()
                            .trim();


                    return this.usuarios.filter(usuario => {

                        const nombreCompleto =
                            `${usuario.nombres ?? ''} ${usuario.apellidos ?? ''}`
                                .toLowerCase();

                        const coincideBusqueda =
                            !texto ||
                            String(usuario.ci ?? '')
                                .toLowerCase()
                                .includes(texto) ||

                            nombreCompleto.includes(texto) ||

                            String(usuario.username ?? '')
                                .toLowerCase()
                                .includes(texto);


                        let coincideEstado = true;


                        if (this.filtroEstado === 'ACTIVOS') {

                            coincideEstado = usuario.activo === true;

                        }

                        if (this.filtroEstado === 'INACTIVOS') {

                            coincideEstado = usuario.activo === false;

                        }


                        return coincideBusqueda && coincideEstado;

                    });

                },


                async inactivarUsuario(usuario) {

                    const nombre =
                        `${usuario.nombres ?? ''} ${usuario.apellidos ?? ''}`;


                    const confirmar =
                        window.confirm(
                            `¿Está seguro de inactivar al usuario ${nombre}?`
                        );


                    if (!confirmar) {
                        return;
                    }


                    try {

                        const respuesta = await window.apiFetch(
                            `/api/usuarios/${usuario.id}/inactivar`,
                            {
                                method: 'POST'
                            }
                        );


                        console.log(
                            'RESPUESTA INACTIVAR:',
                            respuesta
                        );


                        if (!respuesta.ok) {

                            throw new Error(
                                respuesta.data?.message ||
                                'No se pudo inactivar el usuario.'
                            );

                        }


                        alert(
                            respuesta.data?.message ||
                            'Usuario inactivado correctamente.'
                        );


                        await this.cargarUsuarios();


                    } catch (error) {

                        console.error(
                            'ERROR INACTIVAR:',
                            error
                        );

                        alert(error.message);

                    }

                }

            };

        }

    </script>

@endsection