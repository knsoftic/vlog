<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentDimensionDaily extends Model
{
    public $timestamps = false;

    protected $table = 'content_dimension_daily';

    protected $guarded = [];

    protected $casts = ['date' => 'date'];
}
