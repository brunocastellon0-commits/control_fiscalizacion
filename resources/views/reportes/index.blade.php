@extends('layouts.app')

@section('titulo', 'Reportes institucionales')

@section('contenido')

<div
    class="p-6"
    x-data="panelReportes(@js(array_values($definiciones)), @js($estados), @js($usuarios))"
    x-init="cargar()"
>

    <!-- ENCABEZADO -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-5">

        <div>
            <h1 class="text-xl font-bold text-grafito">
                Reportes institucionales
            </h1>

            <p class="text-sm text-gris">
                Los 9 reportes normativos del SRS con filtros por fecha, usuario y estado.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">

            <a
                :href="urlDescarga('excel')"
                class="inline-flex items-center gap-2 border border-verde-profundo text-verde-profundo px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-verde-profundo hover:text-white transition"
                title="Descargar el reporte actual en Excel"
            >
                <i class="fa-solid fa-file-excel"></i>
                Excel (.xlsx)
            </a>

            <a
                :href="urlDescarga('pdf')"
                class="inline-flex items-center gap-2 bg-verde-profundo text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-verde-institucional transition"
                title="Descargar el reporte actual en PDF"
            >
                <i class="fa-solid fa-file-pdf"></i>
                PDF
            </a>

        </div>

    </div>

    <!-- PESTAÑAS DE LOS 9 REPORTES -->
    <div class="bg-white rounded-xl border border-gris-claro shadow mb-5 overflow-hidden">

        <div class="flex overflow-x-auto">

            <template x-for="reporte in reportes" :key="reporte.codigo">

                <button
                    @click="cambiarReporte(reporte.codigo)"
                    class="shrink-0 px-4 py-3 text-xs font-medium border-b-2 transition whitespace-nowrap"
                    :class="reporteActual.codigo === reporte.codigo
                        ? 'border-verde-profundo text-verde-profundo bg-[#E7F0E7]/50'
                        : 'border-transparent text-gris hover:text-grafito hover:bg-fondo'"
                    :title="reporte.descripcion"
                >
                    <span class="font-bold mr-1" x-text="reporte.codigo"></span>
                    <span x-text="reporte.titulo"></span>
                </button>

            </template>

        </div>

    </div>

    <!-- DESCRIPCIÓN DEL REPORTE -->
    <p class="text-sm text-gris mb-4" x-text="reporteActual.descripcion"></p>

    <!-- AYUDA CONTEXTUAL (B9.5) -->
    <details class="bg-white rounded-xl border border-gris-claro shadow mb-5 group">
        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-verde-profundo flex items-center gap-2">
            <i class="fa-regular fa-circle-question"></i>
            ¿Cómo usar estos reportes?
            <i class="fa-solid fa-chevron-down ml-auto text-xs transition group-open:rotate-180"></i>
        </summary>

        <div class="px-4 pb-4 text-xs text-gris space-y-1.5 border-t border-gris-claro pt-3">
            <p><strong class="text-grafito">Filtros:</strong> la fecha, el usuario y el estado se aplican a la tabla y a las descargas Excel/PDF del reporte activo.</p>
            <p><strong class="text-grafito">Pestañas:</strong> cada código R01–R09 corresponde a un reporte normativo del SRS. La descripción superior indica su alcance.</p>
            <p><strong class="text-grafito">Carátula:</strong> en R08, el botón de cada fila genera la Foja Cero del expediente con sello de custodia.</p>
            <p><strong class="text-grafito">Trazabilidad:</strong> el aviso inferior indica si los datos vienen del contrato CTR-08 o del respaldo local mientras se integra el backend de dominio.</p>
        </div>
    </details>

    <!-- FILTROS -->
    <div class="bg-white rounded-xl border border-gris-claro shadow p-4 mb-5">

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">

            <div>
                <label class="block text-xs font-medium text-grafito mb-1" title="Incluye registros desde esta fecha (inclusive).">
                    Fecha desde
                </label>

                <input
                    type="date"
                    x-model="filtros.fecha_desde"
                    @change="cargar()"
                    title="Incluye registros desde esta fecha (inclusive)."
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1" title="Incluye registros hasta esta fecha (inclusive).">
                    Fecha hasta
                </label>

                <input
                    type="date"
                    x-model="filtros.fecha_hasta"
                    @change="cargar()"
                    title="Incluye registros hasta esta fecha (inclusive)."
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1" title="Filtra por el operador con asignación activa.">
                    Usuario asignado
                </label>

                <select
                    x-model="filtros.usuario_id"
                    @change="cargar()"
                    title="Filtra por el operador con asignación activa."
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Todos</option>

                    <template x-for="usuario in usuarios" :key="usuario.id">
                        <option :value="usuario.id" x-text="usuario.nombre"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-grafito mb-1" title="Filtra por el estado actual del trámite.">
                    Estado del trámite
                </label>

                <select
                    x-model="filtros.estado"
                    @change="cargar()"
                    title="Filtra por el estado actual del trámite."
                    class="w-full border border-gris-claro rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Todos</option>

                    <template x-for="estado in estados" :key="estado.id">
                        <option :value="estado.codigo" x-text="estado.nombre"></option>
                    </template>
                </select>
            </div>

            <div class="flex items-end">
                <button
                    @click="limpiarFiltros()"
                    title="Restablece fecha, usuario y estado a 'Todos'."
                    class="w-full border border-gris-claro text-grafito px-4 py-2 rounded-lg text-sm hover:bg-fondo transition"
                >
                    <i class="fa-solid fa-filter-circle-xmark mr-1.5"></i>
                    Limpiar filtros
                </button>
            </div>

        </div>

    </div>

    <!-- ERROR -->
    <template x-if="error">
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>
            <span x-text="error"></span>
        </div>
    </template>

    <!-- RESUMEN -->
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mb-3 text-xs text-gris">

        <span>
            <i class="fa-solid fa-table-list mr-1"></i>
            <strong class="text-grafito" x-text="filas.length"></strong> filas
        </span>

        <span x-show="meta.generado_en">
            <i class="fa-regular fa-clock mr-1"></i>
            Generado: <span x-text="meta.generado_en"></span>
        </span>

        <span x-show="fuenteDatos === 'respaldo'" class="text-[#B53F12]">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Datos desde el respaldo local de reportes.
        </span>

        <span x-show="fuenteDatos === 'ctr08'" class="text-verde-profundo">
            <i class="fa-solid fa-circle-check mr-1"></i>
            Datos desde CTR-08.
        </span>

    </div>

    @include('reportes.tabla')

</div>

<script>
function panelReportes(definiciones, estados, usuarios) {
    return {
        reportes: definiciones,
        reporteActual: definiciones[0],
        estados: estados,
        usuarios: usuarios,
        filas: [],
        meta: {},
        filtros: {
            fecha_desde: '',
            fecha_hasta: '',
            usuario_id: '',
            estado: '',
        },
        cargando: false,
        error: null,
        fuenteDatos: '',

        cambiarReporte(codigo) {
            const reporte = this.reportes.find(r => r.codigo === codigo);

            if (!reporte || this.cargando) {
                return;
            }

            this.reporteActual = reporte;
            this.filas = [];
            this.meta = {};
            this.fuenteDatos = '';
            this.cargar();
        },

        limpiarFiltros() {
            this.filtros = {
                fecha_desde: '',
                fecha_hasta: '',
                usuario_id: '',
                estado: '',
            };

            this.cargar();
        },

        parametros() {
            const params = new URLSearchParams();

            Object.entries(this.filtros).forEach(([clave, valor]) => {
                if (valor !== '' && valor !== null) {
                    params.set(clave, valor);
                }
            });

            const consulta = params.toString();

            return consulta ? `?${consulta}` : '';
        },

        async cargar() {
            this.cargando = true;
            this.error = null;

            try {
                const consulta = this.parametros();
                const codigo = this.reporteActual.codigo;

                const ctr08 = await window.apiFetch(`/api/reportes/${codigo}${consulta}`);

                if (ctr08.ok) {
                    this.aplicarRespuesta(ctr08.data);
                    this.fuenteDatos = 'ctr08';
                    return;
                }

                const respaldo = await window.apiFetch(`/api/reportes/${codigo}/datos${consulta}`);

                if (!respaldo.ok) {
                    throw new Error(respaldo.data?.message || 'No se pudo cargar el reporte.');
                }

                this.aplicarRespuesta(respaldo.data);
                this.fuenteDatos = 'respaldo';
            } catch (e) {
                this.filas = [];
                this.meta = {};
                this.error = e.message || 'No se pudo cargar el reporte.';
            } finally {
                this.cargando = false;
            }
        },

        aplicarRespuesta(respuesta) {
            const filas = Array.isArray(respuesta)
                ? respuesta
                : (Array.isArray(respuesta?.data) ? respuesta.data : []);

            this.filas = filas;
            this.meta = respuesta?.meta || { total: filas.length };
        },

        urlDescarga(formato) {
            const consulta = this.parametros();

            return `/api/reportes/${this.reporteActual.codigo}/exportar/${formato}${consulta}`;
        },

        valorCelda(fila, clave) {
            const valor = fila?.[clave];

            return valor === null || valor === undefined ? '' : valor;
        },
    };
}
</script>

@endsection
