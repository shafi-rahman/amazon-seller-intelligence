<?php

use App\Http\Middleware\SecurityHeaders;
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
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Behind nginx / a load balancer terminating TLS, honour X-Forwarded-*
        // so the app sees the real scheme/host (correct https URLs + secure cookies).
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'workspace' => \App\Http\Middleware\ScopeToWorkspace::class,
            'role'      => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'=> \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // For unexpected server errors on the API, return a generic message so we
        // never leak stack traces / config / env. Framework HttpExceptions
        // (404/403/422/429 …) keep their normal JSON shape. Only applies when
        // debug is off (production); local keeps full detail for development.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException) {
                return null; // let the framework render these normally
            }
            report($e);
            return response()->json(['message' => 'Server error. Please try again.'], 500);
        });
    })->create();
