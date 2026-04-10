<?php

use App\Exceptions\LicenseDeniedException;
use App\Exceptions\LicenseException;
use App\Exceptions\LicenseExpiredException;
use App\Exceptions\LicenseSuspendedException;
use App\Exceptions\LicenseSystemUnavailableException;
use App\Http\Middleware\CheckLicense;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->throttleApi('60,1');

        $middleware->alias([
            'license' => CheckLicense::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Acción no autorizada.'], 403);
            }
        });

        $exceptions->render(function (LicenseDeniedException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (LicenseSuspendedException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (LicenseExpiredException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (LicenseSystemUnavailableException $e, Request $request) {
            return $e->render();
        });

        $exceptions->render(function (LicenseException $e, Request $request) {
            return $e->render();
        });
    })->create();
