<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_connection_id',
        'live_session_id',
        'vip_package_id',
        'queue_entry_id',
        'external_id',
        'provider',
        'donor_name',
        'donor_email',
        'amount',
        'status',
        'payload',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function platformConnection(): BelongsTo
    {
        return $this->belongsTo(PlatformConnection::class);
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function vipPackage(): BelongsTo
    {
        return $this->belongsTo(VipPackage::class);
    }

    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DonationNotification::class);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'review']);
    }

    /** Kepemilikan tidak langsung: data milik streamer pemilik PlatformConnection terkait. */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->whereHas('platformConnection', fn (Builder $owner) => $owner->ownedBy($user));
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->platform_connection_id !== null && (bool) $this->platformConnection?->isOwnedBy($user);
    }
}
