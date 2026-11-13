<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStreamerProfileRequest;
use App\Models\User;
use App\Services\StreamerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/profile             — profil milik streamer yang login (halaman pengaturan)
 * PUT /api/profile             — simpan perubahan profil
 * GET /api/channels/{slug}     — identitas publik untuk halaman antrean & overlay
 */
class StreamerProfileController extends Controller
{
    public function show(Request $request, StreamerProfileService $service): JsonResponse
    {
        return response()->json(['data' => $service->settings($request->user())]);
    }

    public function update(UpdateStreamerProfileRequest $request, StreamerProfileService $service): JsonResponse
    {
        $user = $request->user();
        $slugChanged = $request->validated('slug') !== $user->slug;
        $user = $service->update($user, $request->validated());

        return response()->json([
            'data' => $service->settings($user),
            'message' => $slugChanged
                ? "Profil disimpan. Link publik kini mabarqueue.id/{$user->slug}."
                : 'Profil streamer disimpan.',
        ]);
    }

    public function publicShow(User $user, StreamerProfileService $service): JsonResponse
    {
        $service->assertPublic($user);

        return response()->json(['data' => $service->publicProfile($user)]);
    }
}
