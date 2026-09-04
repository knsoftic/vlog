<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'severity', 'user_id', 'email', 'ip', 'user_agent', 'path', 'details', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];
}
