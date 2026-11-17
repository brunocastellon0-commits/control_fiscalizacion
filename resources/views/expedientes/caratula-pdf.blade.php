<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carátula oficial — {{ $expediente->nurej_code }}</title>

    <style>
        @page {
            margin: 32px 38px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
        }

        .marca-agua {
            position: fixed;
            top: 40%;
            left: 8%;
            width: 84%;
            text-align: center;
            font-size: 44px;
            font-weight: bold;
            color: #1b5e20;
            opacity: 0.06;
            transform: rotate(-32deg);
            z-index: -1;
        }

        .cabecera {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #1b5e20;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .cabecera td {
            vertical-align: middle;
        }

        .logo {
            width: 70px;
        }

        .institucion {
            font-size: 14px;
            font-weight: bold;
            color: #1b5e20;
            letter-spacing: 0.5px;
        }

        .unidad {
            font-size: 9px;
            color: #4b5563;
            margin-top: 2px;
        }

        .titulo {
            margin: 12px 0 4px 0;
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            color: #1b5e20;
            letter-spacing: 1px;
        }

        .subtitulo {
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            margin: 0 0 14px 0;
        }

        .nurej {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            padding: 8px;
            border: 2px solid #1b5e20;
            background: #f0f7f0;
            margin-bottom: 14px;
            letter-spacing: 0.8px;
        }

        .nurej small {
            display: block;
            font-size: 8px;
            font-weight: normal;
            color: #4b5563;
            letter-spacing: 0;
            margin-bottom: 3px;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.datos th {
            width: 30%;
            text-align: left;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 5px 7px;
            font-size: 9px;
            color: #374151;
        }

        table.datos td {
            border: 1px solid #d1d5db;
            padding: 5px 7px;
            font-size: 9.5px;
        }

        .seccion {
            font-size: 10px;
            font-weight: bold;
            color: #1b5e20;
            border-bottom: 1px solid #1b5e20;
            padding-bottom: 3px;
            margin: 12px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        table.partes {
            width: 100%;
            border-collapse: collapse;
        }

        table.partes th {
            background: #1b5e20;
            color: #ffffff;
            font-size: 8.5px;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #14532d;
        }

        table.partes td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            vertical-align: top;
        }

        .sello-contenedor {
            margin-top: 18px;
            width: 100%;
            border-collapse: collapse;
        }

        .sello-contenedor td {
            vertical-align: bottom;
        }

        .sello {
            width: 240px;
            border: 2px dashed #1b5e20;
            padding: 8px;
            text-align: center;
            color: #1b5e20;
        }

        .sello .titulo-sello {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }

        .sello .hash {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 8px;
            word-break: break-all;
            margin: 4px 0;
            color: #14532d;
        }

        .sello .leyenda {
            font-size: 6.5px;
            color: #4b5563;
            line-height: 1.25;
        }

        .firma {
            text-align: center;
            font-size: 8.5px;
            color: #374151;
        }

        .firma .linea {
            border-top: 1px solid #374151;
            width: 210px;
            margin: 28px auto 3px auto;
        }

        .pie {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            font-size: 7px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="marca-agua">
        CONSEJO DE LA MAGISTRATURA
    </div>

    <table class="cabecera">
        <tr>
            <td style="width: 82px;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Escudo institucional" class="logo">
                @endif
            </td>
            <td>
                <div class="institucion">CONSEJO DE LA MAGISTRATURA</div>
                <div class="unidad">Unidad de Control y Fiscalización — Cochabamba</div>
            </td>
            <td style="width: 125px; text-align: right; font-size: 7.5px; color: #6b7280;">
                <div>Foja 0</div>
                <div>Emitida: {{ $generadoEn->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <h1 class="titulo">CARÁTULA OFICIAL</h1>

    <p class="subtitulo">Documento de identificación de la causa — Foja Cero</p>

    <div class="nurej">
        <small>NUREJ</small>
        {{ $expediente->nurej_code }}
    </div>

    <div class="seccion">Identificación procesal</div>

    <table class="datos">
        <tr>
            <th>NUREJ padre</th>
            <td>{{ $expediente->padre?->nurej_code ?? 'Sin NUREJ padre' }}</td>
        </tr>
        <tr>
            <th>NUREJ derivados</th>
            <td>
                @if ($expediente->hijos->isEmpty())
                    Sin derivaciones registradas
                @else
                    {{ $expediente->hijos->pluck('nurej_code')->implode(', ') }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Fecha de ingreso</th>
            <td>{{ $expediente->fecha_ingreso?->format('d/m/Y H:i') ?? 'Sin registro' }}</td>
        </tr>
        <tr>
            <th>Vía procesal</th>
            <td>{{ $expediente->via }}</td>
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
        <tr>
            <th>Estado actual</th>
            <td>{{ $expediente->estadoActual?->nombre ?? 'Sin estado' }}</td>
        </tr>
        <tr>
            <th>Registrado por</th>
            <td>{{ trim(($expediente->creador?->nombres ?? '').' '.($expediente->creador?->apellidos ?? '')) }}</td>
        </tr>
    </table>

    <div class="seccion">Partes intervinientes</div>

    @if ($expediente->partesVigentes->isEmpty())
        <p>Sin partes vigentes registradas.</p>
    @else
        <table class="partes">
            <thead>
                <tr>
                    <th style="width: 20%;">Tipo</th>
                    <th>Nombre completo</th>
                    <th style="width: 22%;">Documento</th>
                    <th style="width: 24%;">Cargo / institución</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($expediente->partesVigentes as $parte)
                    <tr>
                        <td>{{ $parte->tipo }}</td>
                        <td>{{ $parte->nombre_completo }}</td>
                        <td>{{ $parte->documento_identidad ?? '—' }}</td>
                        <td>{{ $parte->cargo_institucion ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="seccion">Síntesis de los hechos</div>

    <p style="border: 1px solid #d1d5db; padding: 6px; min-height: 36px; margin: 0;">
        {{ $expediente->resumen_hechos ?: 'Sin síntesis registrada.' }}
    </p>

    <table class="sello-contenedor">
        <tr>
            <td>
                <div class="sello">
                    <div class="titulo-sello">SELLO DE CUSTODIA</div>
                    <div class="hash">{{ $sello }}</div>
                    <div class="leyenda">
                        Huella SHA-256 de la identificación de la causa y del último
                        actuado registrado. Verificable contra la cadena de custodia.
                    </div>
                </div>
            </td>
            <td>
                <div class="firma">
                    <div class="linea"></div>
                    <div>{{ $generadoPor ?: 'Funcionario del sistema' }}</div>
                    <div style="font-size: 7.5px; color: #6b7280;">Documento generado por el sistema</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="pie">
        Documento emitido el {{ $generadoEn->format('d/m/Y \a \l\a\s H:i') }}.
        La información consignada proviene del expediente {{ $expediente->nurej_code }} y se encuentra
        respaldada por la cadena de actuados inmutables del sistema.
    </div>

</body>
</html>
