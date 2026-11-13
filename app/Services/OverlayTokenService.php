<?php

namespace App\Services;

use App\Models\OverlayToken;
use App\Models\User;
use Illuminate\Support\Str;

class OverlayTokenService
{
    public function issue(User $user): string
    {
        $token = Str::random(48);

        OverlayToken::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['token_hash' => hash('sha256', $token), 'revoked_at' => null]
        );

        return $token;
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
