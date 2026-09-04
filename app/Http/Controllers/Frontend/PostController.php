<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\SeoService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(protected SeoService $seo)
    {
    }

    public function vlogs(Request $request)
    {
        $request->attributes->set('page_meta', ['page_type' => 'listing', 'post_id' => null, 'title' => 'Latest Vlogs']);
        $posts = Post::published()->vlogs()->with(['category', 'author'])->orderByDesc('published_at')->paginate($this->perPage());
        $meta = $this->seo->meta(['title' => $this->seo->title('Latest Vlogs'), 'description' => 'Watch the newest vlogs on '.setting('site.name').'.', 'canonical' => route('vlogs')]);
        return view('frontend.listing', ['posts' => $posts, 'meta' => $meta, 'heading' => 'Latest Vlogs', 'subheading' => 'Fresh from the camera', 'kind' => 'vlogs']);
    }

    public function articles(Request $request)
    {
        $request->attributes->set('page_meta', ['page_type' => 'listing', 'post_id' => null, 'title' => 'Articles']);
        $posts = Post::published()->articles()->with(['category', 'author'])->orderByDesc('published_at')->paginate($this->perPage());
        $meta = $this->seo->meta(['title' => $this->seo->title('Articles'), 'description' => 'Long-form articles and guides on '.setting('site.name').'.', 'canonical' => route('articles')]);
        return view('frontend.listing', ['posts' => $posts, 'meta' => $meta, 'heading' => 'Articles', 'subheading' => 'Long-form reads and guides', 'kind' => 'articles']);
    }

    public function trending(Request $request)
    {
        $request->attributes->set('page_meta', ['page_type' => 'listing', 'post_id' => null, 'title' => 'Trending']);
        $posts = Post::published()->with(['category', 'author'])
            ->orderByDesc('is_trending')
            ->orderByRaw('(SELECT COALESCE(SUM(views),0) FROM content_daily WHERE content_daily.post_id = posts.id AND content_daily.date >= ?) DESC', [now()->subDays(7)->toDateString()])
            ->orderByDesc('published_at')->paginate($this->perPage());
        $meta = $this->seo->meta(['title' => $this->seo->title('Trending'), 'description' => 'What everyone is watching this week on '.setting('site.name').'.', 'canonical' => route('trending')]);
        return view('frontend.listing', ['posts' => $posts, 'meta' => $meta, 'heading' => 'Trending Now', 'subheading' => 'Most watched over the last 7 days', 'kind' => 'trending']);
    }

    public function popular(Request $request)
    {
        $request->attributes->set('page_meta', ['page_type' => 'listing', 'post_id' => null, 'title' => 'Popular']);
        $posts = Post::published()->with(['category', 'author'])->orderByDesc('views_count')->orderByDesc('published_at')->paginate($this->perPage());
        $meta = $this->seo->meta(['title' => $this->seo->title('Most Popular'), 'description' => 'All-time most popular vlogs and articles on '.setting('site.name').'.', 'canonical' => route('popular')]);
        return view('frontend.listing', ['posts' => $posts, 'meta' => $meta, 'heading' => 'Most Popular', 'subheading' => 'All-time favourites', 'kind' => 'popular']);
    }

    public function showVlog(Request $request, string $slug)
    {
        return $this->show($request, $slug, Post::TYPE_VLOG);
    }

    public function showArticle(Request $request, string $slug)
    {
        return $this->show($request, $slug, Post::TYPE_ARTICLE);
    }

    protected function show(Request $request, string $slug, string $type)
    {
        $post = Post::with(['category', 'subcategory', 'author', 'tags', 'videoMedia'])->where('slug', $slug)->first();
        if (! $post) {
            abort(404);
        }
        // Wrong type in URL → canonical redirect (keeps a single URL per post)
        if ($post->type !== $type) {
            return redirect($post->url, 301);
        }
        if (! $post->isPublished()) {
            if (auth()->check() && auth()->user()->role) {
                return redirect()->route('admin.posts.preview', $post->id);
            }
            abort(404);
        }
        $request->attributes->set('page_meta', ['page_type' => $post->type, 'post_id' => $post->id, 'title' => $post->title]);

        $related = Post::published()->where('id', '!=', $post->id)
            ->where(fn ($q) => $q->where('category_id', $post->category_id)->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $post->tags->pluck('id'))))
            ->with(['category', 'author'])->orderByDesc('published_at')->limit(6)->get();
        if ($related->count() < 3) {
            $related = $related->merge(Post::published()->where('id', '!=', $post->id)->whereNotIn('id', $related->pluck('id'))->with(['category', 'author'])->orderByDesc('views_count')->limit(6 - $related->count())->get());
        }
        $comments = $post->allow_comments ? $post->approvedComments()->with('replies')->limit(50)->get() : collect();
        $meta = $this->seo->forPost($post);
        $breadcrumbs = $this->seo->postBreadcrumbs($post);
        $adsAllowed = $this->adsAllowedForPost($post);

        return view('frontend.post', compact('post', 'related', 'comments', 'meta', 'breadcrumbs', 'adsAllowed'));
    }

    /** AdSense policy: no ads on thin / placeholder pages. */
    protected function adsAllowedForPost(Post $post): bool
    {
        $min = (int) setting('adsense.min_words_for_ads', 150);
        return $post->word_count >= $min || ($post->hasVideo() && $post->word_count >= (int) ($min / 3));
    }

    protected function perPage(): int
    {
        return max(6, min(48, (int) setting('site.posts_per_page', 12)));
    }
}
