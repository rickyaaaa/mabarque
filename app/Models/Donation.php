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
        'currency',
        'original_amount',
        'message',
        'status',
        'payload',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'original_amount' => 'float',
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

    /** Donasi dibayar dalam mata uang selain rupiah (mis. MYR dari viewer Malaysia). */
    public function isForeign(): bool
    {
        return ($this->currency ?: 'IDR') !== 'IDR';
    }

    /** Nominal untuk ditampilkan: "Rp 25.000", atau "MYR 25 · ≈ Rp 89.300" untuk mata uang asing. */
    public function amountLabel(): string
    {
        return self::formatAmount((int) $this->amount, $this->currency, $this->original_amount);
    }

    public static function formatAmount(int $rupiah, ?string $currency = 'IDR', ?float $original = null): string
    {
        $idr = 'Rp '.number_format($rupiah, 0, ',', '.');
        $currency = strtoupper((string) ($currency ?: 'IDR'));

        if ($currency === 'IDR' || $original === null) {
            return $idr;
        }

        $foreign = $currency.' '.number_format($original, fmod($original, 1.0) == 0.0 ? 0 : 2, ',', '.');

        return $rupiah > 0 ? $foreign.' · ≈ '.$idr : $foreign;
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
