<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = ['visitor_key', 'first_seen_at', 'last_seen_at', 'sessions_count', 'country', 'device_type', 'browser', 'os', 'is_bot'];

    protected $casts = ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'is_bot' => 'boolean'];
}
