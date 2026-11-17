<div class="bg-white rounded-xl border border-gris-claro shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full text-sm">

            <thead class="bg-fondo border-b border-gris-claro">
                <tr>
                    <template x-for="columna in reporteActual.columnas" :key="columna.clave">
                        <th class="text-left px-4 py-3 font-semibold text-grafito whitespace-nowrap" x-text="columna.titulo"></th>
                    </template>

                    <template x-if="reporteActual.codigo === 'R08'">
                        <th class="text-right px-4 py-3 font-semibold text-grafito whitespace-nowrap">
                            Carátula
                        </th>
                    </template>
                </tr>
            </thead>

            <tbody>

                <!-- CARGANDO -->
                <template x-if="cargando">
                    <tr>
                        <td :colspan="reporteActual.columnas.length + (reporteActual.codigo === 'R08' ? 1 : 0)"
                            class="px-4 py-10 text-center text-gris">
                            <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                            Generando reporte...
                        </td>
                    </tr>
                </template>

                <!-- VACÍO -->
                <template x-if="!cargando && filas.length === 0">
                    <tr>
                        <td :colspan="reporteActual.columnas.length + (reporteActual.codigo === 'R08' ? 1 : 0)"
                            class="px-4 py-10 text-center text-gris">
                            No hay datos para los filtros seleccionados.
                        </td>
                    </tr>
                </template>

                <!-- FILAS -->
                <template x-for="(fila, indice) in filas" :key="indice">
                    <tr class="border-b border-gris-claro last:border-0 hover:bg-fondo/60">

                        <template x-for="columna in reporteActual.columnas" :key="columna.clave">
                            <td class="px-4 py-3 text-grafito align-top">
                                <template x-if="columna.clave === 'dias_restantes'">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="Number(valorCelda(fila, columna.clave)) < 0
                                            ? 'bg-red-100 text-red-700'
                                            : (Number(valorCelda(fila, columna.clave)) <= 1
                                                ? 'bg-amber-100 text-amber-700'
                                                : 'bg-green-100 text-green-700')"
                                        x-text="valorCelda(fila, columna.clave)"
                                    ></span>
                                </template>

                                <template x-if="columna.clave !== 'dias_restantes'">
                                    <span x-text="valorCelda(fila, columna.clave)"></span>
                                </template>
                            </td>
                        </template>

                        <template x-if="reporteActual.codigo === 'R08'">
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a
                                    :href="`/api/reportes/caratula/${fila.expediente_id}/pdf`"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-verde-profundo hover:text-verde-institucional"
                                    title="Descargar la carátula oficial en PDF"
                                >
                                    <i class="fa-solid fa-file-pdf"></i>
                                    Foja 0
                                </a>
                            </td>
                        </template>

                    </tr>
                </template>

            </tbody>

        </table>

    </div>

</div>
