@extends('layouts.app')

@section('titulo', 'Parámetros del Sistema')

@section('contenido')
<div class="p-6" x-data="parametrosSistema()" x-init="cargar()">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-grafito">
            Parámetros del sistema
        </h1>

        <p class="text-sm text-gris">
            Configure las reglas generales utilizadas por el sistema.
        </p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <!-- PLAZOS -->
        <div class="bg-white rounded-xl border border-gris-claro shadow-sm">

            <div class="p-5 border-b border-gris-claro">

                <div class="flex items-center gap-3">

                    <div class="w-10 h-10 rounded-lg bg-verde-institucional/20
                                flex items-center justify-center">

                        <i class="fa-solid fa-clock text-verde-profundo"></i>

                    </div>

                    <div>
                        <h2 class="font-semibold text-grafito">
                            Plazos
                        </h2>

                        <p class="text-xs text-gris">
                            Configuración de tiempos de atención.
                        </p>
                    </div>

                </div>

            </div>

            <div class="p-5 space-y-4">

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Días para alerta amarilla
                    </label>

                    <input
                        type="number"
                        min="1"
                        x-model="form.dias_alerta_amarilla"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Días para alerta roja
                    </label>

                    <input
                        type="number"
                        min="1"
                        x-model="form.dias_alerta_roja"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                </div>

                <div>
                    <label class="text-xs font-medium text-grafito">
                        Días para considerar fuera de plazo
                    </label>

                    <input
                        type="number"
                        min="1"
                        x-model="form.dias_fuera_plazo"
                        class="w-full mt-1 border-gris-claro rounded-lg text-sm">
                </div>

            </div>

        </div>

        <!-- DÍAS HÁBILES -->
        <div class="bg-white rounded-xl border border-gris-claro shadow-sm">

            <div class="p-5 border-b border-gris-claro">

                <div class="flex items-center gap-3">

                    <div class="w-10 h-10 rounded-lg bg-azul-petroleo/10
                                flex items-center justify-center">

                        <i class="fa-solid fa-calendar-days text-azul-petroleo"></i>

                    </div>

                    <div>
                        <h2 class="font-semibold text-grafito">
                            Calendario laboral
                        </h2>

                        <p class="text-xs text-gris">
                            Días considerados como laborables.
                        </p>
                    </div>

                </div>

            </div>

            <div class="p-5">

                <p class="text-xs text-gris mb-3">
                    Seleccione los días de la semana que se consideran laborables.
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">

                    <template x-for="dia in diasSemana" :key="dia.codigo">

                        <label class="border border-gris-claro rounded-lg p-3
                                      flex items-center gap-2 cursor-pointer
                                      hover:bg-gris-claro/40">

                            <input
                                type="checkbox"
                                :value="dia.codigo"
                                x-model="form.dias_laborables"
                                class="rounded border-gris-claro
                                       text-verde-profundo
                                       focus:ring-verde-institucional">

                            <span class="text-sm text-grafito"
                                  x-text="dia.nombre">
                            </span>

                        </label>

                    </template>

                </div>

            </div>

        </div>

        <!-- EXPEDIENTES -->
        <div class="bg-white rounded-xl border border-gris-claro shadow-sm">

            <div class="p-5 border-b border-gris-claro">

                <div class="flex items-center gap-3">

                    <div class="w-10 h-10 rounded-lg bg-amber-100
                                flex items-center justify-center">

                        <i class="fa-solid fa-folder-tree text-amber-600"></i>

                    </div>

                    <div>
                        <h2 class="font-semibold text-grafito">
                            Expedientes
                        </h2>

                        <p class="text-xs text-gris">
                            Reglas generales de gestión.
                        </p>
                    </div>

                </div>

            </div>

            <div class="p-5 space-y-4">

                <label class="flex items-center justify-between gap-4">

                    <div>
                        <p class="text-sm font-medium text-grafito">
                            Alertas automáticas
                        </p>

                        <p class="text-xs text-gris">
                            Generar alertas según el plazo.
                        </p>
                    </div>

                    <input
                        type="checkbox"
                        x-model="form.alertas_automaticas"
                        class="rounded text-verde-profundo">
                </label>

                <label class="flex items-center justify-between gap-4">

                    <div>
                        <p class="text-sm font-medium text-grafito">
                            Considerar feriados
                        </p>

                        <p class="text-xs text-gris">
                            Excluir feriados del cálculo de plazos.
                        </p>
                    </div>

                    <input
                        type="checkbox"
                        x-model="form.considerar_feriados"
                        class="rounded text-verde-profundo">
                </label>

            </div>

        </div>

        <!-- GUARDAR -->
        <div class="bg-white rounded-xl border border-gris-claro shadow-sm">

            <div class="p-5">

                <h2 class="font-semibold text-grafito">
                    Guardar configuración
                </h2>

                <p class="text-xs text-gris mt-1 mb-5">
                    Los cambios realizados afectarán el funcionamiento del sistema.
                </p>

                <button
                    @click="guardar()"
                    class="bg-verde-profundo text-white px-5 py-2.5
                           rounded-lg text-sm font-medium">

                    <i class="fa-solid fa-floppy-disk mr-2"></i>
                    Guardar parámetros

                </button>

            </div>

        </div>

    </div>

</div>

<script>
function parametrosSistema() {

    return {

        diasSemana: [
            { codigo: 'LUN', nombre: 'Lunes' },
            { codigo: 'MAR', nombre: 'Martes' },
            { codigo: 'MIE', nombre: 'Miércoles' },
            { codigo: 'JUE', nombre: 'Jueves' },
            { codigo: 'VIE', nombre: 'Viernes' },
            { codigo: 'SAB', nombre: 'Sábado' },
            { codigo: 'DOM', nombre: 'Domingo' }
        ],

        form: {
            dias_alerta_amarilla: 5,
            dias_alerta_roja: 2,
            dias_fuera_plazo: 0,

            dias_laborables: [
                'LUN',
                'MAR',
                'MIE',
                'JUE',
                'VIE'
            ],

            alertas_automaticas: true,
            considerar_feriados: true
        },

        async cargar() {

            /*
             * GET /api/administrador/parametros
             */

        },

        async guardar() {

            /*
             * PUT /api/administrador/parametros
             */

            this.$dispatch('toast', {
                tipo: 'exito',
                mensaje: 'Parámetros actualizados correctamente.'
            });
        }
    }
}
</script>
@endsection