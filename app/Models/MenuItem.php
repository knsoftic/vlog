<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = ['location', 'label', 'url', 'target', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
