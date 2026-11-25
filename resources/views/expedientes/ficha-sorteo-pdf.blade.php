<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Asignación por sorteo — {{ $expediente->nurej_code }}</title>

    <style>
        @page {
            margin: 30px 36px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            color: #000000;
            margin: 0;
        }

        .titulo {
            margin: 0 0 12px 0;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 1px;
            border-bottom: 2px solid #000000;
            padding-bottom: 7px;
        }

        .nured {
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            padding: 7px;
            border: 2px solid #000000;
            background: #ffffff;
            color: #000000;
            margin-bottom: 12px;
            letter-spacing: 0.8px;
        }

        .nured small {
            display: block;
            font-size: 7.5px;
            font-weight: normal;
            color: #000000;
            letter-spacing: 0;
            margin-bottom: 2px;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.datos th {
            width: 28%;
            text-align: left;
            background: #e5e7eb;
            border: 1px solid #000000;
            padding: 4px 6px;
            font-size: 8.5px;
            color: #000000;
        }

        table.datos td {
            border: 1px solid #000000;
            padding: 4px 6px;
            font-size: 9px;
            color: #000000;
        }

        .seccion {
            font-size: 9.5px;
            font-weight: bold;
            color: #000000;
            border-bottom: 1px solid #000000;
            padding-bottom: 2px;
            margin: 10px 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .destacado {
            border: 2px solid #000000;
            background: #ffffff;
            padding: 9px;
            margin-bottom: 10px;
        }

        .destacado .nombre {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
        }

        .destacado .rol {
            font-size: 9px;
            color: #000000;
            margin-top: 2px;
        }

        .descripcion {
            border: 1px solid #000000;
            padding: 6px;
            min-height: 34px;
            margin: 0;
            font-size: 9px;
            color: #000000;
        }
    </style>
</head>
<body>
    <h1 class="titulo">FICHA DE ASIGNACIÓN POR SORTEO</h1>

    <div class="nured">
        <small>NURED</small>
        {{ $expediente->nurej_code }}
    </div>

    @php
        $origenes = [
            \App\Models\Expediente::VIA_INGRESO_VERBAL => 'Ingreso verbal',
            \App\Models\Expediente::VIA_INGRESO_DISTRITAL => 'Ingreso desde distrital',
        ];
        $naturalezas = ['JURISDICCIONAL' => 'Jurisdiccional', 'ADMINISTRATIVA' => 'Administrativa'];
    @endphp

    <div class="seccion">Datos del expediente</div>

    <table class="datos">
        <tr>
            <th>Fecha de ingreso</th>
            <td>{{ $expediente->fecha_ingreso?->format('d/m/Y') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Hora de ingreso</th>
            <td>{{ $expediente->fecha_ingreso?->format('H:i') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Origen de ingreso</th>
            <td>{{ $origenes[$expediente->via] ?? $expediente->via }}</td>
        </tr>
        @if ($expediente->numero_hoja_ruta)
            <tr>
                <th>Número de hoja de ruta</th>
                <td>{{ $expediente->numero_hoja_ruta }}</td>
            </tr>
        @endif
        <tr>
            <th>Naturaleza</th>
            <td>{{ $naturalezas[$expediente->naturaleza] ?? $expediente->naturaleza }}</td>
        </tr>
        <tr>
            <th>Reglamento</th>
            <td>
                {{ $expediente->reglamento?->codigo ?? 'Sin reglamento' }}
                @if ($expediente->reglamento?->nombre)
                    — {{ $expediente->reglamento->nombre }}
                @endif
            </td>
        </tr>
    </table>

    <div class="seccion">Funcionario asignado</div>

    <div class="destacado">
        <div class="nombre">{{ trim(($ganador->nombres ?? '').' '.($ganador->apellidos ?? '')) }}</div>
        <div class="rol">{{ $ganador->rol?->nombre ?? 'Rol no disponible' }}</div>
    </div>

    <table class="datos">
        <tr>
            <th>Fecha del sorteo</th>
            <td>{{ $actuado->fecha_hora?->format('d/m/Y') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Hora del sorteo</th>
            <td>{{ $actuado->fecha_hora?->format('H:i') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Ejecutado por</th>
            <td>{{ trim(($encargada->nombres ?? '').' '.($encargada->apellidos ?? '')) }}</td>
        </tr>
        <tr>
            <th>Actuado de sorteo</th>
            <td>{{ $actuado->tipoActuado?->nombre ?? 'Sorteo inicial' }} — N.º {{ $actuado->id }}</td>
        </tr>
    </table>

    <div class="seccion">Descripción del sorteo</div>
    <p class="descripcion">{{ $descripcion ?: 'Sorteo probabilístico inicial.' }}</p>
</body>
</html>
