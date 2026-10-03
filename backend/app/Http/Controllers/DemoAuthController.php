<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Password-less "sign in as" for the portfolio demo. Only active when KRI_DEMO_MODE=true,
 * and only for accounts flagged is_demo. The session, roles, maker-checker and audit trail
 * behave exactly as with a normal sign-in.
 */
class DemoAuthController extends Controller
{
    /** GET /api/demo/accounts */
    public function accounts(): JsonResponse
    {
        $this->ensureEnabled();

        $users = User::query()
            ->where('is_demo', true)
            ->with('department:id,name')
            ->orderByRaw("CASE role WHEN 'director' THEN 0 WHEN 'unit_owner' THEN 1 WHEN 'auditor' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users->map->toProfile()->values()]);
    }

    /** POST /api/demo/login */
    public function login(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $user = User::query()->where('is_demo', true)->find($data['user_id'])
            ?? throw new NotFoundHttpException();

        // Switching persona: end the previous identity first, then start a fresh session.
        if ($previous = Auth::guard('web')->user()) {
            ActivityLog::record('auth.logout', $previous, user: $previous);
        }
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        ActivityLog::record('auth.demo_login', $user, user: $user);

        return response()->json(['user' => $user->load('department')->toProfile()]);
    }

    private function ensureEnabled(): void
    {
        if (! config('kri.demo_mode')) {
            throw new NotFoundHttpException();
        }
    }
}
