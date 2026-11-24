@php
    $ruta = request()->route()?->getName();
    $rol = auth()->user()?->rol?->codigo;

    $inicio = match ($rol) {
        \App\Models\Rol::CODIGO_ADMIN => route('administrador.dashboard'),
        \App\Models\Rol::CODIGO_ENCARGADA => route('encargada.dashboard'),
        \App\Models\Rol::CODIGO_AUD_FINANCIERO => route('financiero.dashboard'),
        default => route('expedientes.bandeja'),
    };

    $padreExpedientes = match ($rol) {
        \App\Models\Rol::CODIGO_ADMIN => route('administrador.monitoreo'),
        \App\Models\Rol::CODIGO_ENCARGADA => route('encargada.bandeja-supervision'),
        default => route('expedientes.bandeja'),
    };

    $expedienteRuta = request()->route('expediente');
    $etiquetaDetalle = $expedienteRuta instanceof \App\Models\Expediente
        ? $expedienteRuta->nurej_code
        : 'Detalle';

    $mapa = [
        'expedientes.bandeja' => [
            ['etiqueta' => 'Bandeja de entrada', 'url' => null],
        ],
        'expedientes.apertura' => [
            ['etiqueta' => 'Expedientes', 'url' => route('expedientes.bandeja')],
            ['etiqueta' => 'Apertura de causa', 'url' => null],
        ],
        'expedientes.bandeja-sorteo' => [
            ['etiqueta' => 'Sorteo de expedientes', 'url' => null],
        ],
        'expedientes.detalle' => [
            ['etiqueta' => 'Expedientes', 'url' => $padreExpedientes],
            ['etiqueta' => $etiquetaDetalle, 'url' => null],
        ],
        'reportes.index' => [
            ['etiqueta' => 'Reportes', 'url' => null],
        ],
        'encargada.dashboard' => [],
        'encargada.bandeja-supervision' => [
            ['etiqueta' => 'Bandeja de supervisión', 'url' => null],
        ],
        'financiero.dashboard' => [],
        'administrador.dashboard' => [],
        'administrador.usuarios' => [
            ['etiqueta' => 'Usuarios', 'url' => null],
        ],
        'administrador.feriados' => [
            ['etiqueta' => 'Feriados', 'url' => null],
        ],
        'administrador.requisitos' => [
            ['etiqueta' => 'Requisitos de admisibilidad', 'url' => null],
        ],
        'administrador.monitoreo' => [
            ['etiqueta' => 'Monitoreo de expedientes', 'url' => null],
        ],
    ];

    $items = array_merge([['etiqueta' => 'Inicio', 'url' => $inicio]], $mapa[$ruta] ?? []);
@endphp

@if (count($items) > 1)
    <nav aria-label="Ruta de navegación" class="px-6 pt-4">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-gris">
            @foreach ($items as $item)
                <li class="flex items-center gap-1.5">
                    @if ($loop->first)
                        <i class="fa-solid fa-house text-[11px] text-gris"></i>
                    @else
                        <i class="fa-solid fa-chevron-right text-[9px] text-gris/60"></i>
                    @endif

                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" class="hover:text-verde-profundo hover:underline">{{ $item['etiqueta'] }}</a>
                    @else
                        <span class="font-semibold text-grafito" aria-current="page">{{ $item['etiqueta'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
