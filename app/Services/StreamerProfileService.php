<?php

namespace App\Services;

use App\Models\User;

/**
 * Profil streamer: data untuk halaman pengaturan (privat) dan identitas yang tampil
 * di halaman antrean publik & overlay (publik, tanpa data sensitif).
 */
class StreamerProfileService
{
    /** Warna aksen yang tersedia di halaman pengaturan profil. */
    public const ACCENTS = ['#f8a917', '#d82a1c', '#238b39', '#6b63ff', '#1f9bd1'];

    public const MAIN_ROLE_LABELS = [
        'jungler' => 'Jungler',
        'roamer' => 'Roamer',
        'mid' => 'Mid Lane',
        'gold' => 'Gold Lane',
        'exp' => 'EXP Lane',
        'flex' => 'Flex',
    ];

    /** @param  array<string, mixed>  $attributes  data yang sudah divalidasi */
    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user->refresh();
    }

    /** Hanya streamer dengan akses beta aktif yang punya halaman publik. */
    public function isPublic(User $user): bool
    {
        return $user->isStreamer() && $user->beta_status === User::BETA_ACTIVE;
    }

    public function assertPublic(User $user): void
    {
        abort_unless($this->isPublic($user), 404);
    }

    /** Untuk halaman pengaturan profil milik streamer sendiri. */
    public function settings(User $user): array
    {
        return [
            'channel_name' => $user->channel_name,
            'slug' => $user->slug,
            'tagline' => $user->tagline,
            'main_role' => $user->main_role,
            'live_schedule' => $user->live_schedule,
            'avatar_initials' => $user->avatar_initials,
            'profile_accent' => $user->profile_accent,
            'email' => $user->email,
            'public_url' => url('/'.$user->slug),
            'beta_status' => $user->beta_status,
        ];
    }

    /** Identitas publik — dipakai halaman antrean penonton & overlay. Tanpa email/role/status beta. */
    public function publicProfile(User $user): array
    {
        $active = $user->liveSessions()->active()->latest('started_at')->first(['id', 'started_at']);

        return [
            'channel_name' => $user->channel_name,
            'slug' => $user->slug,
            'tagline' => $user->tagline,
            'main_role' => $user->main_role ? self::MAIN_ROLE_LABELS[$user->main_role] ?? null : null,
            'live_schedule' => $user->live_schedule,
            'avatar_initials' => $user->avatar_initials_label,
            'accent' => $user->profile_accent,
            'is_live' => $active !== null,
            'live_since' => $active?->started_at,
        ];
    }
}
