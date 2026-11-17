<?php

namespace App\Http\Controllers;

use App\Exports\ReporteExport;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ReporteDatosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteDatosService $reportes) {}

    /**
     * Pantalla unificada de los 9 reportes normativos.
     */
    public function index(Request $request): View
    {
        $this->autorizarReporte($request);

        return view('reportes.index', [
            'definiciones' => ReporteDatosService::definiciones(),
            'estados' => CatalogoEstado::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'usuarios' => Usuario::query()
                ->where('activo', true)
                ->orderBy('nombres')
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'rol_id'])
                ->map(fn (Usuario $usuario): array => [
                    'id' => $usuario->id,
                    'nombre' => trim($usuario->nombres.' '.$usuario->apellidos),
                ]),
        ]);
    }

    /**
     * Respaldo local del contrato CTR-08 mientras `ReportesQueryService`
     * no esté integrado. La pantalla intenta primero `/api/reportes/{codigo}`
     * y cae a este endpoint si el backend de dominio aún no responde.
     */
    public function datos(Request $request, string $codigo): JsonResponse
    {
        $this->autorizarReporte($request);

        return response()->json(
            $this->reportes->obtener($codigo, $this->filtros($request)),
        );
    }

    /**
     * Descarga del reporte en Excel (.xlsx).
     */
    public function exportarExcel(Request $request, string $codigo): BinaryFileResponse
    {
        $this->autorizarReporte($request);

        $datos = $this->reportes->obtener($codigo, $this->filtros($request));
        $archivo = 'reporte-'.$datos['codigo'].'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new ReporteExport($datos), $archivo);
    }

    /**
     * Descarga del reporte en PDF institucional.
     */
    public function exportarPdf(Request $request, string $codigo): Response
    {
        $this->autorizarReporte($request);

        $datos = $this->reportes->obtener($codigo, $this->filtros($request));

        $pdf = Pdf::loadView('reportes.pdf', [
            'datos' => $datos,
            'logo' => $this->logoBase64(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte-'.$datos['codigo'].'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Carátula oficial / Foja 0 del expediente (RF-R08).
     */
    public function caratulaPdf(Request $request, Expediente $expediente): Response
    {
        $this->autorizarReporte($request);
        $this->authorize('view', $expediente);

        $expediente->load([
            'padre.estadoActual',
            'hijos.estadoActual',
            'partesVigentes',
            'reglamento',
            'estadoActual',
            'creador',
        ]);

        $ultimoHash = $expediente->actuados()->latest('id')->value('hash_actuado');

        $sello = strtoupper(substr(hash('sha256', implode('|', [
            $expediente->id,
            $expediente->nurej_code,
            $expediente->fecha_ingreso?->toIso8601String() ?? '',
            $ultimoHash ?? '',
        ])), 0, 32));

        $pdf = Pdf::loadView('expedientes.caratula-pdf', [
            'expediente' => $expediente,
            'sello' => $sello,
            'logo' => $this->logoBase64(),
            'generadoPor' => trim(($request->user()?->nombres ?? '').' '.($request->user()?->apellidos ?? '')),
            'generadoEn' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('caratula-'.$expediente->nurej_code.'.pdf');
    }

    /**
     * @return array{fecha_desde?: string|null, fecha_hasta?: string|null, usuario_id?: int|null, estado?: string|null}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'usuario_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'max:30'],
        ]);

        return [
            'fecha_desde' => $validados['fecha_desde'] ?? null,
            'fecha_hasta' => $validados['fecha_hasta'] ?? null,
            'usuario_id' => $validados['usuario_id'] ?? null,
            'estado' => $validados['estado'] ?? null,
        ];
    }

    private function autorizarReporte(Request $request): void
    {
        $usuario = $request->user();

        abort_unless($usuario !== null && $usuario->activo, 403, 'Usuario inactivo.');

        abort_if(
            ($usuario->rol?->codigo ?? null) === Rol::CODIGO_ADMIN,
            403,
            'El rol ADMIN no tiene acceso a los reportes institucionales.',
        );
    }

    /**
     * Devuelve el emblema institucional embebido para DomPDF.
     *
     * DomPDF exige la extensión GD para procesar PNG. Si no está disponible en
     * el servidor, se usa el emblema vectorial SVG (sin GD) para que las
     * descargas PDF sigan siendo operativas.
     */
    private function logoBase64(): ?string
    {
        $usaGd = extension_loaded('gd');

        $ruta = public_path($usaGd ? 'images/logoconsejo_sinfondo.png' : 'images/escudo-cm.svg');

        if (! is_file($ruta)) {
            return null;
        }

        $mime = $usaGd ? 'image/png' : 'image/svg+xml';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($ruta));
    }
}
