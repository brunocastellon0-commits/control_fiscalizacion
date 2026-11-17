@extends('layouts.app')

@section('titulo', 'Panel financiero')

@section('contenido')
<div class="p-6 space-y-6" x-data="financieroDashboard()" x-init="cargar()">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold text-grafito">Panel de auditoría financiera</h1>
            <p class="text-gris">Seguimiento de descargos y plazos del Acuerdo 055.</p>
        </div>
        <button @click="cargar()" class="text-sm text-verde-profundo hover:underline flex items-center gap-1.5">
            <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': cargando }"></i> Actualizar
        </button>
    </div>

    <template x-if="cargando && !datosListos">
        <div class="bg-white rounded-xl shadow p-6 text-center">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-verde-profundo"></i>
            <p class="mt-2 text-gris">Cargando información financiera...</p>
        </div>
    </template>

    <template x-if="error">
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <i class="fa-solid fa-circle-info mr-2"></i>
                <span x-text="error"></span>
            </div>
            <button type="button" @click="cargar()" class="text-sm font-medium underline">Reintentar</button>
        </div>
    </template>

    <template x-if="datosListos">
        <div class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow p-5">
                    <p class="text-xs text-gris uppercase">Descargos comunicados</p>
                    <p class="text-2xl font-bold text-grafito mt-1" x-text="datos.descargos.comunicados"></p>
                    <p class="text-[11px] text-gris mt-0.5">notificaciones enviadas</p>
                </div>
                <div class="bg-white rounded-xl shadow p-5">
                    <p class="text-xs text-gris uppercase">Descargos recepcionados</p>
                    <p class="text-2xl font-bold text-verde-profundo mt-1" x-text="datos.descargos.recepcionados"></p>
                    <p class="text-[11px] text-gris mt-0.5">respuestas recibidas</p>
                </div>
                <div class="bg-white rounded-xl shadow p-5">
                    <p class="text-xs text-gris uppercase">En plazo de aclaración</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1" x-text="datos.plazos.en_aclaracion"></p>
                    <p class="text-[11px] text-gris mt-0.5">hasta 5 días hábiles (AC055)</p>
                </div>
                <div class="bg-white rounded-xl shadow p-5">
                    <p class="text-xs text-gris uppercase">Pendientes de informe final</p>
                    <p class="text-2xl font-bold mt-1" :class="datos.informes.pendientes > 0 ? 'text-red-600' : 'text-grafito'" x-text="datos.informes.pendientes"></p>
                    <p class="text-[11px] text-gris mt-0.5">requieren emisión</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Causas en riesgo de vencimiento</h2>
                    <div class="divide-y divide-gris-claro">
                        <template x-if="(datos.riesgo_vencimiento || []).length === 0">
                            <p class="text-sm text-gris py-3">No hay causas financieras en riesgo por ahora.</p>
                        </template>
                        <template x-for="item in datos.riesgo_vencimiento" :key="item.expediente_id || item.id || item.nurej_code">
                            <a :href="`/expedientes/${item.expediente_id || item.id}`" class="flex items-center justify-between py-2.5 text-sm hover:bg-gris-claro/50 -mx-2 px-2 rounded">
                                <div>
                                    <p class="font-medium text-grafito" x-text="item.nurej_code || 'Expediente'"></p>
                                    <p class="text-xs text-gris" x-text="item.etapa || item.estado || 'Seguimiento financiero'"></p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold text-white" :class="badgeRiesgo(item)" x-text="item.semaforo?.codigo_color || item.nivel_riesgo || 'RIESGO'"></span>
                                    <p class="text-[11px] text-gris mt-1" x-text="item.dias_restantes != null ? (item.dias_restantes + ' día(s)') : (item.fecha_limite || '')"></p>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5">
                    <h2 class="font-semibold text-grafito mb-3">Descargos y aclaraciones</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gris-claro/40">
                            <span class="text-grafito">En plazo de aclaración (5 días hábiles)</span>
                            <span class="font-semibold text-amber-700" x-text="datos.plazos.en_aclaracion"></span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gris-claro/40">
                            <span class="text-grafito">Causas con riesgo de vencimiento</span>
                            <span class="font-semibold text-red-700" x-text="(datos.riesgo_vencimiento || []).length"></span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gris-claro/40">
                            <span class="text-grafito">Expedientes pendientes de informe final</span>
                            <span class="font-semibold text-grafito" x-text="datos.informes.pendientes"></span>
                        </div>
                        <p class="text-xs text-gris pt-2">Los datos mostrados corresponden a la información disponible para su rol. Si algún indicador no está disponible, se mostrará en cero.</p>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    function financieroDashboard() {
        return {
            cargando: false,
            datosListos: false,
            error: null,
            datos: {
                descargos: { comunicados: 0, recepcionados: 0 },
                plazos: { en_aclaracion: 0 },
                informes: { pendientes: 0 },
                riesgo_vencimiento: [],
            },
            async cargar() {
                this.cargando = true;
                this.error = null;
                try {
                    const endpoints = [
                        '/api/financiero/dashboard',
                        '/api/auditor-financiero/dashboard',
                        '/api/encargada/dashboard',
                    ];
                    let payload = null;
                    for (const url of endpoints) {
                        try {
                            const { ok, data } = await window.apiFetch(url);
                            if (ok) { payload = data?.data || data; break; }
                        } catch (e) {}
                    }

                    if (payload) {
                        this.datos = this.normalizar(payload);
                        this.datosListos = true;
                    } else {
                        this.datos = this.normalizar({});
                        this.datosListos = true;
                        this.error = 'No se pudo obtener el resumen financiero detallado. Se muestran indicadores base hasta que el servicio esté disponible.';
                    }
                } catch (e) {
                    this.datos = this.normalizar({});
                    this.datosListos = true;
                    this.error = e.message || 'No se pudo cargar el panel financiero.';
                } finally {
                    this.cargando = false;
                }
            },
            normalizar(p) {
                const desc = p.descargos || p.descargo || {};
                const plazos = p.plazos || {};
                const informes = p.informes || {};
                const riesgo = p.riesgo_vencimiento || p.causas_en_riesgo || p.vencimientos?.proximos || p.vencimientos?.fuera_de_plazo || [];
                return {
                    descargos: {
                        comunicados: Number(desc.comunicados ?? desc.total_comunicados ?? 0),
                        recepcionados: Number(desc.recepcionados ?? desc.total_recepcionados ?? 0),
                    },
                    plazos: {
                        en_aclaracion: Number(plazos.en_aclaracion ?? plazos.aclaracion ?? p.en_plazo_aclaracion ?? 0),
                    },
                    informes: {
                        pendientes: Number(informes.pendientes ?? informes.pendientes_informe_final ?? p.pendientes_informe_final ?? 0),
                    },
                    riesgo_vencimiento: Array.isArray(riesgo) ? riesgo : [],
                };
            },
            badgeRiesgo(item) {
                const color = item.semaforo?.codigo_color || item.nivel_riesgo || '';
                const map = { VERDE: 'bg-[#285C3A]', AMARILLO: 'bg-amber-500', ROJO: 'bg-red-500', FUERA_DE_PLAZO: 'bg-grafito' };
                return map[color] || 'bg-red-500';
            }
        };
    }
</script>
@endsection
