<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'hits', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
