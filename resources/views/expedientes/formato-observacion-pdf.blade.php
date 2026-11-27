<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Formato de observación — {{ $expediente->nurej_code }}</title>

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

        .descripcion {
            border: 1px solid #000000;
            padding: 6px;
            min-height: 34px;
            margin: 0;
            font-size: 9px;
            color: #000000;
        }

        .sello {
            border: 1px solid #000000;
            padding: 6px;
            margin: 0;
            font-size: 9px;
            color: #000000;
            letter-spacing: 0.5px;
        }

        table.firmas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        table.firmas td {
            width: 50%;
            vertical-align: top;
            padding: 24px 10px 4px 0;
            font-size: 9px;
            color: #000000;
        }

        table.firmas td small {
            display: block;
            margin-top: 3px;
            font-size: 7.5px;
            color: #000000;
        }
    </style>
</head>
<body>
    <h1 class="titulo">FORMATO DE OBSERVACIÓN</h1>

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
            <td>{{ $naturalezas[$expediente->naturaleza] ?? $expediente->naturaleza ?? 'Sin registro' }}</td>
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

    <div class="seccion">Detalle de la observación</div>
    <p class="descripcion">{{ $detalleObservacion ?: 'Sin detalle registrado.' }}</p>

    <div class="seccion">Requisitos faltantes</div>
    @if ($faltantes->isEmpty())
        <p class="descripcion">Sin requisitos faltantes registrados.</p>
    @else
        <table class="datos">
            <tr>
                <th style="width: 8%">#</th>
                <th>Requisito</th>
                <th style="width: 25%">Estado</th>
            </tr>
            @foreach ($faltantes as $indice => $evaluacion)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>
                        {{ $evaluacion->requisito?->nombre
                            ?? $evaluacion->requisito?->codigo
                            ?? 'Requisito #'.$evaluacion->requisito_id }}
                    </td>
                    <td>No cumple</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="seccion">Plazo para subsanar</div>
    @if ($plazo)
        <table class="datos">
            <tr>
                <th>Fecha límite</th>
                <td>{{ $plazo->fecha_limite?->format('d/m/Y') ?? 'Sin registro' }}</td>
            </tr>
            <tr>
                <th>Días hábiles otorgados</th>
                <td>{{ $plazo->dias_habiles_otorgados }}</td>
            </tr>
            <tr>
                <th>Estado del plazo</th>
                <td>{{ $plazo->estado }}</td>
            </tr>
        </table>
    @else
        <p class="descripcion">Sin plazo de subsanación parametrizado para este reglamento.</p>
    @endif

    <div class="seccion">Actuado emisor</div>
    <table class="datos">
        <tr>
            <th>Fecha y hora</th>
            <td>{{ $actuado->fecha_hora?->format('d/m/Y H:i') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Tipo de actuado</th>
            <td>{{ $actuado->tipoActuado?->nombre ?? 'Acto de observación' }} — N.º {{ $actuado->id }}</td>
        </tr>
        <tr>
            <th>Emitido por</th>
            <td>
                {{ trim(($actuado->usuario?->nombres ?? '').' '.($actuado->usuario?->apellidos ?? '')) ?: 'Sin registro' }}
                @if ($actuado->usuario?->rol?->nombre)
                    — {{ $actuado->usuario->rol->nombre }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Fecha de impresión</th>
            <td>{{ $generadoEn->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="seccion">Verificación</div>
    <p class="sello">Sello de verificación: <strong>{{ $sello }}</strong></p>

    <table class="firmas">
        <tr>
            <td>
                Entregado por: ___________________________________
                <small>
                    {{ trim(($actuado->usuario?->nombres ?? '').' '.($actuado->usuario?->apellidos ?? '')) ?: 'Nombre del funcionario' }}
                </small>
            </td>
            <td>
                Recibido por: ___________________________________
                <small>Documento de identidad: ______________________</small>
            </td>
        </tr>
    </table>
</body>
</html>
