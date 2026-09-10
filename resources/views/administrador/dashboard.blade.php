@extends('layouts.app')

@section('titulo', 'Dashboard Administrativo')

@section('contenido')
<div class="p-6 space-y-6" x-data="adminDashboard()" x-init="cargarDashboard()">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold text-grafito">Dashboard Administrativo</h1>
            <p class="text-gris">Resumen general del sistema de control y fiscalización.</p>
        </div>
        <button @click="cargarDashboard" class="text-sm text-verde-profundo hover:underline flex items-center gap-1.5">
            <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': cargando }"></i> Actualizar
        </button>
    </div>

    <template x-if="cargando && !datosListos">
        <div class="bg-white rounded-xl shadow p-6 text-center">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-verde-profundo"></i>
            <p class="mt-2 text-gray-500">Cargando información...</p>
        </div>
    </template>

    <template x-if="error">
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>
            <span x-text="error"></span>
        </div>
    </template>

    <template x-if="datosListos">
        <div class="space-y-6">

            <!-- ALERTA: expedientes fuera de plazo -->
            <div x-show="datos.expedientes_fuera_de_plazo.length > 0"
                 class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-semibold text-red-700 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span x-text="datos.expedientes_fuera_de_plazo.length + ' expediente(s) fuera de plazo'"></span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="exp in datos.expedientes_fuera_de_plazo" :key="exp.id">
                        <a :href="`/expedientes/${exp.id}`"
                           class="text-xs bg-white border border-red-200 text-red-700 rounded-full px-3 py-1 hover:bg-red-100">
                            <span x-text="exp.nurej_code"></span> — <span x-text="exp.asignado_a"></span>
                        </a>
                    </template>
                </div>
            </div>

            <!-- KPI CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gris uppercase">Usuarios</p>
                            <p class="text-2xl font-bold text-grafito mt-1" x-text="datos.usuarios.total"></p>
                            <p class="text-[11px] text-gris mt-0.5">
                                <span class="text-verde-profundo font-medium" x-text="datos.usuarios.activos"></span> activos ·
                                <span class="text-red-500 font-medium" x-text="datos.usuarios.inactivos"></span> inactivos
                            </p>
                        </div>
                        <div class="w-11 h-11 rounded-full bg-verde-claro flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-users text-verde-profundo"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gris uppercase">Expedientes</p>
                            <p class="text-2xl font-bold text-grafito mt-1" x-text="datos.expedientes.total"></p>
                            <p class="text-[11px] text-gris mt-0.5">totales en el sistema</p>
                        </div>
                        <div class="w-11 h-11 rounded-full bg-verde-claro flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-folder-open text-verde-profundo"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gris uppercase">Sin asignar</p>
                            <p class="text-2xl font-bold mt-1" :class="datos.expedientes.sin_asignar > 0 ? 'text-amber-600' : 'text-grafito'"
                               x-text="datos.expedientes.sin_asignar"></p>
                            <p class="text-[11px] text-gris mt-0.5">pendientes de sorteo/asignación</p>
                        </div>
                        <div class="w-11 h-11 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-user-clock text-amber-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gris uppercase">Asignaciones activas</p>
                            <p class="text-2xl font-bold text-grafito mt-1" x-text="datos.asignaciones.activas"></p>
                            <p class="text-[11px] text-gris mt-0.5">expedientes en trámite</p>
                        </div>
                        <div class="w-11 h-11 rounded-full bg-verde-claro flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-user-tag text-verde-profundo"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gris uppercase">Accesos fallidos (24h)</p>
                            <p class="text-2xl font-bold mt-1" :class="datos.seguridad.intentos_fallidos_24h > 0 ? 'text-red-600' : 'text-grafito'"
                               x-text="datos.seguridad.intentos_fallidos_24h"></p>
                            <p class="text-[11px] text-gris mt-0.5">intentos de login rechazados</p>
                        </div>
                        <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-shield-halved text-red-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEMAFORO GLOBAL -->
            <div class="bg-white rounded-xl shadow p-5">
                <h2 class="font-semibold text-grafito mb-3">Semáforo de plazos (todos los expedientes)</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-lg p-3 bg-[#285C3A]/10">
                        <p class="text-[11px] uppercase text-[#285C3A] font-medium">En plazo</p>
                        <p class="text-xl font-bold text-[#285C3A]" x-text="datos.semaforo.total_en_plazo"></p>
                    </div>
                    <div class="rounded-lg p-3 bg-amber-50">
                        <p class="text-[11px] uppercase text-amber-600 font-medium">Precaución</p>
                        <p class="text-xl font-bold text-amber-600" x-text="datos.semaforo.total_precaucion"></p>
                    </div>
                    <div class="rounded-lg p-3 bg-red-50">
                        <p class="text-[11px] uppercase text-red-500 font-medium">Urgente</p>
                        <p class="text-xl font-bold text-red-500" x-text="datos.semaforo.total_urgente"></p>
                    </div>
                    <div class="rounded-lg p-3 bg-grafito/10">
                        <p class="text-[11px] uppercase text-grafito font-medium">Fuera de plazo</p>
                        <p class="text-xl font-bold text-grafito" x-text="datos.semaforo.total_fuera_de_plazo"></p>
                    </div>
                </div>
                <!-- barra proporcional -->
                <div class="mt-4 h-3 w-full rounded-full overflow-hidden flex bg-gris-claro">
                    <div class="h-full bg-[#285C3A]" :style="`width: ${pct(datos.semaforo.total_en_plazo)}%`"></div>
                    <div class="h-full bg-amber-400" :style="`width: ${pct(datos.semaforo.total_precaucion)}%`"></div>
                    <div class="h-full bg-red-500" :style="`width: ${pct(datos.semaforo.total_urgente)}%`"></div>
                    <div class="h-full bg-grafito" :style="`width: ${pct(datos.semaforo.total_fuera_de_plazo)}%`"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- USUARIOS POR ROL -->
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Usuarios por rol</h2>
                    <div class="space-y-2.5">
                        <template x-for="rol in datos.usuarios.por_rol" :key="rol.codigo">
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-grafito font-medium" x-text="rol.nombre"></span>
                                    <span class="text-gris" x-text="rol.activos + ' / ' + rol.total"></span>
                                </div>
                                <div class="h-2 w-full bg-gris-claro rounded-full overflow-hidden">
                                    <div class="h-full bg-verde-profundo rounded-full"
                                         :style="`width: ${pct(rol.activos, maxRolTotal)}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- EXPEDIENTES POR ESTADO -->
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Expedientes por estado</h2>
                    <div class="space-y-2.5">
                        <template x-for="estado in datos.expedientes.por_estado" :key="estado.codigo">
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-grafito font-medium" x-text="estado.nombre"></span>
                                    <span class="text-gris" x-text="estado.total"></span>
                                </div>
                                <div class="h-2 w-full bg-gris-claro rounded-full overflow-hidden">
                                    <div class="h-full bg-azul-petroleo rounded-full"
                                         :style="`width: ${pct(estado.total, maxEstadoTotal)}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- ULTIMOS EXPEDIENTES -->
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Últimos expedientes ingresados</h2>
                    <div class="divide-y divide-gris-claro">
                        <template x-for="exp in datos.expedientes.ultimos" :key="exp.id">
                            <a :href="`/expedientes/${exp.id}`" class="flex items-center justify-between py-2.5 text-sm hover:bg-gris-claro/50 -mx-2 px-2 rounded">
                                <div>
                                    <p class="font-medium text-grafito" x-text="exp.nurej_code"></p>
                                    <p class="text-xs text-gris" x-text="exp.creador ? ('Creado por ' + exp.creador) : ''"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gris" x-text="exp.estado"></p>
                                    <p class="text-[11px] text-gris" x-text="exp.fecha_ingreso"></p>
                                </div>
                            </a>
                        </template>
                        <p x-show="datos.expedientes.ultimos.length === 0" class="text-sm text-gris py-3">Sin expedientes registrados.</p>
                    </div>
                </div>

                <!-- SESIONES RECIENTES -->
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Accesos recientes</h2>
                    <div class="divide-y divide-gris-claro">
                        <template x-for="(sesion, idx) in datos.seguridad.sesiones_recientes" :key="idx">
                            <div class="flex items-center justify-between py-2.5 text-sm">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid" :class="sesion.exitoso ? 'fa-circle-check text-verde-profundo' : 'fa-circle-xmark text-red-500'"></i>
                                    <div>
                                        <p class="font-medium text-grafito" x-text="sesion.usuario"></p>
                                        <p class="text-[11px] text-gris" x-text="sesion.ip_origen"></p>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gris" x-text="sesion.login_at"></p>
                            </div>
                        </template>
                        <p x-show="datos.seguridad.sesiones_recientes.length === 0" class="text-sm text-gris py-3">Sin accesos registrados.</p>
                    </div>
                </div>

            </div>

            <!-- FERIADOS PROXIMOS -->
            <div class="bg-white rounded-xl shadow p-5" x-show="datos.feriados_proximos.length > 0">
                <h2 class="font-semibold text-grafito mb-3">Próximos feriados (afectan el cálculo de plazos)</h2>
                <div class="flex flex-wrap gap-3">
                    <template x-for="(feriado, idx) in datos.feriados_proximos" :key="idx">
                        <div class="bg-gris-claro rounded-lg px-3 py-2 text-xs">
                            <p class="font-medium text-grafito" x-text="feriado.fecha"></p>
                            <p class="text-gris" x-text="feriado.descripcion"></p>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </template>
</div>

<script>
    function adminDashboard() {
        return {
            cargando: true,
            datosListos: false,
            error: null,
            datos: {
                usuarios: { total: 0, activos: 0, inactivos: 0, por_rol: [] },
                expedientes: { total: 0, sin_asignar: 0, por_estado: [], por_via: [], ultimos: [] },
                asignaciones: { activas: 0 },
                semaforo: { total_en_plazo: 0, total_precaucion: 0, total_urgente: 0, total_fuera_de_plazo: 0 },
                expedientes_fuera_de_plazo: [],
                seguridad: { sesiones_recientes: [], intentos_fallidos_24h: 0 },
                feriados_proximos: [],
            },

            get maxRolTotal() {
                return Math.max(1, ...this.datos.usuarios.por_rol.map(r => r.activos));
            },
            get maxEstadoTotal() {
                return Math.max(1, ...this.datos.expedientes.por_estado.map(e => e.total));
            },

            pct(valor, max = null) {
                const total = max ?? (
                    this.datos.semaforo.total_en_plazo +
                    this.datos.semaforo.total_precaucion +
                    this.datos.semaforo.total_urgente +
                    this.datos.semaforo.total_fuera_de_plazo
                );
                if (!total) return 0;
                return Math.min(100, Math.round((valor / total) * 100));
            },

            async cargarDashboard() {
                this.cargando = true;
                this.error = null;
                try {
                    const { ok, data } = await window.apiFetch('/api/admin/dashboard');
                    if (!ok) {
                        throw new Error(data.message || 'No se pudo cargar el dashboard.');
                    }
                    this.datos = data;
                    this.datosListos = true;
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.cargando = false;
                }
            },
        };
    }
</script>
@endsection