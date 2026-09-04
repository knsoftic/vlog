<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\SeoService;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(protected SeoService $seo)
    {
    }

    public function show(Request $request, string $slug)
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();
        $request->attributes->set('page_meta', ['page_type' => 'tag', 'post_id' => null, 'title' => '#'.$tag->name]);
        $posts = $tag->posts()->visible()->with(['category', 'author'])->orderByDesc('published_at')->paginate(max(6, (int) setting('site.posts_per_page', 12)));
        $meta = $this->seo->meta([
            'title' => $this->seo->title('#'.$tag->name),
            'description' => $tag->description ?: 'Vlogs and articles tagged "'.$tag->name.'" on '.setting('site.name').'.',
            'canonical' => $tag->url,
            'robots' => $posts->total() < 2 ? 'noindex, follow' : null,
        ]);
        return view('frontend.tag', compact('tag', 'posts', 'meta'));
    }
}
