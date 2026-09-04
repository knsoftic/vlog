<?php

namespace App\Services;

use App\Models\RateLimitBlock;
use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Traffic-quality classification.
 *
 *  - Known crawlers (Googlebot, Bingbot, ...) are identified and *never blocked*; they are simply
 *    excluded from human analytics.
 *  - Abusive request frequency from non-crawler clients results in a temporary block.
 *  - Nothing here inflates human page views; classification only removes noise.
 */
class BotDetector
{
    protected CrawlerDetect $crawler;

    /** Search engines that must never be rate-limited or blocked. */
    protected array $verifiedSearchEngines = [
        'googlebot', 'google-inspectiontool', 'adsbot-google', 'mediapartners-google', 'apis-google', 'feedfetcher-google',
        'bingbot', 'bingpreview', 'msnbot', 'yandex', 'duckduckbot', 'baiduspider', 'applebot', 'slurp', 'facebookexternalhit',
        'twitterbot', 'linkedinbot', 'pinterestbot', 'whatsapp', 'telegrambot', 'discordbot', 'ia_archiver', 'petalbot',
    ];

    public function __construct()
    {
        $this->crawler = new CrawlerDetect;
    }

    /** @return array{is_bot: bool, name: ?string, is_search_engine: bool} */
    public function classify(Request $request): array
    {
        $ua = (string) $request->userAgent();
        if ($ua === '') {
            return ['is_bot' => true, 'name' => 'empty-ua', 'is_search_engine' => false];
        }
        $lower = strtolower($ua);
        foreach ($this->verifiedSearchEngines as $engine) {
            if (str_contains($lower, $engine)) {
                return ['is_bot' => true, 'name' => $engine, 'is_search_engine' => true];
            }
        }
        if ($this->crawler->isCrawler($ua)) {
            return ['is_bot' => true, 'name' => mb_substr((string) $this->crawler->getMatches(), 0, 100), 'is_search_engine' => false];
        }
        // headless / automation hints
        if (preg_match('/headless|phantomjs|selenium|puppeteer|playwright|python-requests|curl\/|wget\/|go-http-client|java\/|libwww|scrapy|httpclient/i', $ua)) {
            return ['is_bot' => true, 'name' => 'automation', 'is_search_engine' => false];
        }
        return ['is_bot' => false, 'name' => null, 'is_search_engine' => false];
    }

    public function isSearchEngine(Request $request): bool
    {
        return $this->classify($request)['is_search_engine'];
    }

    /** Stable, salted hash of the IP for short-lived rate limiting (never the raw IP). */
    public function ipHash(?string $ip): string
    {
        return hash('sha256', ($ip ?? '0.0.0.0').'|'.config('app.key').'|'.now()->format('Y-m-d'));
    }

    /**
     * Sliding request counter. Returns true when the client should be blocked.
     */
    public function tooManyRequests(Request $request, int $perMinute, int $blockThreshold): bool
    {
        $class = $this->classify($request);
        if ($class['is_search_engine']) {
            return false; // never throttle verified search engines
        }
        $hash = $this->ipHash($request->ip());
        $key = 'rl:'.$hash.':'.now()->format('YmdHi');
        $count = (int) Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, 120);
        }
        if ($count > $blockThreshold) {
            $this->block($hash, 'request_flood', 30, $request, $count);
            return true;
        }
        if ($count > $perMinute) {
            if ($count === $perMinute + 1) {
                app(AuditLogger::class)->security('rate_limited', 'warning', ['count' => $count, 'per_minute' => $perMinute]);
            }
            return true;
        }
        return false;
    }

    public function isBlocked(Request $request): bool
    {
        $hash = $this->ipHash($request->ip());
        try {
            return Cache::remember('rlb:'.$hash, 60, function () use ($hash) {
                return RateLimitBlock::where('ip_hash', $hash)->where('blocked_until', '>', now())->exists();
            });
        } catch (\Throwable) {
            return false; // database not migrated yet
        }
    }

    public function block(string $hash, string $reason, int $minutes, ?Request $request = null, int $count = 0): void
    {
        RateLimitBlock::create(['ip_hash' => $hash, 'reason' => $reason, 'blocked_until' => now()->addMinutes($minutes)]);
        Cache::put('rlb:'.$hash, true, $minutes * 60);
        app(AuditLogger::class)->security('bot_blocked', 'warning', ['reason' => $reason, 'count' => $count, 'minutes' => $minutes]);
    }

    /** Referrer spam heuristics. */
    public function isSpamReferrer(?string $referrer): bool
    {
        if (! $referrer) {
            return false;
        }
        return (bool) preg_match('/semalt|buttons-for-website|darodar|best-seo-offer|free-share-buttons|traffic2money|site-auditor|ranksonic|bot-traffic|floating-share-buttons|event-tracking|success-seo|seo-platform/i', $referrer);
    }
}
