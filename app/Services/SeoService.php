<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Meta tags, structured data, sitemap and robots generation.
 * Structured data only reflects actually visible content; no ratings/reviews are ever fabricated.
 */
class SeoService
{
    public function siteName(): string
    {
        return (string) setting('site.name', config('app.name'));
    }

    public function title(?string $title, bool $appendSite = true): string
    {
        $site = $this->siteName();
        $sep = ' '.setting('seo.title_separator', '|').' ';
        if (! $title) {
            return setting('seo.home_title') ?: $site.$sep.setting('site.tagline', '');
        }
        return $appendSite ? Str::limit($title, 60, '').$sep.$site : $title;
    }

    /** Build the meta array consumed by the layout. */
    public function meta(array $overrides = []): array
    {
        $base = [
            'title' => $this->title(null),
            'description' => setting('seo.home_description') ?: setting('site.description', ''),
            'canonical' => url()->current(),
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'og_type' => 'website',
            'og_title' => null,
            'og_description' => null,
            'og_image' => media_url(setting('site.organization_logo')) ?: media_url(setting('site.logo')),
            'twitter_card' => 'summary_large_image',
            'schema' => [],
        ];
        $m = array_merge($base, array_filter($overrides, fn ($v) => $v !== null));
        $m['og_title'] = $m['og_title'] ?: $m['title'];
        $m['og_description'] = $m['og_description'] ?: $m['description'];
        $m['description'] = Str::limit(trim(strip_tags((string) $m['description'])), 160, '');
        return $m;
    }

    public function forPost(Post $post): array
    {
        $desc = $post->meta_description ?: Str::limit(strip_tags((string) $post->excerpt ?: $post->content), 160, '');
        $schema = [$this->breadcrumbSchema($this->postBreadcrumbs($post))];
        $schema[] = $this->articleSchema($post);
        if ($post->hasVideo()) {
            $schema[] = $this->videoSchema($post);
        }
        return $this->meta([
            'title' => $this->title($post->seo_title_text),
            'description' => $desc,
            'canonical' => $post->canonical_url ?: $post->url,
            'robots' => $post->robotsDirective(),
            'og_type' => $post->isVlog() && $post->hasVideo() ? 'video.other' : 'article',
            'og_title' => $post->og_title ?: $post->seo_title_text,
            'og_description' => $post->og_description ?: $desc,
            'og_image' => $post->og_image_url,
            'twitter_card' => $post->twitter_card ?: 'summary_large_image',
            'schema' => array_values(array_filter($schema)),
            'published_time' => $post->published_at?->toIso8601String(),
            'modified_time' => $post->updated_at?->toIso8601String(),
            'author' => $post->author?->name,
            'keyword' => $post->focus_keyword,
        ]);
    }

    public function forCategory(Category $c): array
    {
        return $this->meta([
            'title' => $this->title($c->seo_title ?: $c->name),
            'description' => $c->meta_description ?: $c->description,
            'canonical' => $c->url,
            'og_image' => $c->image_url,
            'schema' => [$this->breadcrumbSchema([['Home', url('/')], ['Categories', route('categories')], [$c->name, $c->url]])],
        ]);
    }

    public function forPage(Page $p): array
    {
        $noindex = setting_bool('seo.noindex_thin', true) && $p->isThin();
        return $this->meta([
            'title' => $this->title($p->meta_title ?: $p->title),
            'description' => $p->meta_description ?: Str::limit(strip_tags((string) $p->content), 160, ''),
            'canonical' => $p->canonical_url ?: $p->url,
            'robots' => $p->robots ?: ($noindex ? 'noindex, follow' : null),
            'og_title' => $p->og_title,
            'og_description' => $p->og_description,
            'og_image' => media_url($p->og_image),
            'schema' => [$this->breadcrumbSchema([['Home', url('/')], [$p->title, $p->url]])],
        ]);
    }

    // ---- Schema builders ----

    public function organizationSchema(): array
    {
        $links = json_decode((string) setting('site.social_links', '{}'), true) ?: [];
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => setting('site.organization_name') ?: $this->siteName(),
            'url' => url('/'),
            'logo' => media_url(setting('site.organization_logo')) ?: media_url(setting('site.logo')),
            'sameAs' => array_values(array_filter($links)) ?: null,
        ]);
    }

    public function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->siteName(),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('search').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function articleSchema(Post $post): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $post->isVlog() ? 'BlogPosting' : 'Article',
            'headline' => Str::limit($post->title, 110, ''),
            'description' => Str::limit(strip_tags((string) ($post->meta_description ?: $post->excerpt)), 300, ''),
            'image' => $post->featured_image_url ? [$post->featured_image_url] : null,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name, 'url' => $post->author->url] : null,
            'publisher' => ['@type' => 'Organization', 'name' => setting('site.organization_name') ?: $this->siteName(), 'logo' => media_url(setting('site.organization_logo')) ? ['@type' => 'ImageObject', 'url' => media_url(setting('site.organization_logo'))] : null],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post->url],
            'wordCount' => $post->word_count ?: null,
            'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
            'articleSection' => $post->category?->name,
        ]);
    }

    public function videoSchema(Post $post): ?array
    {
        $embed = null;
        $content = null;
        if ($id = $post->youtubeId()) {
            $embed = 'https://www.youtube.com/embed/'.$id;
        } elseif ($id = $post->vimeoId()) {
            $embed = 'https://player.vimeo.com/video/'.$id;
        } elseif ($url = $post->selfHostedVideoUrl()) {
            $content = $url;
        } elseif ($post->video_type === 'external' && $post->video_url) {
            $content = $post->video_url;
        }
        if (! $embed && ! $content) {
            return null;
        }
        $duration = $post->video_duration ? 'PT'.intdiv($post->video_duration, 60).'M'.($post->video_duration % 60).'S' : null;
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $post->title,
            'description' => Str::limit(strip_tags((string) ($post->excerpt ?: $post->meta_description ?: $post->title)), 300, ''),
            'thumbnailUrl' => $post->thumbnail_url ? [$post->thumbnail_url] : null,
            'uploadDate' => $post->published_at?->toIso8601String(),
            'duration' => $duration,
            'embedUrl' => $embed,
            'contentUrl' => $content,
            'interactionStatistic' => $post->video_plays_count > 0 ? [
                '@type' => 'InteractionCounter', 'interactionType' => ['@type' => 'WatchAction'], 'userInteractionCount' => $post->video_plays_count,
            ] : null,
        ]);
    }

    public function breadcrumbSchema(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    public function postBreadcrumbs(Post $post): array
    {
        $crumbs = [['Home', url('/')]];
        $crumbs[] = $post->isVlog() ? ['Vlogs', route('vlogs')] : ['Articles', route('articles')];
        if ($post->category) {
            $crumbs[] = [$post->category->name, $post->category->url];
        }
        if ($post->subcategory) {
            $crumbs[] = [$post->subcategory->name, $post->subcategory->url];
        }
        $crumbs[] = [$post->title, $post->url];
        return $crumbs;
    }

    // ---- Sitemap / robots ----

    public function sitemapXml(): string
    {
        $urls = [];
        $add = function (string $loc, ?string $lastmod = null, string $freq = 'weekly', string $priority = '0.6', array $extra = []) use (&$urls) {
            $urls[] = compact('loc', 'lastmod', 'freq', 'priority', 'extra');
        };
        $add(url('/'), now()->toAtomString(), 'daily', '1.0');
        foreach ([route('vlogs'), route('articles'), route('trending'), route('popular'), route('categories')] as $u) {
            $add($u, null, 'daily', '0.8');
        }
        Post::published()->with('videoMedia')->orderByDesc('published_at')->chunk(500, function ($posts) use ($add) {
            foreach ($posts as $p) {
                if ($p->isThin() || str_contains((string) $p->robots, 'noindex')) {
                    continue;
                }
                $extra = [];
                if ($p->featured_image_url) {
                    $extra['image'] = $p->featured_image_url;
                }
                if ($p->hasVideo() && $p->thumbnail_url) {
                    $extra['video'] = [
                        'thumbnail' => $p->thumbnail_url, 'title' => $p->title,
                        'description' => Str::limit(strip_tags((string) ($p->excerpt ?: $p->title)), 200, ''),
                        'player' => $p->youtubeId() ? 'https://www.youtube.com/embed/'.$p->youtubeId() : ($p->vimeoId() ? 'https://player.vimeo.com/video/'.$p->vimeoId() : null),
                        'content' => $p->selfHostedVideoUrl(),
                        'duration' => $p->video_duration,
                        'publication' => $p->published_at?->toAtomString(),
                    ];
                }
                $add($p->url, $p->updated_at?->toAtomString(), 'weekly', $p->isVlog() ? '0.8' : '0.7', $extra);
            }
        });
        foreach (Category::active()->get() as $c) {
            $add($c->url, $c->updated_at?->toAtomString(), 'weekly', '0.6');
        }
        foreach (Tag::has('posts')->get() as $t) {
            $add($t->url, $t->updated_at?->toAtomString(), 'weekly', '0.4');
        }
        foreach (User::where('is_active', true)->has('posts')->get() as $u) {
            $add($u->url, $u->updated_at?->toAtomString(), 'weekly', '0.5');
        }
        foreach (Page::published()->get() as $p) {
            if (setting_bool('seo.noindex_thin', true) && $p->isThin()) {
                continue;
            }
            $add($p->url, $p->updated_at?->toAtomString(), 'monthly', '0.4');
        }

        $x = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">'."\n";
        foreach ($urls as $u) {
            $x .= "  <url>\n    <loc>".e($u['loc'])."</loc>\n";
            if ($u['lastmod']) {
                $x .= '    <lastmod>'.e($u['lastmod'])."</lastmod>\n";
            }
            $x .= '    <changefreq>'.$u['freq']."</changefreq>\n    <priority>".$u['priority']."</priority>\n";
            if (! empty($u['extra']['image'])) {
                $x .= '    <image:image><image:loc>'.e($u['extra']['image'])."</image:loc></image:image>\n";
            }
            if (! empty($u['extra']['video'])) {
                $v = $u['extra']['video'];
                $x .= "    <video:video>\n      <video:thumbnail_loc>".e($v['thumbnail'])."</video:thumbnail_loc>\n      <video:title>".e($v['title'])."</video:title>\n      <video:description>".e($v['description'])."</video:description>\n";
                if ($v['player']) {
                    $x .= '      <video:player_loc>'.e($v['player'])."</video:player_loc>\n";
                }
                if ($v['content']) {
                    $x .= '      <video:content_loc>'.e($v['content'])."</video:content_loc>\n";
                }
                if ($v['duration']) {
                    $x .= '      <video:duration>'.(int) $v['duration']."</video:duration>\n";
                }
                if ($v['publication']) {
                    $x .= '      <video:publication_date>'.e($v['publication'])."</video:publication_date>\n";
                }
                $x .= "    </video:video>\n";
            }
            $x .= "  </url>\n";
        }
        $x .= '</urlset>';
        return $x;
    }

    public function robotsTxt(): string
    {
        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /admin/', 'Disallow: /api/', 'Disallow: /search?', 'Disallow: /preview/', ''];
        $extra = trim((string) setting('seo.robots_extra'));
        if ($extra !== '') {
            $lines[] = $extra;
            $lines[] = '';
        }
        if (setting_bool('seo.sitemap_enabled', true)) {
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }
        return implode("\n", $lines)."\n";
    }
}
