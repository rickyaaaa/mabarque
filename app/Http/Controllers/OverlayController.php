<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OverlayTokenService;
use App\Services\PublicQueueService;
use App\Services\VipPackageService;
use Illuminate\Contracts\View\View;
use App\Services\LiveSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Overlay OBS berbasis token (tanpa login).
 * GET /o/{token}          halaman browser source
 * GET /api/overlay/{token} data antrean + pengaturan tampilan (dipoll halaman overlay)
 * GET /o/{token}/bg/{file} gambar latar overlay yang diunggah streamer
 * POST /api/overlay/{token}/obs event OBS (mulai/berhenti streaming) → sesi live ikut mulai/berakhir
 */
class OverlayController extends Controller
{
    public function show(string $token, OverlayTokenService $tokenService, PublicQueueService $publicQueueService): JsonResponse
    {
        $overlayToken = $tokenService->resolve($token);

        if ($overlayToken === null) {
            return response()->json(['message' => 'Overlay token tidak valid.'], 404);
        }

        return response()->json(['data' => $this->payload($overlayToken->user, $publicQueueService, $token)]);
    }

    public function page(string $token, OverlayTokenService $tokenService, PublicQueueService $publicQueueService): View
    {
        $overlayToken = $tokenService->resolve($token);
        abort_if($overlayToken === null, 404);

        return view('pages.overlay', ['token' => $token, 'initial' => $this->payload($overlayToken->user, $publicQueueService, $token)]);
    }

    /**
     * OBS mulai streaming → sesi live dimulai (bila belum ada yang berjalan). OBS berhenti → sesi diakhiri,
     * hanya bila streamer mengaktifkan "akhiri otomatis". Keduanya bisa dimatikan di pengaturan overlay.
     */
    public function obsEvent(Request $request, string $token, OverlayTokenService $tokenService, LiveSessionService $sessions): JsonResponse
    {
        $user = $tokenService->resolve($token)?->user;

        if ($user === null || ! $user->hasActiveBeta()) {
            return response()->json(['message' => 'Overlay token tidak valid.'], 404);
        }

        $event = $request->validate(['event' => ['required', 'in:started,stopped']])['event'];
        $settings = $user->overlaySetting;
        $active = $user->liveSessions()->active()->latest('started_at')->first();

        if ($event === 'started') {
            if (! ($settings->obs_auto_start ?? true)) {
                return response()->json(['data' => ['action' => 'disabled']]);
            }

            if ($active !== null) {
                return response()->json(['data' => ['action' => 'already_live', 'session' => $active->title]]);
            }

            $session = $sessions->start($user, 'Mabar VIP '.now()->translatedFormat('l, j F'));

            return response()->json(['data' => ['action' => 'started', 'session' => $session->title]], 201);
        }

        if (! ($settings->obs_auto_end ?? false)) {
            return response()->json(['data' => ['action' => 'disabled']]);
        }

        if ($active === null) {
            return response()->json(['data' => ['action' => 'not_live']]);
        }

        $sessions->end($user, $active);

        return response()->json(['data' => ['action' => 'ended', 'session' => $active->title]]);
    }

    /** Gambar latar: hanya file yang sedang dipakai streamer pemilik token (nama file acak, jadi aman di-cache). */
    public function background(string $token, string $file, OverlayTokenService $tokenService): BinaryFileResponse
    {
        $overlayToken = $tokenService->resolve($token);
        $path = $overlayToken?->user?->overlaySetting?->background_image;

        abort_if($path === null || basename($path) !== $file || ! Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=604800, immutable']);
    }

    public static function backgroundUrl(string $token, ?string $path): ?string
    {
        return $path ? route('overlay.background', ['token' => $token, 'file' => basename($path)]) : null;
    }

    /** Harga ringkas untuk layar live: 25000 → "25K", 12500 → "12.5K", 1500000 → "1.5JT". */
    public static function compactPrice(int $price): string
    {
        $format = fn (float $value) => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return match (true) {
            $price >= 1_000_000 => $format($price / 1_000_000).'JT',
            $price >= 1_000 => $format($price / 1_000).'K',
            default => (string) $price,
        };
    }

    /** Hanya data yang aman tampil di layar live: tanpa id internal, email, atau nominal donasi. */
    private function payload(User $user, PublicQueueService $publicQueueService, string $token): array
    {
        $settings = $user->overlaySetting;
        $queue = $publicQueueService->forUser($user);
        $live = $user->liveSessions()->active()->latest('started_at')->first();

        return [
            'channel_name' => $user->channel_name,
            'overlay_settings' => [
                'accent_color' => $settings->accent_color ?? '#C5F02E',
                'font_scale' => (int) ($settings->font_scale ?? 100),
                'max_players' => (int) ($settings->max_players ?? 5),
                'show_now_playing' => (bool) ($settings->show_now_playing ?? true),
                'transparent_mode' => (bool) ($settings->transparent_mode ?? true),
                'show_score' => (bool) ($settings->show_score ?? true),
                'show_packages' => (bool) ($settings->show_packages ?? true),
                'layout' => ($settings->layout ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical',
                'card_width' => (int) ($settings->card_width ?? 440),
                'card_height' => $settings?->card_height ? (int) $settings->card_height : null,
                // Mengisi penuh Browser Source OBS (default) — ukuran cukup diatur di OBS.
                'fit_source' => (bool) ($settings->fit_source ?? true),
                // Latar kartu: warna (null = putih bawaan), gambar opsional, dan tebal lapisan warna di atas gambar.
                'background_color' => $settings?->background_color,
                'background_image_url' => self::backgroundUrl($token, $settings?->background_image),
                'background_dim' => (int) ($settings->background_dim ?? 35),
            ],
            // Skor Win/Lose sesi live yang sedang berjalan (null saat belum live).
            'score' => $live ? ['wins' => (int) $live->wins, 'losses' => (int) $live->losses] : null,
            'packages' => app(VipPackageService::class)->listForUser($user, true)
                ->map(fn ($package) => [
                    'name' => $package->name,
                    'price' => self::compactPrice((int) $package->price),
                    'games' => (int) $package->match_count,
                    'gets' => $package->getsLabel(),
                ])->values()->all(),
            'queue' => [
                'status' => $queue['status'],
                'current' => $queue['current'] ?? null,
                'party' => $queue['party'] ?? [],
                // Di layar live: party yang sedang main, lalu urutan masuk party berikutnya.
                'entries' => collect($queue['status'] === 'live' ? $queue['queue'] : [])
                    ->filter(fn (array $entry) => $entry['status'] === 'playing' || $entry['next_rank'] !== null)
                    ->sortBy(fn (array $entry) => $entry['status'] === 'playing' ? [0, $entry['position']] : [1, $entry['next_rank']])
                    ->map(fn (array $entry) => [
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
