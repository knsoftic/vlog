<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    protected $fillable = ['consent_key', 'necessary', 'analytics', 'advertising', 'personalization', 'region', 'method', 'tc_string', 'user_agent'];

    protected $casts = ['necessary' => 'boolean', 'analytics' => 'boolean', 'advertising' => 'boolean', 'personalization' => 'boolean'];
}
