<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokenLink;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SystemLog;
use App\Services\AuditLogger;
use App\Services\SearchConsoleService;
use App\Services\SeoService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SeoController extends Controller
{
    public function __construct(protected AuditLogger $audit, protected SearchConsoleService $gsc)
    {
    }

    public function overview(Request $request)
    {
        $range = DateRange::fromRequest($request, '28d');
        $issues = [
            'missing_meta' => Post::published()->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))->count(),
            'missing_alt' => Post::published()->whereNotNull('featured_image')->where(fn ($q) => $q->whereNull('featured_image_alt')->orWhere('featured_image_alt', ''))->count(),
            'thin' => Post::published()->where('word_count', '<', 100)->where('video_type', 'none')->count(),
            'no_category' => Post::published()->whereNull('category_id')->count(),
            'long_titles' => Post::published()->whereRaw('LENGTH(COALESCE(seo_title, title)) > 65')->count(),
            'broken_links' => BrokenLink::where('is_resolved', false)->count(),
            'not_found_24h' => (int) SystemLog::where('type', '404')->where('created_at', '>=', now()->subDay())->sum('occurrences'),
        ];
        $postsNeedingWork = Post::published()->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', '')->orWhere('word_count', '<', 100))->latest('published_at')->limit(15)->get();
        $gscTotals = $this->gsc->totals($range->from, $range->to);
        $topQueries = $this->gsc->isConnected() ? $this->gsc->breakdown('query', $range->from, $range->to, 10) : [];
        $topPages = $this->gsc->isConnected() ? $this->gsc->breakdown('page', $range->from, $range->to, 10) : [];
        $indexable = Post::published()->count() - $issues['thin'];
        return view('admin.seo.overview', compact('range', 'issues', 'postsNeedingWork', 'gscTotals', 'topQueries', 'topPages', 'indexable'));
    }

    public function searchConsole(Request $request)
    {
        $range = DateRange::fromRequest($request, '28d');
        $connected = $this->gsc->isConnected();
        $token = $this->gsc->token();
        $sites = [];
        if ($connected && ! $this->gsc->siteUrl()) {
            try {
                $sites = $this->gsc->listSites();
            } catch (\Throwable $e) {
                $sites = [];
                session()->flash('error', 'Could not list Search Console properties: '.$e->getMessage());
            }
        }
        $totals = $connected ? $this->gsc->totals($range->from, $range->to) : null;
        $prev = $connected ? $this->gsc->totals($range->prevFrom, $range->prevTo) : null;
        $series = $connected ? $this->gsc->series($range->from, $range->to) : null;
        $queries = $connected ? $this->gsc->breakdown('query', $range->from, $range->to, 25) : [];
        $pages = $connected ? $this->gsc->breakdown('page', $range->from, $range->to, 25) : [];
        $countries = $connected ? $this->gsc->breakdown('country', $range->from, $range->to, 10) : [];
        $devices = $connected ? $this->gsc->breakdown('device', $range->from, $range->to, 3) : [];
        return view('admin.seo.search-console', compact('range', 'connected', 'token', 'sites', 'totals', 'prev', 'series', 'queries', 'pages', 'countries', 'devices'));
    }

    public function selectSite(Request $request)
    {
        $data = $request->validate(['site_url' => 'required|string|max:500']);
        $this->gsc->setSite($data['site_url']);
        $this->audit->log('settings_changed', 'seo', null, 'Search Console property selected: '.$data['site_url']);
        return back()->with('success', 'Property selected. Run a sync to fetch data.');
    }

    public function sitemap(SeoService $seo)
    {
        $counts = [
            'posts' => Post::published()->count(),
            'excluded_thin' => Post::published()->where('word_count', '<', 100)->where('video_type', 'none')->count(),
            'categories' => \App\Models\Category::active()->count(),
            'pages' => Page::published()->count(),
        ];
        $lastLog = SystemLog::where('type', 'sitemap')->latest('created_at')->first();
        $preview = \Illuminate\Support\Str::limit(Cache::get('sitemap.xml') ?: $seo->sitemapXml(), 4000);
        return view('admin.seo.sitemap', compact('counts', 'lastLog', 'preview'));
    }

    public function regenerateSitemap(SeoService $seo)
    {
        Cache::forget('sitemap.xml');
        try {
            $xml = $seo->sitemapXml();
            Cache::put('sitemap.xml', $xml, 1800);
            $this->audit->log('sitemap_regenerated', 'seo', null, 'Sitemap regenerated ('.substr_count($xml, '<url>').' URLs)');
            return back()->with('success', 'Sitemap regenerated with '.substr_count($xml, '<url>').' URLs.');
        } catch (\Throwable $e) {
            SystemLog::record('sitemap', 'Sitemap generation failed: '.$e->getMessage());
            \App\Models\AdminNotification::announce('sitemap', 'Sitemap generation failed', $e->getMessage(), 'warning', route('admin.seo.sitemap'));
            return back()->withErrors(['sitemap' => $e->getMessage()]);
        }
    }

    // ---- Redirects ----

    public function redirects(Request $request)
    {
        $redirects = Redirect::when($request->s, fn ($q) => $q->where('from_path', 'like', '%'.$request->s.'%')->orWhere('to_path', 'like', '%'.$request->s.'%'))->latest()->paginate(30)->withQueryString();
        $top404 = SystemLog::where('type', '404')->where('created_at', '>=', now()->subDays(30))->orderByDesc('occurrences')->limit(15)->get();
        return view('admin.seo.redirects', compact('redirects', 'top404'));
    }

    public function storeRedirect(Request $request)
    {
        $data = $request->validate(['from_path' => 'required|string|max:500', 'to_path' => 'required|string|max:1000', 'status_code' => ['required', Rule::in([301, 302, 307, 308])]]);
        $data['from_path'] = Redirect::normalizePath($data['from_path']);
        if ($data['from_path'] === Redirect::normalizePath($data['to_path'])) {
            return back()->withErrors(['to_path' => 'Source and destination cannot be the same.']);
        }
        $r = Redirect::updateOrCreate(['from_path' => $data['from_path']], $data + ['is_active' => true]);
        Cache::forget('redirects.map');
        $this->audit->log('created', 'seo', $r, 'Redirect '.$r->from_path.' → '.$r->to_path);
        return back()->with('success', 'Redirect saved.');
    }

    public function updateRedirect(Request $request, Redirect $redirect)
    {
        $data = $request->validate(['from_path' => 'required|string|max:500', 'to_path' => 'required|string|max:1000', 'status_code' => ['required', Rule::in([301, 302, 307, 308])], 'is_active' => 'nullable|boolean']);
        $data['from_path'] = Redirect::normalizePath($data['from_path']);
        $data['is_active'] = $request->boolean('is_active');
        $original = $redirect->getOriginal();
        $redirect->update($data);
        Cache::forget('redirects.map');
        $this->audit->logModelChange('updated', 'seo', $redirect, $original, 'Redirect updated');
        return back()->with('success', 'Redirect updated.');
    }

    public function destroyRedirect(Redirect $redirect)
    {
        $this->audit->log('deleted', 'seo', $redirect, 'Redirect deleted '.$redirect->from_path);
        $redirect->delete();
        Cache::forget('redirects.map');
        return back()->with('success', 'Redirect deleted.');
    }

    // ---- Broken links ----

    public function brokenLinks(Request $request)
    {
        $links = BrokenLink::with('post')->where('is_resolved', $request->boolean('resolved'))->latest('checked_at')->paginate(30)->withQueryString();
        $lastRun = \App\Models\JobRun::where('name', 'links:check')->latest('started_at')->first();
        return view('admin.seo.broken-links', compact('links', 'lastRun'));
    }

    public function runLinkCheck()
    {
        Artisan::call('links:check', ['--limit' => 30]);
        $this->audit->log('link_check', 'seo', null, 'Broken link check triggered');
        return back()->with('success', trim(Artisan::output()) ?: 'Link check completed.');
    }

    public function resolveLink(BrokenLink $link)
    {
        $link->update(['is_resolved' => true]);
        return back()->with('success', 'Marked as resolved.');
    }
}
