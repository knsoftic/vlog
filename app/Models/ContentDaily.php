<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDaily extends Model
{
    public $timestamps = false;

    protected $table = 'content_daily';

    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
