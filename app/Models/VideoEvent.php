<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'visitor_id', 'post_id', 'event', 'provider', 'watch_seconds', 'position', 'duration', 'play_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
