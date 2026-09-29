@extends('layouts.app')

@section('titulo', 'Monitoreo de Expedientes')

@section('contenido')
    <div class="p-6" x-data="monitoreoExpedientes()" x-init="cargar()">

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-5">

            <div>
                <h1 class="text-xl font-bold text-grafito">
                    Monitoreo de expedientes
                </h1>

                <p class="text-sm text-gris">
                    Supervise la asignación, responsables y cumplimiento de plazos.
                </p>
            </div>

            <div class="text-xs text-gris">
                <i class="fa-solid fa-arrows-rotate mr-1"></i>
                Actualización automática
            </div>

        </div>

        <!-- RESUMEN -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">

            <div class="bg-white rounded-xl border border-gris-claro p-4">
                <p class="text-[10px] text-gris uppercase">Total</p>
                <p class="text-xl font-bold text-grafito" x-text="resumen.total"></p>
            </div>

            <div class="bg-white rounded-xl border border-gris-claro p-4">
                <p class="text-[10px] text-gris uppercase">Sin asignar</p>
                <p class="text-xl font-bold text-amber-600" x-text="resumen.sin_asignar"></p>
            </div>

            <div class="bg-white rounded-xl border border-gris-claro p-4">
                <p class="text-[10px] text-gris uppercase">En trámite</p>
                <p class="text-xl font-bold text-verde-profundo" x-text="resumen.en_tramite"></p>
            </div>

            <div class="bg-white rounded-xl border border-gris-claro p-4">
                <p class="text-[10px] text-gris uppercase">Por vencer</p>
                <p class="text-xl font-bold text-amber-600" x-text="resumen.por_vencer"></p>
            </div>

            <div class="bg-white rounded-xl border border-gris-claro p-4">
                <p class="text-[10px] text-gris uppercase">Fuera de plazo</p>
                <p class="text-xl font-bold text-red-600" x-text="resumen.fuera_plazo"></p>
            </div>

        </div>

        <!-- FILTROS -->
        <div class="bg-white rounded-xl border border-gris-claro shadow-sm p-4 mb-5">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">

                <div class="lg:col-span-2">

                    <label class="text-xs font-medium text-grafito">
                        Buscar expediente
                    </label>

                    <div class="relative mt-1">

                        <i class="fa-solid fa-magnifying-glass
                                  absolute left-3 top-3 text-gris"></i>

                        <input type="text" x-model="filtros.buscar" @input.debounce.400ms="cargar()"
                            placeholder="NUREJ o descripción..." class="w-full pl-9 border-gris-claro rounded-lg text-sm">

                    </div>

                </div>

                <div>

                    <label class="text-xs font-medium text-grafito">
                        Responsable
                    </label>

                    <select x-model="filtros.responsable" @change="cargar()"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">

                        <option value="">Todos</option>
                        <option value="TECNICO">Técnicos</option>
                        <option value="AUDITOR">Auditores</option>

                    </select>

                </div>

                <div>

                    <label class="text-xs font-medium text-grafito">
                        Estado
                    </label>

                    <select x-model="filtros.estado" @change="cargar()"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">

                        <option value="">Todos</option>
                        <option value="SIN_ASIGNAR">Sin asignar</option>
                        <option value="EN_TRAMITE">En trámite</option>
                        <option value="POR_VENCER">Por vencer</option>
                        <option value="FUERA_DE_PLAZO">Fuera de plazo</option>

                    </select>

                </div>

                <div>

                    <label class="text-xs font-medium text-grafito">
                        Ordenar
                    </label>

                    <select x-model="filtros.orden" @change="cargar()"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">

                        <option value="plazo">Por plazo</option>
                        <option value="reciente">Más recientes</option>
                        <option value="antiguo">Más antiguos</option>

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

                            <th class="text-left px-4 py-3 text-[11px] font-semibold">
                                Expediente
                            </th>

                            <th class="text-left px-4 py-3 text-[11px] font-semibold">
                                Responsable
                            </th>

                            <th class="text-left px-4 py-3 text-[11px] font-semibold">
                                Designación
                            </th>

                            <th class="text-left px-4 py-3 text-[11px] font-semibold">
                                Plazo
                            </th>

                            <th class="text-left px-4 py-3 text-[11px] font-semibold">
                                Estado
                            </th>

                            <th class="text-right px-4 py-3 text-[11px] font-semibold">
                                Acción
                            </th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gris-claro">

                        <template x-for="exp in expedientes" :key="exp.id">

                            <tr class="hover:bg-gris-claro/30">

                                <!-- EXPEDIENTE -->
                                <td class="px-4 py-4">

                                    <p class="font-semibold text-grafito" x-text="exp.nurej"></p>

                                    <p class="text-xs text-gris max-w-[250px] truncate" x-text="exp.descripcion"></p>

                                    <p class="text-[10px] text-gris mt-1" x-text="'Ingreso: ' + exp.fecha_ingreso">
                                    </p>

                                </td>

                                <!-- RESPONSABLE -->
                                <td class="px-4 py-4">

                                    <template x-if="exp.responsable">

                                        <div>

                                            <div class="flex items-center gap-2">

                                                <div class="w-7 h-7 rounded-full
                                                            bg-verde-institucional/20
                                                            flex items-center justify-center">

                                                    <i :class="exp.responsable.rol === 'AUDITOR'
                                                            ? 'fa-solid fa-user-shield'
                                                            : 'fa-solid fa-user-gear'" class="text-xs text-verde-profundo">
                                                    </i>

                                                </div>

                                                <div>

                                                    <p class="text-xs font-semibold text-grafito"
                                                        x-text="exp.responsable.nombre">
                                                    </p>

                                                    <p class="text-[10px] text-gris" x-text="exp.responsable.rol">
                                                    </p>

                                                </div>

                                            </div>

                                        </div>

                                    </template>

                                    <template x-if="!exp.responsable">

                                        <span class="text-xs text-amber-600 font-medium">
                                            <i class="fa-solid fa-circle-exclamation mr-1"></i>
                                            Sin asignar
                                        </span>

                                    </template>

                                </td>

                                <!-- DESIGNACIÓN -->
                                <td class="px-4 py-4">

                                    <template x-if="exp.designacion">

                                        <div>

                                            <p class="text-xs text-grafito" x-text="exp.designacion.fecha">
                                            </p>

                                            <p class="text-[10px] text-gris">
                                                Designado por:
                                            </p>

                                            <p class="text-xs font-medium text-grafito" x-text="exp.designacion.por">
                                            </p>

                                        </div>

                                    </template>

                                    <template x-if="!exp.designacion">

                                        <span class="text-xs text-gris">
                                            Pendiente
                                        </span>

                                    </template>

                                </td>

                                <!-- PLAZO -->
                                <td class="px-4 py-4 min-w-[180px]">

                                    <div x-show="exp.plazo">

                                        <div class="flex justify-between text-[10px] mb-1">

                                            <span class="text-gris">
                                                <span x-text="exp.plazo.transcurridos"></span>
                                                días transcurridos
                                            </span>

                                            <span class="font-semibold" :class="{
                                                      'text-verde-profundo': exp.plazo.dias_restantes > 5,
                                                      'text-amber-600': exp.plazo.dias_restantes <= 5 && exp.plazo.dias_restantes > 0,
                                                      'text-red-600': exp.plazo.dias_restantes <= 0
                                                  }">

                                                <template x-if="exp.plazo.dias_restantes > 0">
                                                    <span x-text="exp.plazo.dias_restantes + ' días restantes'"></span>
                                                </template>

                                                <template x-if="exp.plazo.dias_restantes <= 0">
                                                    <span>Vencido</span>
                                                </template>

                                            </span>

                                        </div>

                                        <div class="h-2 bg-gris-claro rounded-full overflow-hidden">

                                            <div class="h-full rounded-full" :class="{
                                                    'bg-verde-profundo': exp.plazo.dias_restantes > 5,
                                                    'bg-amber-400': exp.plazo.dias_restantes <= 5 && exp.plazo.dias_restantes > 0,
                                                    'bg-red-500': exp.plazo.dias_restantes <= 0
                                                }" :style="'width:' + exp.plazo.porcentaje + '%'">
                                            </div>

                                        </div>

                                        <p class="text-[10px] text-gris mt-1" x-text="'Límite: ' + exp.plazo.fecha_limite">
                                        </p>

                                    </div>

                                    <span x-show="!exp.plazo" class="text-xs text-gris">
                                        Sin plazo
                                    </span>

                                </td>

                                <!-- ESTADO -->
                                <td class="px-4 py-4">

                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold" :class="{
                                            'bg-gris-claro text-gris':
                                                exp.estado === 'SIN_ASIGNAR',

                                            'bg-[#8CC63F]/20 text-[#3F5E1B]':
                                                exp.estado === 'EN_TRAMITE',

                                            'bg-amber-100 text-amber-700':
                                                exp.estado === 'POR_VENCER',

                                            'bg-red-100 text-red-700':
                                                exp.estado === 'FUERA_DE_PLAZO'
                                        }" x-text="textoEstado(exp.estado)">
                                    </span>

                                </td>

                                <!-- ACCIÓN -->
                                <td class="px-4 py-4 text-right">

                                    <a :href="'/expedientes/' + exp.id" class="inline-flex items-center justify-center
                                               w-8 h-8 rounded-lg border border-gris-claro
                                               hover:bg-gris-claro">

                                        <i class="fa-solid fa-eye text-xs text-grafito"></i>

                                    </a>

                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>

            <!-- SIN RESULTADOS -->
            <div x-show="expedientes.length === 0" class="p-10 text-center text-gris">

                <i class="fa-solid fa-folder-open text-3xl mb-2"></i>

                <p class="text-sm">
                    No se encontraron expedientes.
                </p>

            </div>

        </div>

    </div>

    <script>

        function monitoreoExpedientes() {

            return {

                expedientes: [],

                resumen: {
                    total: 0,
                    sin_asignar: 0,
                    en_tramite: 0,
                    por_vencer: 0,
                    fuera_plazo: 0
                },

                filtros: {
                    buscar: '',
                    responsable: '',
                    estado: '',
                    orden: 'plazo'
                },

                cargando: true,

                error: '',


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


                        if (this.filtros.responsable) {

                            params.set(
                                'responsable',
                                this.filtros.responsable
                            );

                        }


                        if (this.filtros.estado) {

                            params.set(
                                'estado',
                                this.filtros.estado
                            );

                        }


                        if (this.filtros.orden) {

                            params.set(
                                'orden',
                                this.filtros.orden
                            );

                        }


                        const url =
                            `/api/admin/monitoreo?${params.toString()}`;


                        const respuesta =
                            await window.apiFetch(url);


                        console.log(
                            'RESPUESTA MONITOREO:',
                            respuesta
                        );


                        if (!respuesta.ok) {

                            throw new Error(
                                respuesta.data?.message ||
                                'No se pudo cargar el monitoreo.'
                            );

                        }


                        this.expedientes =
                            respuesta.data?.data ?? [];


                        this.resumen =
                            respuesta.data?.resumen ?? {
                                total: 0,
                                sin_asignar: 0,
                                en_tramite: 0,
                                por_vencer: 0,
                                fuera_plazo: 0
                            };


                    } catch (error) {

                        console.error(
                            'ERROR MONITOREO:',
                            error
                        );

                        this.error =
                            error.message ||
                            'Ocurrió un error al cargar los expedientes.';

                        this.expedientes = [];

                    } finally {

                        this.cargando = false;

                    }

                },


                textoEstado(estado) {

                    const estados = {

                        SIN_ASIGNAR:
                            'Sin asignar',

                        EN_TRAMITE:
                            'En trámite',

                        POR_VENCER:
                            'Por vencer',

                        FUERA_DE_PLAZO:
                            'Fuera de plazo'

                    };

                    return estados[estado] || estado;

                },


                claseSemaforo(plazo) {

                    if (!plazo) {
                        return 'bg-gris-claro';
                    }

                    switch (plazo.codigo_color) {

                        case 'VERDE':
                            return 'bg-verde-profundo';

                        case 'AMARILLO':
                            return 'bg-amber-400';

                        case 'ROJO':
                        case 'FUERA_DE_PLAZO':
                            return 'bg-red-500';

                        default:
                            return 'bg-gris';

                    }

                }

            };

        }

    </script>
@endsection