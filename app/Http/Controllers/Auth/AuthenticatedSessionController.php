<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\StreamerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sesi login streamer (guard "web", berbasis session + cookie).
 *
 * POST /api/auth/login   — masuk
 * POST /api/auth/logout  — keluar & cabut sesi
 * GET  /api/auth/me      — akun yang sedang masuk (termasuk beta_status untuk gating UI)
 */
class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request, StreamerRegistrationService $presenter): JsonResponse
    {
        $user = $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => $presenter->present($user),
            'redirect' => $user->hasActiveBeta() ? '/live-session' : '/account',
        ]);
    }

    public function show(Request $request, StreamerRegistrationService $presenter): JsonResponse
    {
        return response()->json(['data' => $presenter->present($request->user())]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Kamu sudah keluar.']);
    }
}
