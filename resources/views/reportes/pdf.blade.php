<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $datos['codigo'] }} — {{ $datos['titulo'] }}</title>

    <style>
        @page {
            margin: 105px 28px 60px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #1f2937;
            margin: 0;
        }

        .marca-agua {
            position: fixed;
            top: 38%;
            left: 12%;
            width: 76%;
            text-align: center;
            font-size: 42px;
            font-weight: bold;
            color: #1b5e20;
            opacity: 0.07;
            transform: rotate(-30deg);
            z-index: -1;
        }

        .encabezado {
            position: fixed;
            top: -82px;
            left: 0;
            right: 0;
            height: 76px;
            border-bottom: 2px solid #1b5e20;
            padding-bottom: 8px;
        }

        .encabezado td {
            vertical-align: middle;
        }

        .logo {
            width: 58px;
        }

        .institucion {
            font-size: 12px;
            font-weight: bold;
            color: #1b5e20;
            letter-spacing: 0.4px;
        }

        .unidad {
            font-size: 9px;
            color: #4b5563;
        }

        .titulo-reporte {
            font-size: 15px;
            font-weight: bold;
            color: #1b5e20;
            margin: 0 0 2px 0;
        }

        .subtitulo {
            font-size: 9px;
            color: #4b5563;
            margin: 0 0 8px 0;
        }

        .filtros {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8px;
        }

        .filtros td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            background: #f9fafb;
        }

        .filtros .etiqueta {
            color: #6b7280;
            font-weight: bold;
            text-transform: uppercase;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
        }

        table.datos thead {
            display: table-header-group;
        }

        table.datos th {
            background: #1b5e20;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-align: left;
            padding: 6px 5px;
            border: 1px solid #14532d;
        }

        table.datos td {
            padding: 5px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        table.datos tbody tr:nth-child(even) td {
            background: #f9fafb;
        }

        .pie {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            font-size: 7.5px;
            color: #6b7280;
            text-align: center;
        }

        .sin-datos {
            padding: 24px;
            text-align: center;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>

    <div class="marca-agua">
        CONSEJO DE LA MAGISTRATURA
    </div>

    <table class="encabezado">
        <tr>
            <td style="width: 70px;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Escudo institucional" class="logo">
                @endif
            </td>
            <td>
                <div class="institucion">CONSEJO DE LA MAGISTRATURA</div>
                <div class="unidad">Unidad de Control y Fiscalización — Cochabamba</div>
            </td>
            <td style="width: 170px; text-align: right; font-size: 8px; color: #4b5563;">
                <div><strong>{{ $datos['codigo'] }}</strong></div>
                <div>Generado: {{ $datos['meta']['generado_en'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <h1 class="titulo-reporte">{{ $datos['titulo'] }}</h1>

    <p class="subtitulo">{{ $datos['descripcion'] }}</p>

    @if (! empty($datos['filtros_aplicados']))
        <table class="filtros">
            <tr>
                @foreach ($datos['filtros_aplicados'] as $clave => $valor)
                    <td>
                        <span class="etiqueta">{{ str_replace('_', ' ', $clave) }}:</span>
                        {{ $valor }}
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if (count($datos['data']) === 0)
        <div class="sin-datos">
            No hay datos para los filtros seleccionados.
        </div>
    @else
        <table class="datos">
            <thead>
                <tr>
                    @foreach ($datos['columnas'] as $columna)
                        <th>{{ $columna['titulo'] }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($datos['data'] as $fila)
                    <tr>
                        @foreach ($datos['columnas'] as $columna)
                            <td>{{ $fila[$columna['clave']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="pie">
        Documento generado automáticamente por el Sistema de Control y Fiscalización.
        Total de filas: {{ $datos['meta']['total'] ?? count($datos['data']) }}.
    </div>

</body>
</html>
