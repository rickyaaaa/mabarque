<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationNotification extends Model
{
    use HasFactory;
    use OwnedByUser;

    protected $fillable = ['donation_id', 'user_id', 'type', 'context', 'read_at'];

    protected $casts = [
        'context' => 'array',
        'read_at' => 'datetime',
    ];

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
