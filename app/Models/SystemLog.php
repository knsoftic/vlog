<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['level', 'type', 'message', 'url', 'referrer', 'context', 'occurrences', 'last_seen_at', 'created_at'];

    protected $casts = ['context' => 'array', 'created_at' => 'datetime', 'last_seen_at' => 'datetime'];

    /** Record a log line; identical type+message+url within 24h are merged as occurrences. */
    public static function record(string $type, string $message, array $context = [], string $level = 'error', ?string $url = null, ?string $referrer = null): void
    {
        try {
            $message = mb_substr($message, 0, 1000);
            $url = $url ? mb_substr($url, 0, 1000) : null;
            $existing = static::where('type', $type)->where('message', $message)->where('url', $url)
                ->where('created_at', '>=', now()->subDay())->first();
            if ($existing) {
                $existing->increment('occurrences');
                $existing->update(['last_seen_at' => now(), 'context' => $context ?: $existing->context]);
                return;
            }
            static::create([
                'level' => $level, 'type' => $type, 'message' => $message, 'url' => $url,
                'referrer' => $referrer ? mb_substr($referrer, 0, 1000) : null, 'context' => $context,
                'occurrences' => 1, 'last_seen_at' => now(), 'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // never let logging break the app
        }
    }
}
