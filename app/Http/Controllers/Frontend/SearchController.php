<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AnalyticsService;
use App\Services\SeoService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(protected SeoService $seo, protected AnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 120));
        $request->attributes->set('page_meta', ['page_type' => 'search', 'post_id' => null, 'title' => 'Search']);
        $posts = null;
        if ($q !== '') {
            $posts = Post::visible()->search($q)->with(['category', 'author'])->orderByDesc('views_count')->orderByDesc('published_at')->paginate(12)->withQueryString();
            if (! $request->query('page') && setting_bool('analytics.internal_enabled', true)) {
                $this->analytics->logSearch($request, $q, $posts->total());
            }
        }
        $meta = $this->seo->meta([
            'title' => $this->seo->title($q !== '' ? 'Search: '.$q : 'Search'),
            'description' => 'Search vlogs and articles on '.setting('site.name').'.',
            'canonical' => route('search'),
            'robots' => 'noindex, follow',
        ]);
        return view('frontend.search', compact('q', 'posts', 'meta'));
    }

    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        return response()->json(['suggestions' => $this->analytics->suggestions($q)])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
