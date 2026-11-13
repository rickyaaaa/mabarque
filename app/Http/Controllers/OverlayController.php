<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OverlayTokenService;
use App\Services\PublicQueueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Overlay OBS berbasis token (tanpa login).
 * GET /o/{token}          halaman browser source
 * GET /api/overlay/{token} data antrean + pengaturan tampilan (dipoll halaman overlay)
 */
class OverlayController extends Controller
{
    public function show(string $token, OverlayTokenService $tokenService, PublicQueueService $publicQueueService): JsonResponse
    {
        $overlayToken = $tokenService->resolve($token);

        if ($overlayToken === null) {
            return response()->json(['message' => 'Overlay token tidak valid.'], 404);
        }

        return response()->json(['data' => $this->payload($overlayToken->user, $publicQueueService)]);
    }

    public function page(string $token, OverlayTokenService $tokenService, PublicQueueService $publicQueueService): View
    {
        $overlayToken = $tokenService->resolve($token);
        abort_if($overlayToken === null, 404);

        return view('pages.overlay', ['token' => $token, 'initial' => $this->payload($overlayToken->user, $publicQueueService)]);
    }

    /** Hanya data yang aman tampil di layar live: tanpa id internal, email, atau nominal donasi. */
    private function payload(User $user, PublicQueueService $publicQueueService): array
    {
        $settings = $user->overlaySetting;
        $queue = $publicQueueService->forUser($user);

        return [
            'channel_name' => $user->channel_name,
            'overlay_settings' => [
                'accent_color' => $settings->accent_color ?? '#D82A1C',
                'font_scale' => (int) ($settings->font_scale ?? 100),
                'max_players' => (int) ($settings->max_players ?? 5),
                'show_now_playing' => (bool) ($settings->show_now_playing ?? true),
                'transparent_mode' => (bool) ($settings->transparent_mode ?? true),
            ],
            'queue' => [
                'status' => $queue['status'],
                'current' => $queue['current'] ?? null,
                'entries' => collect($queue['queue'])->map(fn (array $entry) => [
                    'position' => $entry['position'],
                    'player_name' => $entry['player_name'],
                    'status' => $entry['status'],
                    'matches_remaining' => $entry['matches_remaining'],
                    'provider' => $entry['provider'],
                ])->values()->all(),
            ],
        ];
    }
}
