<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStreamerRequest;
use App\Services\StreamerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredStreamerController extends Controller
{
    /** POST /api/auth/register — daftar akun streamer lalu langsung masuk (sesi web). */
    public function store(RegisterStreamerRequest $request, StreamerRegistrationService $service): JsonResponse
    {
        $user = $service->register($request->validated());

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => $service->present($user),
            'message' => $user->hasActiveBeta()
                ? 'Akun streamer berhasil dibuat.'
                : 'Akun dibuat. Akses private alpha akan aktif setelah disetujui admin.',
        ], 201);
    }
}
