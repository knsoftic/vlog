<?php

namespace App\Http\Middleware;

use App\Services\BotDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting + abusive-client blocking for the public site.
 * Verified search engine crawlers are never throttled.
 */
class TrafficProtection
{
    public function __construct(protected BotDetector $bots)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('storage/*', 'build/*')) {
            return $next($request);
        }
        if ($this->bots->isBlocked($request)) {
            return response('Too many requests. Please try again later.', 429, ['Retry-After' => '1800']);
        }
        $perMinute = (int) setting('security.rate_limit_per_minute', 120);
        $threshold = (int) setting('security.bot_block_threshold', 300);
        if ($perMinute > 0 && $this->bots->tooManyRequests($request, $perMinute, max($threshold, $perMinute + 1))) {
            return response('Too many requests. Please slow down.', 429, ['Retry-After' => '60']);
        }
        return $next($request);
    }
}
