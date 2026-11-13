<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_session_id',
        'vip_package_id',
        'player_name',
        'provider',
        'donation_amount',
        'matches_total',
        'matches_played',
        'status',
        'position',
        'joined_at',
    ];

    protected $casts = [
        'donation_amount' => 'integer',
        'matches_total' => 'integer',
        'matches_played' => 'integer',
        'position' => 'integer',
        'joined_at' => 'datetime',
    ];

    protected $appends = ['matches_remaining'];

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function vipPackage(): BelongsTo
    {
        return $this->belongsTo(VipPackage::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchRecord::class);
    }

    public function getMatchesRemainingAttribute(): int
    {
        return max(0, $this->matches_total - $this->matches_played);
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    /** Kepemilikan tidak langsung: data milik streamer pemilik LiveSession terkait. */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->whereHas('liveSession', fn (Builder $owner) => $owner->ownedBy($user));
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->live_session_id !== null && (bool) $this->liveSession?->isOwnedBy($user);
    }
}
