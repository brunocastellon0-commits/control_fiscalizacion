<?php

use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\SorteoPeso;
use App\Models\Usuario;
use App\Services\SorteoAlgorithmService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);

    $this->reglamentos = collect([
        Reglamento::CODIGO_AC_022 => Reglamento::factory()->create([
            'codigo' => Reglamento::CODIGO_AC_022,
        ]),
        Reglamento::CODIGO_AC_054 => Reglamento::factory()->create([
            'codigo' => Reglamento::CODIGO_AC_054,
        ]),
        Reglamento::CODIGO_AC_055 => Reglamento::factory()->create([
            'codigo' => Reglamento::CODIGO_AC_055,
        ]),
    ]);

    $this->reglamento = $this->reglamentos->get(Reglamento::CODIGO_AC_022);
});

function sorteoTestExpediente(string $via, int $reglamentoId, ?string $numeroHojaRuta = null): Expediente
{
    $creador = Usuario::factory()->create();

    return Expediente::create([
        'nurej_code' => 'NUREJ-'.strtoupper(Str::random(10)),
        'via' => $via,
        'numero_hoja_ruta' => $numeroHojaRuta,
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'),
        'resumen_hechos' => 'Hechos de prueba del sorteo probabilístico.',
        'fecha_ingreso' => now(),
        'creado_por' => $creador->id,
    ]);
}

function sorteoTestRol(string $codigo): Rol
{
    return Rol::factory()->create(['codigo' => $codigo]);
}

function sorteoTestUsuarioDeRol(Rol $rol, bool $activo = true): Usuario
{
    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => $activo]);
}

it('mapea el reglamento del expediente al rol participante del sorteo', function () {
    $servicio = new SorteoAlgorithmService;

    expect($servicio->rolParaReglamento($this->reglamentos->get(Reglamento::CODIGO_AC_022)))->toBe(Rol::CODIGO_TECNICO)
        ->and($servicio->rolParaReglamento($this->reglamentos->get(Reglamento::CODIGO_AC_054)))->toBe(Rol::CODIGO_AUD_JURIDICO)
        ->and($servicio->rolParaReglamento($this->reglamentos->get(Reglamento::CODIGO_AC_055)))->toBe(Rol::CODIGO_AUD_FINANCIERO);
});

it('lanza ValidationException para un reglamento no soportado', function () {
    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_999_2099']);

    (new SorteoAlgorithmService)->rolParaReglamento($reglamento);
})->throws(ValidationException::class);

it('considera solo usuarios activos del rol correspondiente al reglamento', function (string $codigoReglamento, string $codigoRol) {
    $rol = sorteoTestRol($codigoRol);
    $activo = sorteoTestUsuarioDeRol($rol);
    $inactivo = sorteoTestUsuarioDeRol($rol, activo: false);
    sorteoTestUsuarioDeRol(sorteoTestRol(Rol::CODIGO_ENCARGADA));

    $expediente = sorteoTestExpediente(
        Expediente::VIA_INGRESO_VERBAL,
        $this->reglamentos->get($codigoReglamento)->id,
    );

    $ids = (new SorteoAlgorithmService)->candidatos($expediente)->pluck('id')->all();

    expect($ids)->toBe([$activo->id])
        ->and($ids)->not->toContain($inactivo->id);
})->with([
    [Reglamento::CODIGO_AC_022, Rol::CODIGO_TECNICO],
    [Reglamento::CODIGO_AC_054, Rol::CODIGO_AUD_JURIDICO],
    [Reglamento::CODIGO_AC_055, Rol::CODIGO_AUD_FINANCIERO],
]);

it('el origen de ingreso no modifica los candidatos del reglamento', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    $tecnico = sorteoTestUsuarioDeRol($rolTecnico);
    sorteoTestUsuarioDeRol(sorteoTestRol(Rol::CODIGO_AUD_JURIDICO));

    $servicio = new SorteoAlgorithmService;
    $verbal = sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id);
    $distrital = sorteoTestExpediente(
        Expediente::VIA_INGRESO_DISTRITAL,
        $this->reglamento->id,
        'HR-2026-0001',
    );

    expect($servicio->candidatos($verbal)->pluck('id')->all())->toBe([$tecnico->id])
        ->and($servicio->candidatos($distrital)->pluck('id')->all())->toBe([$tecnico->id]);
});

it('calcula tickets de ruleta inversa: el menos cargado recibe mas y el mas cargado mantiene margen', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    $a = sorteoTestUsuarioDeRol($rolTecnico);
    $b = sorteoTestUsuarioDeRol($rolTecnico);
    $c = sorteoTestUsuarioDeRol($rolTecnico);

    SorteoPeso::factory()->create(['usuario_id' => $a->id, 'reglamento_id' => $this->reglamento->id, 'peso' => 5]);
    SorteoPeso::factory()->create(['usuario_id' => $b->id, 'reglamento_id' => $this->reglamento->id, 'peso' => 2]);

    $entradas = (new SorteoAlgorithmService)->calcularTickets(
        new Collection([$a, $b, $c]),
        $this->reglamento->id,
    );

    $tickets = collect($entradas)->keyBy(fn ($e) => $e['usuario']->id)
        ->map(fn ($e) => $e['tickets']);

    expect($tickets[$a->id])->toBe(1)
        ->and($tickets[$b->id])->toBe(4)
        ->and($tickets[$c->id])->toBe(6);
});

it('selecciona al ganador ponderado de forma determinista con RNG inyectado', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    $a = sorteoTestUsuarioDeRol($rolTecnico);
    $b = sorteoTestUsuarioDeRol($rolTecnico);

    SorteoPeso::factory()->create(['usuario_id' => $a->id, 'reglamento_id' => $this->reglamento->id, 'peso' => 5]);

    $primerTicket = new SorteoAlgorithmService(randomInt: fn (int $max): int => 1);
    $ultimoTicket = new SorteoAlgorithmService(randomInt: fn (int $max): int => $max);

    expect($primerTicket->sortear(sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id))->id)->toBe($a->id)
        ->and($ultimoTicket->sortear(sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id))->id)->toBe($b->id);
});

it('incrementa el peso del ganador por reglamento con una unica fila por pareja', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    $ganador = sorteoTestUsuarioDeRol($rolTecnico);

    $servicio = new SorteoAlgorithmService(randomInt: fn (int $max): int => 1);

    $servicio->sortear(sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id));
    $servicio->sortear(sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id));
    $servicio->sortear(sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id));

    $registro = SorteoPeso::where('usuario_id', $ganador->id)
        ->where('reglamento_id', $this->reglamento->id)
        ->first();

    expect(SorteoPeso::count())->toBe(1)
        ->and($registro)->not->toBeNull()
        ->and($registro->peso)->toBe(3);
});

it('rechaza el sorteo cuando no hay candidatos activos para el reglamento', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    sorteoTestUsuarioDeRol($rolTecnico, activo: false);

    (new SorteoAlgorithmService)->sortear(sorteoTestExpediente(Expediente::VIA_INGRESO_VERBAL, $this->reglamento->id));
})->throws(ValidationException::class);
