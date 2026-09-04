<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'visitor_id', 'post_id', 'page_type', 'path', 'title', 'referrer', 'engagement_time', 'scroll_depth', 'is_bot', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime', 'is_bot' => 'boolean'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSession::class, 'session_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
