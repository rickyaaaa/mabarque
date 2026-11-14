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

    /** Jenis layanan: mabar bareng streamer (VIP) atau jasa mainin akun (joki). */
    public const SERVICE_TYPES = ['vip' => 'Mabar VIP', 'joki' => 'Mainin akun'];

    protected $fillable = [
        'live_session_id',
        'vip_package_id',
        'player_name',
        'mlbb_id',
        'service_type',
        'note',
        'provider',
        'donation_amount',
        'matches_total',
        'matches_played',
        'status',
        'position',
        'joined_at',
        'next_order',
    ];

    protected $casts = [
        'donation_amount' => 'integer',
        'matches_total' => 'integer',
        'matches_played' => 'integer',
        'position' => 'integer',
        'joined_at' => 'datetime',
        'next_order' => 'integer',
    ];

    protected $appends = ['matches_remaining'];

    protected static function booted(): void
    {
        // Pilihan "Next game" hanya berlaku selama pemain menunggu dengan status siap; begitu masuk party,
        // offline, dilewati, atau selesai, pilihannya gugur.
        static::saving(function (QueueEntry $entry): void {
            if ($entry->isDirty('status') && $entry->status !== 'ready') {
                $entry->next_order = null;
            }
        });
    }

    public function isPickedForNext(): bool
    {
        return $this->next_order !== null && $this->status === 'ready';
    }

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

    /** Nama untuk ditampilkan; pemain yang baru tercatat ID-nya saja ditampilkan sebagai "ID …". */
    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) $this->player_name);

        return $name !== '' ? $name : ($this->mlbb_id ? 'ID '.$this->mlbb_id : 'Tanpa nama');
    }

    public function getServiceLabelAttribute(): string
    {
        return self::SERVICE_TYPES[$this->service_type] ?? self::SERVICE_TYPES['vip'];
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
