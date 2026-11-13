<?php

namespace App\Models;

use App\Notifications\ResetStreamerPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun streamer / Super Admin sekaligus pemilik (tenant) semua data domain.
 *
 * Kepemilikan langsung (kolom user_id): vipPackages, liveSessions, platformConnections,
 * overlaySetting, overlayToken, donationNotifications.
 * Kepemilikan tidak langsung: queueEntries & matchRecords lewat live_sessions,
 * donations lewat platform_connections.
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_STREAMER = 'streamer';

    public const BETA_PENDING = 'pending';
    public const BETA_ACTIVE = 'active';
    public const BETA_INACTIVE = 'inactive';

    public const MAIN_ROLES = ['jungler', 'roamer', 'mid', 'gold', 'exp', 'flex'];

    /** Slug yang tidak boleh dipakai streamer (bentrok dengan rute/brand). */
    public const RESERVED_SLUGS = ['admin', 'api', 'login', 'logout', 'signup', 'daftar', 'masuk', 'o', 'overlay', 'settings', 'mabarqueue', 'support', 'help'];

    /** Format slug: 3–24 karakter, huruf kecil/angka, tanda hubung hanya di tengah. */
    public const SLUG_PATTERN = '/^(?=.{3,24}$)[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** role & beta_status sengaja tidak fillable: hanya Super Admin yang boleh mengubahnya. */
    protected $fillable = [
        'name',
        'email',
        'password',
        'channel_name',
        'slug',
        'tagline',
        'main_role',
        'live_schedule',
        'avatar_initials',
        'profile_accent',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'role' => self::ROLE_STREAMER,
        'beta_status' => self::BETA_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Kepemilikan langsung ────────────────────────────────────────────

    public function vipPackages(): HasMany
    {
        return $this->hasMany(VipPackage::class);
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function activeLiveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class)->ofMany(['started_at' => 'max', 'id' => 'max'], fn (Builder $query) => $query->where('status', 'active'));
    }

    public function platformConnections(): HasMany
    {
        return $this->hasMany(PlatformConnection::class);
    }

    public function overlaySetting(): HasOne
    {
        return $this->hasOne(OverlaySetting::class);
    }

    public function overlayToken(): HasOne
    {
        return $this->hasOne(OverlayToken::class);
    }

    public function donationNotifications(): HasMany
    {
        return $this->hasMany(DonationNotification::class);
    }

    // ── Kepemilikan tidak langsung ──────────────────────────────────────

    public function queueEntries(): HasManyThrough
    {
        return $this->hasManyThrough(QueueEntry::class, LiveSession::class);
    }

    public function matchRecords(): HasManyThrough
    {
        return $this->hasManyThrough(MatchRecord::class, LiveSession::class);
    }

    public function donations(): HasManyThrough
    {
        return $this->hasManyThrough(Donation::class, PlatformConnection::class);
    }

    // ── Peran & akses ───────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStreamer(): bool
    {
        return $this->role === self::ROLE_STREAMER;
    }

    public function hasActiveBeta(): bool
    {
        return $this->isSuperAdmin() || $this->beta_status === self::BETA_ACTIVE;
    }

    /**
     * Cek kepemilikan untuk model domain apa pun (langsung maupun lewat relasi).
     * Super Admin TIDAK otomatis dianggap pemilik — akses admin diatur terpisah.
     */
    public function owns(Model $model): bool
    {
        return method_exists($model, 'isOwnedBy') && $model->isOwnedBy($this);
    }

    public function scopeStreamers(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_STREAMER);
    }

    public function scopeBetaActive(Builder $query): Builder
    {
        return $query->where('beta_status', self::BETA_ACTIVE);
    }

    /** Email reset password berbahasa Indonesia yang mengarah ke halaman lupa-password frontend. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetStreamerPassword($token));
    }

    /** Inisial avatar: pakai yang diset streamer, kalau kosong ambil dari nama channel. */
    public function getAvatarInitialsLabelAttribute(): string
    {
        if ($this->avatar_initials) {
            return strtoupper($this->avatar_initials);
        }

        $words = preg_split('/\s+/', trim((string) $this->channel_name)) ?: [];
        $initials = implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice(array_filter($words), 0, 2)));

        return mb_strtoupper($initials) ?: 'MQ';
    }
}
