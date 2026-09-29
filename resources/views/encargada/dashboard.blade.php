@extends('layouts.app')

@section('titulo', 'Dashboard Encargada')

@section('contenido')
<div class="p-6 space-y-6" x-data="encargadaDashboard()" x-init="cargarDashboard()">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold text-grafito">Dashboard de Encargada</h1>
            <p class="text-gris">Seguimiento operativo de expedientes, asignaciones y plazos.</p>
        </div>
        <button @click="cargarDashboard" class="text-sm text-verde-profundo hover:underline flex items-center gap-1.5">
            <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': cargando }"></i> Actualizar
        </button>
    </div>

    <template x-if="cargando && !datosListos">
        <div class="bg-white rounded-xl shadow p-6 text-center">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-verde-profundo"></i>
            <p class="mt-2 text-gris">Cargando información...</p>
        </div>
    </template>

    <template x-if="error">
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
            <i class="fa-solid fa-circle-exclamation mr-2"></i><span x-text="error"></span>
        </div>
    </template>

    <template x-if="datosListos">
        <div class="space-y-6">
            <div x-show="datos.vencimientos.fuera_de_plazo.length > 0"
                class="bg-red-50 border border-red-200 rounded-xl p-4">
                <p class="font-semibold text-red-700 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span x-text="datos.vencimientos.fuera_de_plazo.length + ' vencimiento(s) fuera de plazo'"></span>
                </p>
                <div class="flex flex-wrap gap-2 mt-2">
                    <template x-for="vencimiento in datos.vencimientos.fuera_de_plazo" :key="vencimiento.id">
                        <a :href="`/expedientes/${vencimiento.expediente_id}`"
                            class="text-xs bg-white border border-red-200 text-red-700 rounded-full px-3 py-1 hover:bg-red-100">
                            <span x-text="vencimiento.nurej_code"></span> — <span x-text="vencimiento.asignado_a"></span>
                        </a>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gris uppercase">Expedientes</p>
                        <p class="text-2xl font-bold text-grafito mt-1" x-text="datos.expedientes.total"></p>
                        <p class="text-[11px] text-gris mt-0.5">en la unidad</p>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-verde-claro flex items-center justify-center">
                        <i class="fa-solid fa-folder-open text-verde-profundo"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gris uppercase">Pendientes de sorteo</p>
                        <p class="text-2xl font-bold mt-1 text-amber-600" x-text="datos.expedientes.pendientes_sorteo"></p>
                        <p class="text-[11px] text-gris mt-0.5">requieren asignación</p>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-amber-100 flex items-center justify-center">
                        <i class="fa-solid fa-shuffle text-amber-600"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gris uppercase">Asignaciones activas</p>
                        <p class="text-2xl font-bold text-grafito mt-1" x-text="datos.asignaciones.activas"></p>
                        <p class="text-[11px] text-gris mt-0.5">expedientes en trámite</p>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-verde-claro flex items-center justify-center">
                        <i class="fa-solid fa-user-tag text-verde-profundo"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gris uppercase">Fuera de plazo</p>
                        <p class="text-2xl font-bold mt-1 text-red-600" x-text="datos.semaforo.total_fuera_de_plazo"></p>
                        <p class="text-[11px] text-gris mt-0.5">requieren atención</p>
                    </div>
                    <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-5">
                <h2 class="font-semibold text-grafito mb-3">Semáforo de plazos</h2>
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
                <div class="mt-4 h-3 w-full rounded-full overflow-hidden flex bg-gris-claro">
                    <div class="h-full bg-[#285C3A]" :style="`width: ${pct(datos.semaforo.total_en_plazo)}%`"></div>
                    <div class="h-full bg-amber-400" :style="`width: ${pct(datos.semaforo.total_precaucion)}%`"></div>
                    <div class="h-full bg-red-500" :style="`width: ${pct(datos.semaforo.total_urgente)}%`"></div>
                    <div class="h-full bg-grafito" :style="`width: ${pct(datos.semaforo.total_fuera_de_plazo)}%`"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                                    <div class="h-full bg-azul-petroleo rounded-full" :style="`width: ${pct(estado.total, maxEstadoTotal)}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Carga por operador</h2>
                    <div class="space-y-2.5">
                        <template x-for="operador in datos.asignaciones.carga_operadores" :key="operador.usuario_id">
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-grafito font-medium" x-text="operador.nombre"></span>
                                    <span class="text-gris" x-text="operador.total + (operador.rol ? ' · ' + operador.rol : '')"></span>
                                </div>
                                <div class="h-2 w-full bg-gris-claro rounded-full overflow-hidden">
                                    <div class="h-full bg-verde-profundo rounded-full" :style="`width: ${pct(operador.total, maxCargaTotal)}%`"></div>
                                </div>
                            </div>
                        </template>
                        <p x-show="datos.asignaciones.carga_operadores.length === 0" class="text-sm text-gris py-3">No hay asignaciones activas.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Próximos vencimientos</h2>
                    <div class="divide-y divide-gris-claro">
                        <template x-for="vencimiento in datos.vencimientos.proximos" :key="vencimiento.id">
                            <a :href="`/expedientes/${vencimiento.expediente_id}`" class="flex items-center justify-between py-2.5 text-sm hover:bg-gris-claro/50 -mx-2 px-2 rounded">
                                <div><p class="font-medium text-grafito" x-text="vencimiento.nurej_code"></p><p class="text-xs text-gris" x-text="vencimiento.asignado_a"></p></div>
                                <div class="text-right"><p class="text-xs text-gris" x-text="vencimiento.tipo_plazo"></p><p class="text-[11px] text-gris" x-text="vencimiento.fecha_limite"></p></div>
                            </a>
                        </template>
                        <p x-show="datos.vencimientos.proximos.length === 0" class="text-sm text-gris py-3">No hay vencimientos próximos.</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Últimos expedientes ingresados</h2>
                    <div class="divide-y divide-gris-claro">
                        <template x-for="expediente in datos.expedientes.ultimos" :key="expediente.id">
                            <a :href="`/expedientes/${expediente.id}`" class="flex items-center justify-between py-2.5 text-sm hover:bg-gris-claro/50 -mx-2 px-2 rounded">
                                <div><p class="font-medium text-grafito" x-text="expediente.nurej_code"></p><p class="text-xs text-gris" x-text="expediente.creador || 'Sin creador registrado'"></p></div>
                                <div class="text-right"><p class="text-xs text-gris" x-text="expediente.estado"></p><p class="text-[11px] text-gris" x-text="expediente.fecha_ingreso"></p></div>
                            </a>
                        </template>
                        <p x-show="datos.expedientes.ultimos.length === 0" class="text-sm text-gris py-3">Sin expedientes registrados.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Expedientes por vía</h2>
                    <div class="space-y-2.5">
                        <template x-for="via in datos.expedientes.por_via" :key="via.via">
                            <div><div class="flex justify-between text-xs mb-1"><span class="text-grafito font-medium" x-text="via.via"></span><span class="text-gris" x-text="via.total"></span></div><div class="h-2 w-full bg-gris-claro rounded-full overflow-hidden"><div class="h-full bg-verde-profundo rounded-full" :style="`width: ${pct(via.total, maxViaTotal)}%`"></div></div></div>
                        </template>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow p-5" x-show="datos.feriados_proximos.length > 0">
                    <h2 class="font-semibold text-grafito mb-3">Próximos feriados</h2>
                    <div class="flex flex-wrap gap-3">
                        <template x-for="feriado in datos.feriados_proximos" :key="feriado.fecha">
                            <div class="bg-gris-claro rounded-lg px-3 py-2 text-xs"><p class="font-medium text-grafito" x-text="feriado.fecha"></p><p class="text-gris" x-text="feriado.descripcion"></p></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function encargadaDashboard() {
    return {
        cargando: true,
        datosListos: false,
        error: null,
        datos: {
            expedientes: { total: 0, pendientes_sorteo: 0, sin_asignar: 0, por_estado: [], por_via: [], ultimos: [] },
            asignaciones: { activas: 0, carga_operadores: [] },
            semaforo: { total_en_plazo: 0, total_precaucion: 0, total_urgente: 0, total_fuera_de_plazo: 0 },
            vencimientos: { fuera_de_plazo: [], proximos: [] },
            feriados_proximos: [],
        },
        get maxEstadoTotal() { return Math.max(1, ...this.datos.expedientes.por_estado.map(e => e.total)); },
        get maxViaTotal() { return Math.max(1, ...this.datos.expedientes.por_via.map(v => v.total)); },
        get maxCargaTotal() { return Math.max(1, ...this.datos.asignaciones.carga_operadores.map(u => u.total)); },
        pct(valor, max = null) {
            const total = max ?? (this.datos.semaforo.total_en_plazo + this.datos.semaforo.total_precaucion + this.datos.semaforo.total_urgente + this.datos.semaforo.total_fuera_de_plazo);
            return total ? Math.min(100, Math.round((valor / total) * 100)) : 0;
        },
        async cargarDashboard() {
            this.cargando = true;
            this.error = null;
            try {
                const { ok, data } = await window.apiFetch('/api/encargada/dashboard');
                if (!ok) throw new Error(data.message || 'No se pudo cargar el dashboard.');
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
