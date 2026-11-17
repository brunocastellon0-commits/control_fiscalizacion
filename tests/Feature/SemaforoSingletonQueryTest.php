<?php

use App\Services\SemaforoPlazoService;
use Illuminate\Support\Facades\DB;

function contarConsultasDeCalendario(array $queries): int
{
    return count(array_filter($queries, function (string $sql): bool {
        return str_contains($sql, 'feriados')
            || str_contains($sql, 'suspensiones_plazo');
    }));
}

it('comparte una sola instancia de SemaforoPlazoService', function () {
    expect(app(SemaforoPlazoService::class))
        ->toBe(app(SemaforoPlazoService::class));
});

it('no recarga feriados ni suspensiones al resolver el servicio dos veces', function () {
    app()->forgetInstance(SemaforoPlazoService::class);

    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(SemaforoPlazoService::class);
    $primeraResolucion = contarConsultasDeCalendario($queries);

    app(SemaforoPlazoService::class);
    $segundaResolucion = contarConsultasDeCalendario($queries);

    expect($primeraResolucion)->toBe(2)
        ->and($segundaResolucion)->toBe($primeraResolucion);
});
