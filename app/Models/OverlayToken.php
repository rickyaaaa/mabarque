<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverlayToken extends Model
{
    use HasFactory;
    use OwnedByUser;

    protected $fillable = ['user_id', 'token_hash', 'last_used_at', 'revoked_at'];

    protected $casts = ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
}
