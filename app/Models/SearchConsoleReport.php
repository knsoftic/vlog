<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchConsoleReport extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['report_date' => 'date', 'synced_at' => 'datetime'];
}
