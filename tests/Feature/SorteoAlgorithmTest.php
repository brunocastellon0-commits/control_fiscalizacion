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
    $this->reglamento = Reglamento::factory()->create();
});

function sorteoTestExpediente(string $via, int $reglamentoId): Expediente
{
    $creador = Usuario::factory()->create();

    return Expediente::create([
        'nurej_code' => 'NUREJ-'.strtoupper(Str::random(10)),
        'via' => $via,
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

it('mapea la via del expediente al rol participante del sorteo', function () {
    $servicio = new SorteoAlgorithmService;

    expect($servicio->rolParaVia('TECNICO'))->toBe(Rol::CODIGO_TECNICO)
        ->and($servicio->rolParaVia('JURIDICO'))->toBe(Rol::CODIGO_AUD_JURIDICO)
        ->and($servicio->rolParaVia('FINANCIERO'))->toBe(Rol::CODIGO_AUD_FINANCIERO);
});

it('lanza ValidationException para una via no soportada', function () {
    (new SorteoAlgorithmService)->rolParaVia('INVALIDAR');
})->throws(ValidationException::class);

it('considera solo usuarios activos del rol de la via como candidatos', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    sorteoTestUsuarioDeRol($rolTecnico);
    $inactivo = sorteoTestUsuarioDeRol($rolTecnico, activo: false);
    sorteoTestUsuarioDeRol(sorteoTestRol(Rol::CODIGO_AUD_JURIDICO));

    $expediente = sorteoTestExpediente('TECNICO', $this->reglamento->id);

    $ids = (new SorteoAlgorithmService)->candidatos($expediente)->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids)->not->toContain($inactivo->id);
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

    expect($primerTicket->sortear(sorteoTestExpediente('TECNICO', $this->reglamento->id))->id)->toBe($a->id)
        ->and($ultimoTicket->sortear(sorteoTestExpediente('TECNICO', $this->reglamento->id))->id)->toBe($b->id);
});

it('incrementa el peso del ganador por reglamento con una unica fila por pareja', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    $ganador = sorteoTestUsuarioDeRol($rolTecnico);

    $servicio = new SorteoAlgorithmService(randomInt: fn (int $max): int => 1);

    $servicio->sortear(sorteoTestExpediente('TECNICO', $this->reglamento->id));
    $servicio->sortear(sorteoTestExpediente('TECNICO', $this->reglamento->id));
    $servicio->sortear(sorteoTestExpediente('TECNICO', $this->reglamento->id));

    $registro = SorteoPeso::where('usuario_id', $ganador->id)
        ->where('reglamento_id', $this->reglamento->id)
        ->first();

    expect(SorteoPeso::count())->toBe(1)
        ->and($registro)->not->toBeNull()
        ->and($registro->peso)->toBe(3);
});

it('rechaza el sorteo cuando no hay candidatos activos para la via', function () {
    $rolTecnico = sorteoTestRol(Rol::CODIGO_TECNICO);
    sorteoTestUsuarioDeRol($rolTecnico, activo: false);

    (new SorteoAlgorithmService)->sortear(sorteoTestExpediente('TECNICO', $this->reglamento->id));
})->throws(ValidationException::class);
