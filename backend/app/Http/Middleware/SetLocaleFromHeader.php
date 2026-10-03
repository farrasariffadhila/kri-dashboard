<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the client's Accept-Language (sent by the SPA) for API messages; Indonesian by default. */
class SetLocaleFromHeader
{
    public const SUPPORTED = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // Always set it (also without a header), so a long-running worker never carries
        // one request's language into the next.
        $locale = $request->headers->has('Accept-Language') ? $request->getPreferredLanguage(self::SUPPORTED) : null;
        app()->setLocale($locale ?? config('app.locale'));

        return $next($request);
    }
}
