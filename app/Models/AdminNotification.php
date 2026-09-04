<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'severity', 'title', 'message', 'data', 'link', 'is_read', 'created_at'];

    protected $casts = ['data' => 'array', 'is_read' => 'boolean', 'created_at' => 'datetime'];

    public static function announce(string $type, string $title, ?string $message = null, string $severity = 'info', ?string $link = null, array $data = []): ?self
    {
        try {
            // de-duplicate identical unread notifications within 24h
            $dup = static::where('type', $type)->where('title', $title)->where('is_read', false)->where('created_at', '>=', now()->subDay())->exists();
            if ($dup) {
                return null;
            }
            return static::create(compact('type', 'title', 'message', 'severity', 'link', 'data') + ['created_at' => now()]);
        } catch (\Throwable) {
            return null;
        }
    }
}
