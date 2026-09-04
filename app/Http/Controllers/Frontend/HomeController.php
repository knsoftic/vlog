<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Post;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __construct(protected SeoService $seo)
    {
    }

    public function index(Request $request)
    {
        $request->attributes->set('page_meta', ['page_type' => 'home', 'post_id' => null, 'title' => 'Home']);

        $ttl = setting_bool('perf.cache_pages', true) ? (int) setting('perf.cache_ttl', 600) : 0;
        $build = function () {
            $sections = HomeSection::where('enabled', true)->orderBy('sort_order')->get();
            $data = [];
            $used = [];
            foreach ($sections as $s) {
                if (($s->key === 'latest' && ! Post::typeEnabled(Post::TYPE_VLOG)) || ($s->key === 'articles' && ! Post::typeEnabled(Post::TYPE_ARTICLE))) {
                    continue;
                }
                $limit = (int) $s->setting('limit', 6);
                $data[$s->key] = match ($s->key) {
                    'hero' => $this->fresh(Post::visible()->featured()->with(['category', 'author'])->orderByDesc('published_at')->limit($limit), $used),
                    'latest' => $this->fresh(Post::visible()->vlogs()->with(['category', 'author'])->orderByDesc('published_at')->limit($limit + count($used)), $used, $limit),
                    'trending' => $this->fresh(Post::visible()->trending()->with(['category', 'author'])->orderByDesc('published_at')->limit($limit), $used),
                    'popular' => $this->fresh(Post::visible()->with(['category', 'author'])->orderByDesc('views_count')->limit($limit + count($used)), $used, $limit),
                    'articles' => $this->fresh(Post::visible()->articles()->with(['category', 'author'])->orderByDesc('published_at')->limit($limit), $used),
                    'recommended' => $this->fresh(Post::visible()->recommended()->with(['category', 'author'])->orderByDesc('published_at')->limit($limit), $used),
                    'categories' => Category::active()->topLevel()->withCount(['posts' => fn ($q) => $q->visible()])->orderByDesc('posts_count')->orderBy('sort_order')->limit($limit)->get(),
                    default => null,
                };
                if (! empty($s->setting('category_id')) && in_array($s->key, ['latest', 'popular'], true)) {
                    $data[$s->key] = Post::visible()->where('category_id', (int) $s->setting('category_id'))->with(['category', 'author'])->orderByDesc('published_at')->limit($limit)->get();
                }
            }
            return [$sections, $data];
        };
        [$sections, $data] = $ttl > 0 ? Cache::remember('home.sections', $ttl, $build) : $build();

        // Fallback: if there are no featured posts, use the latest vlogs for the hero
        if (isset($data['hero']) && $data['hero']->isEmpty()) {
            $data['hero'] = Post::visible()->with(['category', 'author'])->orderByDesc('published_at')->limit(5)->get();
        }

        $meta = $this->seo->meta([
            'schema' => [$this->seo->organizationSchema(), $this->seo->websiteSchema()],
        ]);
        return view('frontend.home', compact('sections', 'data', 'meta'));
    }

    /** Avoid showing the same post in multiple home sections. */
    protected function fresh($query, array &$used, ?int $limit = null)
    {
        $items = $query->get()->reject(fn ($p) => in_array($p->id, $used, true))->values();
        if ($limit) {
            $items = $items->take($limit);
        }
        foreach ($items as $p) {
            $used[] = $p->id;
        }
        return $items;
    }
}
