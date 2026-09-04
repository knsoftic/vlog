<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeVisitor extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_key', 'post_id', 'path', 'title', 'page_type', 'device_type', 'country', 'source', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function post(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
