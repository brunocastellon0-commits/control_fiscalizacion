<?php

namespace App\Providers;

use App\Services\SemaforoPlazoService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * SemaforoPlazoService es stateless; su dependencia PlazoCalculatorService
         * cachea feriados y suspensiones en el constructor. Compartir una sola
         * instancia evita recargar esas colecciones en cada resolucion.
         */
        $this->app->singleton(SemaforoPlazoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
