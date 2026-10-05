<?php

use App\Models\Adjunto;
use App\Models\Expediente;
use App\Models\Usuario;
use Illuminate\Notifications\Notifiable;

/**
 * Todas las clases modelo de app/Models (B0.1: sin dependencia del contenedor
 * Laravel — este archivo corre en el TestCase base de PHPUnit).
 *
 * @return array<int, class-string>
 */
function modelSecurityClasses(): array
{
    $dir = dirname(__DIR__, 2).'/app/Models';
    $classes = [];

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'php') {
            continue;
        }

        $classes[] = 'App\\Models\\'.pathinfo($entry, PATHINFO_FILENAME);
    }

    sort($classes);

    return $classes;
}

/**
 * Columnas que jamás deben ser mass-assignables en ningún modelo.
 *
 * @return array<int, string>
 */
function modelSecurityForbiddenGlobally(): array
{
    return ['id', 'created_at', 'updated_at'];
}

it('carga al menos los 22 modelos esperados', function () {
    $classes = modelSecurityClasses();

    expect($classes)->toHaveCount(22);

    foreach ($classes as $class) {
        expect(class_exists($class))->toBeTrue("No existe la clase {$class}");
    }
});

it('ningun modelo define $guarded = []', function () {
    foreach (modelSecurityClasses() as $class) {
        $default = (new ReflectionClass($class))->getDefaultProperties();

        expect($default['guarded'] ?? null)
            ->not->toBe([], "{$class} define \$guarded = [] (asignación masiva sin protección)");
    }
});

it('ningun modelo expone columnas sensibles en $fillable', function () {
    $porModelo = [
        'App\Models\Usuario' => ['password_hash'],
        'App\Models\Adjunto' => ['hash_sha256'],
        'App\Models\Actuado' => ['hash_actuado', 'hash_anterior'],
    ];

    foreach (modelSecurityClasses() as $class) {
        $fillable = (new $class)->getFillable();

        foreach (modelSecurityForbiddenGlobally() as $columna) {
            expect($fillable)
                ->not->toContain($columna, "{$class} expone la columna sensible '{$columna}' en \$fillable");
        }

        foreach ($porModelo[$class] ?? [] as $columna) {
            expect($fillable)
                ->not->toContain($columna, "{$class} expone la columna sensible '{$columna}' en \$fillable");
        }
    }
});

it('Usuario usa el trait Notifiable', function () {
    expect(class_uses(Usuario::class))->toContain(Notifiable::class);
});

it('ignora el mass-assignment de password_hash en Usuario', function () {
    $usuario = new Usuario;
    $usuario->fill(['password_hash' => 'secreto', 'username' => 'operador']);

    expect($usuario->getAttributes())
        ->not->toHaveKey('password_hash')
        ->toHaveKey('username', 'operador');
});

it('ignora el mass-assignment de hash_sha256 en Adjunto', function () {
    $adjunto = new Adjunto;
    $adjunto->fill(['hash_sha256' => str_repeat('a', 64), 'nombre_original' => 'foja.pdf']);

    expect($adjunto->getAttributes())
        ->not->toHaveKey('hash_sha256')
        ->toHaveKey('nombre_original', 'foja.pdf');
});

it('ignora el mass-assignment de created_at en Expediente', function () {
    $expediente = new Expediente;
    $expediente->fill(['created_at' => '2020-01-01 00:00:00', 'via' => 'TECNICO']);

    expect($expediente->getAttributes())
        ->not->toHaveKey('created_at')
        ->toHaveKey('via', 'TECNICO');
});
