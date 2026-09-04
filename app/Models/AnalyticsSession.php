<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSession extends Model
{
    protected $fillable = [
        'session_key', 'visitor_id', 'started_at', 'last_activity_at', 'duration', 'engagement_time', 'page_views',
        'landing_page', 'exit_page', 'referrer', 'referrer_host', 'source', 'medium', 'campaign',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'device_type', 'browser', 'browser_version', 'os', 'os_version', 'country', 'city',
        'is_returning', 'is_bot', 'bot_name', 'ip_hash',
    ];

    protected $casts = ['started_at' => 'datetime', 'last_activity_at' => 'datetime', 'is_returning' => 'boolean', 'is_bot' => 'boolean'];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'session_id');
    }
}
