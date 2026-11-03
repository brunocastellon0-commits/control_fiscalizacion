<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bloquea la ejecucion de tests contra una base que no sea exclusiva de
     * pruebas. RefreshDatabase ejecuta migrate:fresh y destruye los datos,
     * por lo que este guard debe correr ANTES de parent::setUp().
     */
    protected function setUp(): void
    {
        $database = (string) (
            getenv('DB_DATABASE')
            ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? '')
        );

        if ($database === '' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                'ABORTADO: los tests intentan usar la base "'.$database.'". '
                .'Solo se permite una base cuyo nombre termine en "_test" '
                .'(ver phpunit.xml: DB_DATABASE=control_fiscalizacion_test).'
            );
        }

        parent::setUp();
    }
}
