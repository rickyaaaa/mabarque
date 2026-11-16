<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipPackage extends Model
{
    use HasFactory;
    use OwnedByUser;

    /** Jenis paket: mabar = harga → jumlah game (masuk antrean); reward = harga → hadiah lain (mis. Follback IG). */
    public const KINDS = ['mabar' => 'Mabar VIP', 'reward' => 'Reward lain'];

    protected $fillable = ['user_id', 'name', 'kind', 'reward_label', 'price', 'match_count', 'is_active', 'sort_order'];

    protected $casts = [
        'price' => 'integer',
        'match_count' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function isReward(): bool
    {
        return $this->kind === 'reward';
    }

    /** Yang didapat donatur: "2 game" untuk paket mabar, atau keterangan reward (mis. "Follback IG"). */
    public function getsLabel(): string
    {
        return $this->isReward() ? (string) ($this->reward_label ?: $this->name) : $this->match_count.' game';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
