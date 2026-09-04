<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDimensionDaily extends Model
{
    public $timestamps = false;

    protected $table = 'analytics_dimension_daily';

    protected $guarded = [];

    protected $casts = ['date' => 'date'];
}
