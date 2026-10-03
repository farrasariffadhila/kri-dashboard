<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cookie-based session auth (with CSRF protection) for requests coming from the SPA.
        $middleware->statefulApi();
        // API messages follow the SPA's language; prepended so route-binding 404s are localised too.
        $middleware->api(prepend: [SetLocaleFromHeader::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API clients always get JSON errors (e.g. 404 for an unknown department), even without an Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Do not leak internal class names ("No query results for model [App\Models\...]") to API clients.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => __('errors.not_found')], 404)
            : null);
    })->create();
