<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokenLink extends Model
{
    protected $fillable = ['post_id', 'source_url', 'target_url', 'status_code', 'error', 'is_resolved', 'checked_at'];

    protected $casts = ['is_resolved' => 'boolean', 'checked_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
