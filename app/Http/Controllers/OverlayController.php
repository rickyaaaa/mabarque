<?php

namespace App\Http\Controllers;

use App\Services\OverlayTokenService;
use App\Services\PublicQueueService;
use Illuminate\Http\JsonResponse;

class OverlayController extends Controller
{
    public function show(string $token, OverlayTokenService $tokenService, PublicQueueService $publicQueueService): JsonResponse
    {
        $overlayToken = $tokenService->resolve($token);

        if ($overlayToken === null) {
            return response()->json(['message' => 'Overlay token tidak valid.'], 404);
        }

        return response()->json([
            'data' => [
                'queue' => $publicQueueService->forUser($overlayToken->user),
                'overlay_settings' => $overlayToken->user->overlaySetting,
            ],
        ]);
    }
}
