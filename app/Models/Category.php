<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image', 'seo_title', 'meta_description', 'status', 'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            $c->slug = static::uniqueSlug($c->slug ?: $c->name, $c->id);
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'category';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }
        return $slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function getUrlAttribute(): string
    {
        return route('category.show', $this->slug);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }
        return Str::startsWith($this->image, ['http://', 'https://']) ? $this->image : asset('storage/'.$this->image);
    }
}
