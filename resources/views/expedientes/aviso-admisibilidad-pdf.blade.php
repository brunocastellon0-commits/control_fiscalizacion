<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} — {{ $expediente->nurej_code }}</title>
    <style>
        @page { margin: 30px 36px; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #000000;
            margin: 0;
        }

        h1 {
            margin: 0 0 4px 0;
            text-align: center;
            font-size: 16px;
            letter-spacing: 1px;
        }

        .nurej {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            padding: 7px;
            border: 1px solid #000000;
            margin: 12px 0 16px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        th, td {
            border: 1px solid #000000;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            width: 32%;
            background: #f2f2f2;
        }

        .detalle { margin-bottom: 14px; line-height: 1.5; }
        .pie { margin-top: 26px; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="nurej">NUREJ: {{ $expediente->nurej_code }}</div>

    <table>
        <tr>
            <th>Fecha de emisión</th>
            <td>{{ $fechaEmision }}</td>
        </tr>
        <tr>
            <th>Estado del expediente</th>
            <td>{{ $estado }}</td>
        </tr>
        @if ($fechaLimite !== null)
            <tr>
                <th>Fecha límite</th>
                <td>{{ $fechaLimite }}</td>
            </tr>
        @endif
    </table>

    <p class="detalle">{{ $detalle }}</p>

    @if (count($requisitos) > 0)
        <table>
            <tr>
                <th colspan="2">Requisitos observados</th>
            </tr>
            @foreach ($requisitos as $index => $requisito)
                <tr>
                    <th>{{ $index + 1 }}</th>
                    <td>{{ $requisito }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="pie">Documento generado automáticamente por el sistema de control y fiscalización.</p>
</body>
</html>
