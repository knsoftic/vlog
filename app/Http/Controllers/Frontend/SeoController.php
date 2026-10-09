<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\AdsTxtService;
use App\Services\SeoService;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function sitemap(SeoService $seo)
    {
        if (! setting_bool('seo.sitemap_enabled', true)) {
            abort(404);
        }
        $xml = Cache::remember('sitemap.xml', 1800, fn () => $seo->sitemapXml());
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=1800']);
    }

    public function robots(SeoService $seo)
    {
        return response($seo->robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Monetag push service worker (Admin → Monetization → Monetag). */
    public function serviceWorker()
    {
        $js = trim((string) setting('monetag.sw_js'));
        if ($js === '') {
            abort(404);
        }
        return response($js."\n", 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function adsTxt(AdsTxtService $ads)
    {
        return response($ads->content(), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
