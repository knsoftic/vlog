<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateLimitBlock extends Model
{
    protected $fillable = ['ip_hash', 'reason', 'blocked_until'];

    protected $casts = ['blocked_until' => 'datetime'];
}
