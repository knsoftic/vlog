<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'content', 'template', 'status', 'is_system', 'show_in_footer', 'show_in_header', 'sort_order',
        'meta_title', 'meta_description', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image',
    ];

    protected $casts = ['is_system' => 'boolean', 'show_in_footer' => 'boolean', 'show_in_header' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Page $p) {
            $base = Str::slug($p->slug ?: $p->title) ?: 'page';
            $slug = $base;
            $i = 1;
            while (static::where('slug', $slug)->where('id', '!=', $p->id ?? 0)->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $p->slug = $slug;
        });
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function getUrlAttribute(): string
    {
        return route('page.show', $this->slug);
    }

    public function isThin(): bool
    {
        return str_word_count(strip_tags((string) $this->content)) < 50 && $this->template === 'default';
    }
}
