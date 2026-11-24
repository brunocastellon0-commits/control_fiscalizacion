<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recepción de causa — {{ $expediente->nurej_code }}</title>

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

        table.partes {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.partes th {
            background: #000000;
            color: #ffffff;
            font-size: 8px;
            text-align: left;
            padding: 4px 5px;
            border: 1px solid #000000;
        }

        table.partes td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: top;
            color: #000000;
        }

        .sin-partes {
            padding: 5px 7px;
            border: 1px dashed #000000;
            color: #000000;
            font-size: 8.5px;
            margin-bottom: 4px;
        }

        .resumen {
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
    <h1 class="titulo">RECEPCIÓN DE CAUSA</h1>

    <div class="nured">
        <small>NURED</small>
        {{ $expediente->nurej_code }}
    </div>

    @php
        $denunciantes = $expediente->partesVigentes->where('tipo', 'DENUNCIANTE');
        $denunciados = $expediente->partesVigentes->where('tipo', 'DENUNCIADO');
    @endphp

    <div class="seccion">Datos de recepción</div>

    <table class="datos">
        <tr>
            <th>Fecha de recepción</th>
            <td>{{ $expediente->fecha_ingreso?->format('d/m/Y') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Hora de recepción</th>
            <td>{{ $expediente->fecha_ingreso?->format('H:i') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Registrado por</th>
            <td>{{ trim(($expediente->creador?->nombres ?? '').' '.($expediente->creador?->apellidos ?? '')) ?: $generadoPor }}</td>
        </tr>
    </table>

    <div class="seccion">Denunciantes</div>

    @if ($denunciantes->isEmpty())
        <div class="sin-partes">Sin denunciantes registrados.</div>
    @else
        <table class="partes">
            <thead>
                <tr>
                    <th style="width: 8%;">N.º</th>
                    <th>Nombre completo</th>
                    <th style="width: 20%;">Documento</th>
                    <th style="width: 26%;">Cargo / institución</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($denunciantes as $parte)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $parte->nombre_completo }}</td>
                        <td>{{ $parte->documento_identidad ?? '—' }}</td>
                        <td>{{ $parte->cargo_institucion ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="seccion">Denunciados</div>

    @if ($denunciados->isEmpty())
        <div class="sin-partes">Sin denunciados registrados.</div>
    @else
        <table class="partes">
            <thead>
                <tr>
                    <th style="width: 8%;">N.º</th>
                    <th>Nombre completo</th>
                    <th style="width: 20%;">Documento</th>
                    <th style="width: 26%;">Cargo / institución</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($denunciados as $parte)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $parte->nombre_completo }}</td>
                        <td>{{ $parte->documento_identidad ?? '—' }}</td>
                        <td>{{ $parte->cargo_institucion ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="seccion">Resumen de los hechos</div>
    <p class="resumen">{{ $expediente->resumen_hechos ?: 'Sin resumen registrado.' }}</p>
</body>
</html>
