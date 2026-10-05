<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['color_theme', 'sidebar_state']);

        // Dokploy'da uygulama Traefik'in arkasında çalışır; HTTPS ve gerçek IP başlıklarına güven.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            'feature' => EnsureFeatureEnabled::class,
            'role' => EnsureUserHasRole::class,
        ]);

        // Rota model bağlama (/persons/{person}) acente kapsamı kurulduktan sonra çalışsın;
        // aksi halde başka acentenin kaydı URL ile bulunabilir.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureTenantContext::class,
        );

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
