<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_system', 'level'];

    protected $casts = ['is_system' => 'boolean'];

    protected ?array $permissionCache = null;

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->permissions()->pluck('slug')->all();
        }
        return in_array($slug, $this->permissionCache, true);
    }
}
