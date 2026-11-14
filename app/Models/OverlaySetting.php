<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverlaySetting extends Model
{
    use HasFactory;
    use OwnedByUser;

    protected $fillable = [
        'user_id',
        'accent_color',
        'font_scale',
        'max_players',
        'show_now_playing',
        'transparent_mode',
        'censor_names',
        'show_score',
        'show_packages',
    ];

    protected $casts = [
        'font_scale' => 'integer',
        'max_players' => 'integer',
        'show_now_playing' => 'boolean',
        'transparent_mode' => 'boolean',
        'censor_names' => 'boolean',
        'show_score' => 'boolean',
        'show_packages' => 'boolean',
    ];
}
