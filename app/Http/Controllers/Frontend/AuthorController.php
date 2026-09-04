<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ContentDaily;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function __construct(protected SeoService $seo)
    {
    }

    public function show(Request $request, string $slug)
    {
        $author = User::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $request->attributes->set('page_meta', ['page_type' => 'author', 'post_id' => null, 'title' => $author->name]);
        $posts = $author->posts()->visible()->with(['category'])->orderByDesc('published_at')->paginate(max(6, (int) setting('site.posts_per_page', 12)));
        $stats = [
            'posts' => $author->posts()->visible()->count(),
            'views' => (int) $author->posts()->visible()->sum('views_count'),
            'video_views' => (int) $author->posts()->visible()->sum('video_plays_count'),
        ];
        $eng = ContentDaily::whereIn('post_id', $author->posts()->visible()->select('id'))->selectRaw('SUM(engagement_time) e, SUM(views) v')->first();
        $stats['avg_engagement'] = $eng && $eng->v > 0 ? (int) ($eng->e / $eng->v) : 0;
        $meta = $this->seo->meta([
            'title' => $this->seo->title($author->name),
            'description' => $author->bio ? \Illuminate\Support\Str::limit(strip_tags($author->bio), 160, '') : 'Vlogs and articles by '.$author->name.'.',
            'canonical' => $author->url,
            'og_type' => 'profile',
            'og_image' => $author->avatar_url,
            'schema' => [array_filter([
                '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $author->name, 'url' => $author->url,
                'image' => $author->avatar_url, 'description' => $author->bio ? strip_tags($author->bio) : null,
                'sameAs' => array_values(array_filter($author->social_links ?? [])) ?: null,
            ])],
        ]);
        return view('frontend.author', compact('author', 'posts', 'stats', 'meta'));
    }
}
