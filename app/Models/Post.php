<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use SoftDeletes;

    public const TYPE_VLOG = 'vlog';
    public const TYPE_ARTICLE = 'article';

    public const STATUSES = ['draft', 'scheduled', 'published', 'unpublished'];

    protected $fillable = [
        'type', 'title', 'seo_title', 'slug', 'excerpt', 'content', 'featured_image', 'featured_image_alt', 'thumbnail',
        'video_type', 'video_url', 'video_embed', 'video_media_id', 'video_duration',
        'category_id', 'subcategory_id', 'author_id', 'created_by', 'status', 'published_at', 'scheduled_at',
        'is_featured', 'is_trending', 'is_recommended', 'allow_comments', 'canonical_url', 'meta_description',
        'focus_keyword', 'og_title', 'og_description', 'og_image', 'twitter_card', 'robots', 'quality_checklist',
        'reading_time', 'word_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'is_recommended' => 'boolean',
        'allow_comments' => 'boolean',
        'quality_checklist' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            $post->slug = static::uniqueSlug($post->slug ?: $post->title, $post->id);
            $text = trim(strip_tags((string) $post->content));
            $post->word_count = $text === '' ? 0 : str_word_count($text);
            $post->reading_time = max(1, (int) ceil($post->word_count / 200));
            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($source, 180, '')) ?: 'post';
        $slug = $base;
        $i = 1;
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }
        return $slug;
    }

    // ---- Relations ----
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function videoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_media_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved')->whereNull('parent_id')->latest();
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(ContentDaily::class);
    }

    // ---- Scopes ----
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeVlogs(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_VLOG);
    }

    public function scopeArticles(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_ARTICLE);
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    public function scopeTrending(Builder $q): Builder
    {
        return $q->where('is_trending', true);
    }

    public function scopeRecommended(Builder $q): Builder
    {
        return $q->where('is_recommended', true);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        return $q->where(function (Builder $w) use ($like) {
            $w->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like)
                ->orWhere('focus_keyword', 'like', $like)
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like))
                ->orWhereHas('author', fn ($a) => $a->where('name', 'like', $like));
        });
    }

    // ---- Helpers ----
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->isPast();
    }

    public function isVlog(): bool
    {
        return $this->type === self::TYPE_VLOG;
    }

    public function hasVideo(): bool
    {
        return $this->video_type !== 'none' && ($this->video_url || $this->video_embed || $this->video_media_id);
    }

    public function getUrlAttribute(): string
    {
        return $this->isVlog() ? route('vlog.show', $this->slug) : route('article.show', $this->slug);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('admin.posts.preview', $this->id);
    }

    public function getSeoTitleTextAttribute(): string
    {
        return $this->seo_title ?: $this->title;
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->featured_image) ?? $this->youtubeThumbnail();
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->thumbnail) ?? $this->getFeaturedImageUrlAttribute();
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->og_image) ?? $this->getFeaturedImageUrlAttribute();
    }

    public function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        return Str::startsWith($path, ['http://', 'https://', '//']) ? $path : asset('storage/'.ltrim($path, '/'));
    }

    public function youtubeId(): ?string
    {
        if ($this->video_type !== 'youtube' || ! $this->video_url) {
            return null;
        }
        return static::extractYoutubeId($this->video_url);
    }

    public static function extractYoutubeId(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return $m[1];
        }
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', trim($url))) {
            return trim($url);
        }
        return null;
    }

    public function vimeoId(): ?string
    {
        if ($this->video_type !== 'vimeo' || ! $this->video_url) {
            return null;
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $this->video_url, $m)) {
            return $m[1];
        }
        return null;
    }

    public function youtubeThumbnail(): ?string
    {
        $id = $this->youtubeId();
        return $id ? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg" : null;
    }

    public function selfHostedVideoUrl(): ?string
    {
        if ($this->video_type === 'self_hosted' && $this->videoMedia) {
            return $this->videoMedia->url;
        }
        return null;
    }

    public function readableStatus(): string
    {
        return ucfirst($this->status);
    }

    public function qualityScore(): array
    {
        $checks = [
            'title' => ['label' => 'Title completed', 'ok' => mb_strlen(trim((string) $this->title)) >= 10],
            'thumbnail' => ['label' => 'Thumbnail / featured image set', 'ok' => (bool) ($this->thumbnail || $this->featured_image || $this->youtubeThumbnail())],
            'description' => ['label' => 'Short description completed', 'ok' => mb_strlen(trim((string) $this->excerpt)) >= 50],
            'content' => ['label' => 'Main content completed (300+ words)', 'ok' => $this->word_count >= 300],
            'category' => ['label' => 'Category selected', 'ok' => (bool) $this->category_id],
            'seo' => ['label' => 'SEO title & meta description', 'ok' => (bool) ($this->meta_description && mb_strlen($this->meta_description) >= 50)],
            'alt' => ['label' => 'Featured image alt text', 'ok' => (bool) $this->featured_image_alt || ! $this->featured_image],
            'canonical' => ['label' => 'Canonical checked', 'ok' => ! $this->canonical_url || filter_var($this->canonical_url, FILTER_VALIDATE_URL) !== false],
            'links' => ['label' => 'No broken links detected', 'ok' => ! BrokenLink::where('post_id', $this->id)->where('is_resolved', false)->exists()],
        ];
        $passed = count(array_filter($checks, fn ($c) => $c['ok']));
        return ['checks' => $checks, 'passed' => $passed, 'total' => count($checks), 'percent' => (int) round($passed / count($checks) * 100)];
    }

    /** Thin content should not be indexed (AdSense/SEO quality). */
    public function isThin(): bool
    {
        return $this->word_count < 100 && ! $this->hasVideo();
    }

    public function robotsDirective(): string
    {
        if ($this->robots) {
            return $this->robots;
        }
        if (! $this->isPublished() || $this->isThin()) {
            return 'noindex, follow';
        }
        return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }
}
