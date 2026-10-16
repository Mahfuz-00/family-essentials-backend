<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Mobile client surface. Same domain and same controllers as the web
            // dashboard, but its own prefix and its own token ability, so a token
            // minted for one client cannot be replayed against the other.
            Route::middleware('api')
                ->prefix('mobile')
                ->group(base_path('routes/mobile.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the tenant context before route-model binding runs.
        $middleware->prependToGroup('api', App\Http\Middleware\IdentifyTenant::class);

        $middleware->alias([
            'tenant' => App\Http\Middleware\RequireTenant::class,
            'platform.admin' => App\Http\Middleware\EnsurePlatformAdmin::class,
            'tenancy.bootstrap' => App\Http\Middleware\BootstrapTenancy::class,
            'abilities' => Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('mobile/*')
                || $request->expectsJson(),
        );
    })->create();
