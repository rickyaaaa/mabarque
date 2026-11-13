<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kepemilikan langsung lewat kolom user_id (multi-tenant per streamer).
 *
 * Model yang memakai trait ini: VipPackage, LiveSession, PlatformConnection,
 * OverlaySetting, OverlayToken, DonationNotification.
 */
trait OwnedByUser
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Batasi query ke data milik streamer tertentu. */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where($this->qualifyColumn('user_id'), $user instanceof User ? $user->getKey() : $user);
    }

    /** Bandingkan sebagai integer supaya aman walau driver DB mengembalikan string. */
    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->getKey();
    }
}
