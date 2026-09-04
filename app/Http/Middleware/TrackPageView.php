<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use App\Services\GeoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a first-party page view for successful HTML GET responses on the public site.
 * The controller sets `$request->attributes->set('page_meta', [...])` to identify the page/post.
 */
class TrackPageView
{
    public function __construct(protected AnalyticsService $analytics, protected GeoService $geo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $this->shouldTrack($request, $response)) {
                return $response;
            }
            $meta = $request->attributes->get('page_meta', ['page_type' => 'other', 'post_id' => null, 'title' => null]);
            $consent = $this->consentState($request);
            [$pvId, $sessionKey, $cookies] = $this->analytics->trackPageView($request, $meta, $consent['analytics']);
            foreach ($cookies as $c) {
                $response->headers->setCookie($c);
            }
            // expose page view id to the tracker script via header-safe inline injection
            $content = $response->getContent();
            if (is_string($content) && str_contains($content, '</head>')) {
                $inject = '<meta name="vh-pv" content="'.(int) $pvId.'"><meta name="vh-sk" content="'.e($sessionKey).'">';
                $response->setContent(preg_replace('~</head>~', $inject.'</head>', $content, 1));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    protected function shouldTrack(Request $request, Response $response): bool
    {
        if (! setting_bool('analytics.internal_enabled', true)) {
            return false;
        }
        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $ct = (string) $response->headers->get('Content-Type');
        if ($ct && ! str_contains($ct, 'text/html')) {
            return false;
        }
        if ($request->is('admin*', 'api/*', 'preview/*', 'storage/*', 'build/*', 'sitemap.xml', 'robots.txt', 'ads.txt')) {
            return false;
        }
        if (auth()->check() && ! setting_bool('analytics.track_admins', false)) {
            return false;
        }
        return true;
    }

    /** @return array{required: bool, analytics: bool, advertising: bool} */
    public function consentState(Request $request): array
    {
        $country = $this->geo->resolve($request)['country'];
        $required = setting_bool('consent.enabled', true) && $this->geo->requiresConsent($country);
        $cookie = $request->cookie('vh_consent');
        $analytics = ! $required;
        $advertising = ! $required;
        if ($cookie && preg_match('/^v1\.(\d)(\d)(\d)$/', $cookie, $m)) {
            $analytics = $m[1] === '1';
            $advertising = $m[2] === '1';
        }
        return ['required' => $required, 'analytics' => $analytics, 'advertising' => $advertising, 'country' => $country];
    }
}
