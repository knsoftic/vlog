<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'user_name', 'action', 'module', 'model_type', 'model_id', 'description', 'before', 'after', 'ip', 'user_agent', 'device', 'created_at'];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
