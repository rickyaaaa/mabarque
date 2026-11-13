<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * POST /api/auth/forgot-password — kirim link reset ke email (respons generik).
 * POST /api/auth/reset-password  — simpan password baru dari token email.
 */
class PasswordResetController extends Controller
{
    public function sendLink(Request $request, PasswordResetService $service): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate(
            ['email' => ['required', 'string', 'email', 'max:255']],
            ['email.email' => 'Masukkan alamat email yang valid, contoh kamu@email.com.']
        );

        return response()->json(['message' => $service->sendLink($validated['email'])]);
    }

    public function reset(Request $request, PasswordResetService $service): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'Kedua password belum sama.',
        ]);

        $service->reset($validated);

        return response()->json([
            'message' => 'Password diganti. Sesi di perangkat lain sudah dikeluarkan — silakan masuk lagi.',
            'redirect' => '/login',
        ]);
    }
}
