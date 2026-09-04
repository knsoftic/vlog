<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['query', 'query_normalized', 'results_count', 'session_id', 'visitor_id', 'is_bot', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'is_bot' => 'boolean'];
}
