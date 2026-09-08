@extends('layouts.app')

@section('titulo', 'Dashboard Administrativo')

@section('contenido')

    <div x-data="adminDashboard()" x-init="cargarDashboard()" class="space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-grafito">
                Dashboard Administrativo
            </h1>

            <p class="text-gray-500">
                Resumen general del sistema.
            </p>
        </div>


        {{-- CARGANDO --}}

        <template x-if="cargando">
            <div class="bg-white rounded-xl shadow p-6 text-center">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-verde-profundo"></i>

                <p class="mt-2 text-gray-500">
                    Cargando información...
                </p>
            </div>
        </template>


        {{-- ERROR --}}

        <template x-if="error">
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
                <i class="fa-solid fa-circle-exclamation mr-2"></i>

                <span x-text="error"></span>
            </div>
        </template>


        {{-- DASHBOARD --}}

        <template x-if="!cargando && !error">

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">


                {{-- USUARIOS --}}

                <div class="bg-white rounded-xl shadow p-6">

                    <div class="flex items-center justify-between">

                        <div>
                            <p class="text-sm text-gray-500">
                                Usuarios registrados
                            </p>

                            <p class="text-3xl font-bold text-grafito mt-2" x-text="datos.usuarios.total"></p>
                        </div>

                        <div class="w-12 h-12 rounded-full bg-verde-claro flex items-center justify-center">

                            <i class="fa-solid fa-users text-verde-profundo text-xl"></i>

                        </div>

                    </div>

                </div>


                {{-- ACTIVOS --}}

                <div class="bg-white rounded-xl shadow p-6">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Usuarios activos
                            </p>

                            <p class="text-3xl font-bold text-grafito mt-2" x-text="datos.usuarios.activos"></p>

                        </div>

                        <div class="w-12 h-12 rounded-full bg-verde-claro flex items-center justify-center">

                            <i class="fa-solid fa-user-check text-verde-profundo text-xl"></i>

                        </div>

                    </div>

                </div>


                {{-- EXPEDIENTES --}}

                <div class="bg-white rounded-xl shadow p-6">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Expedientes
                            </p>

                            <p class="text-3xl font-bold text-grafito mt-2" x-text="datos.expedientes.total"></p>

                        </div>

                        <div class="w-12 h-12 rounded-full bg-verde-claro flex items-center justify-center">

                            <i class="fa-solid fa-folder-open text-verde-profundo text-xl"></i>

                        </div>

                    </div>

                </div>


                {{-- ASIGNACIONES --}}

                <div class="bg-white rounded-xl shadow p-6">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Asignaciones activas
                            </p>

                            <p class="text-3xl font-bold text-grafito mt-2" x-text="datos.asignaciones.activas"></p>

                        </div>

                        <div class="w-12 h-12 rounded-full bg-verde-claro flex items-center justify-center">

                            <i class="fa-solid fa-user-tag text-verde-profundo text-xl"></i>

                        </div>

                    </div>

                </div>

            </div>

        </template>

    </div>


    <script>

        function adminDashboard() {

            return {

                cargando: true,

                error: null,

                datos: {

                    usuarios: {
                        total: 0,
                        activos: 0,
                        inactivos: 0
                    },

                    expedientes: {
                        total: 0
                    },

                    asignaciones: {
                        activas: 0
                    },

                    usuarios_por_rol: {}

                },


                async cargarDashboard() {

                    try {

                        const respuesta = await window.apiFetch(
                            '/api/admin/dashboard'
                        );

                        console.log('RESPUESTA DASHBOARD:', respuesta);

                        this.datos = respuesta.data;

                    } catch (error) {

                        console.error('ERROR DASHBOARD:', error);

                        this.error = error.message;

                    } finally {

                        this.cargando = false;

                    }

                }

            }

        }

    </script>

@endsection