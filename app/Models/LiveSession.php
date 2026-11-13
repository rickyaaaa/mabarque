<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveSession extends Model
{
    use HasFactory;
    use OwnedByUser;

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'started_at',
        'ended_at',
        'total_players',
        'total_matches',
        'total_donation',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_players' => 'integer',
        'total_matches' => 'integer',
        'total_donation' => 'integer',
    ];

    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class)->orderBy('position');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchRecord::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
