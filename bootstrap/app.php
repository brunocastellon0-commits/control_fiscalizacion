<?php

use App\Exceptions\CannotDeriveNurejException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * AUD-0038 (RN-10): derivar un NUREJ Hijo desde un expediente ya
         * derivado es una regla de negocio, no un error de servidor. El mismo
         * contrato 422 del endpoint recibe el rechazo con su mensaje.
         */
        $exceptions->render(function (CannotDeriveNurejException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        });

        $exceptions->dontReport(CannotDeriveNurejException::class);
    })->create();
