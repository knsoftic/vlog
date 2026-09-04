<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'visitor_id', 'post_id', 'event_type', 'event_value', 'event_data', 'path', 'created_at'];

    protected $casts = ['event_data' => 'array', 'created_at' => 'datetime'];
}
