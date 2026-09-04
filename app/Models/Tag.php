<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::saving(function (Tag $t) {
            $base = Str::slug($t->slug ?: $t->name) ?: 'tag';
            $slug = $base;
            $i = 1;
            while (static::where('slug', $slug)->where('id', '!=', $t->id ?? 0)->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $t->slug = $slug;
        });
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function getUrlAttribute(): string
    {
        return route('tag.show', $this->slug);
    }

    public static function findOrCreateByNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tag = static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
            $ids[] = $tag->id;
        }
        return array_unique($ids);
    }
}
