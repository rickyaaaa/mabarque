<?php

namespace App\Services;

use App\Models\OverlayToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Token link overlay OBS (/o/{token}). Lookup memakai hash SHA-256; token juga disimpan terenkripsi
 * supaya streamer bisa menyalin ulang link yang sama tanpa harus mengganti browser source di OBS.
 */
class OverlayTokenService
{
    /** Buat token baru (mencabut link lama). */
    public function issue(User $user): string
    {
        $token = Str::random(48);

        OverlayToken::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['token_hash' => hash('sha256', $token), 'token_encrypted' => $token, 'revoked_at' => null]
        );

        return $token;
    }

    /** Token aktif milik streamer; dibuat otomatis bila belum ada. */
    public function current(User $user): string
    {
        $existing = $user->overlayToken()->whereNull('revoked_at')->first();

        return filled($existing?->token_encrypted) ? $existing->token_encrypted : $this->issue($user);
    }

    public function url(string $token): string
    {
        return url('/o/'.$token);
    }

    public function resolve(string $token): ?OverlayToken
    {
        $overlayToken = OverlayToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->with('user')
            ->first();

        if ($overlayToken !== null) {
            $overlayToken->forceFill(['last_used_at' => now()])->save();
        }

        return $overlayToken;
    }
}
