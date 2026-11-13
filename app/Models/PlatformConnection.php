<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformConnection extends Model
{
    use HasFactory;
    use OwnedByUser;

    protected $fillable = [
        'user_id',
        'provider',
        'label',
        'webhook_url',
        'signing_secret',
        'status',
        'last_received_at',
        'last_tested_at',
        'last_error',
    ];

    protected $casts = [
        'signing_secret' => 'encrypted',
        'last_received_at' => 'datetime',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = ['signing_secret'];

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
