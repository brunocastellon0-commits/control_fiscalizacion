<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider dedicado de auditoría (COORDINACION §2.2): concentra los
 * listeners de auditoría fuera de la transacción principal (`Gate::after`
 * y `RequestHandled` con conexión de BD dedicada) para no modificar
 * AppServiceProvider (propiedad de Brayan).
 *
 * El cuerpo se implementa en la tarea B2.4 (auditoría append-only);
 * desde B0.1 solo se declara y registra el provider.
 */
class AuditoriaServiceProvider extends ServiceProvider
{
    /**
     * Registrar los servicios del contenedor.
     */
    public function register(): void
    {
        //
    }

    /**
     * Boot de los listeners de auditoría (B2.4).
     */
    public function boot(): void
    {
        //
    }
}
