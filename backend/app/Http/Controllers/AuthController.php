<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Session (cookie) authentication for the SPA, via Sanctum's stateful API. */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function login(Request $request): JsonResponse
    {
        // Plain string rules on purpose: the login never sends mail, and Laravel 11's `email` rule
        // is affected by a CRLF advisory (CVE-2026-48019).
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'message' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ], 429);
        }

        if (! Auth::guard('web')->attempt($credentials)) {
            RateLimiter::hit($key, 60);
            ActivityLog::record('auth.login_failed', properties: ['email' => Str::lower($credentials['email'])]);

            // Same message whether the email exists or not, so accounts cannot be enumerated.
            return response()->json(['message' => __('auth.failed')], 422);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = Auth::guard('web')->user()->load('department');
        ActivityLog::record('auth.login', $user, user: $user);

        return response()->json(['user' => $user->toProfile()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        ActivityLog::record('auth.logout', $user, user: $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('department')->toProfile()]);
    }
}
