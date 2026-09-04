<?php

namespace App\Services;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsDimensionDaily;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\ContentDaily;
use App\Models\ContentDimensionDaily;
use App\Models\PageView;
use App\Models\Post;
use App\Models\RealtimeVisitor;
use App\Models\SearchLog;
use App\Models\VideoEvent;
use App\Models\Visitor;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * First-party, privacy-aware analytics.
 *
 *  - Visitors are identified by a random cookie id (no IP, no fingerprint).
 *  - IPs are only used transiently (bot detection / geo) and stored as a salted daily hash on sessions
 *    for abuse investigation; the retention job removes them.
 *  - Bots are recorded separately and never mixed into human metrics.
 *  - Nothing here ever fabricates views.
 */
class AnalyticsService
{
    public const VISITOR_COOKIE = 'vh_vid';
    public const SESSION_COOKIE = 'vh_sid';
    public const SESSION_TIMEOUT_MIN = 30;

    public function __construct(
        protected BotDetector $bots,
        protected GeoService $geo,
    ) {
    }

    // -------------------------------------------------------------------------------------------------
    //  Tracking
    // -------------------------------------------------------------------------------------------------

    /**
     * Record a page view. Returns [pageViewId, cookiesToQueue].
     *
     * @param  array{page_type:string, post_id:?int, title:?string}  $page
     */
    public function trackPageView(Request $request, array $page, bool $analyticsAllowed): array
    {
        $now = now();
        $class = $this->bots->classify($request);
        $isBot = $class['is_bot'];
        $ua = new UserAgentParser((string) $request->userAgent());
        $path = '/'.ltrim($request->path(), '/');
        $referrer = $this->cleanReferrer($request->headers->get('referer'), $request->getHost());
        $cookies = [];

        $visitor = null;
        $session = null;

        if ($isBot) {
            // Bot views: one lightweight session per bot UA per day, never a visitor record.
            $sessionKey = 'bot-'.substr(hash('sha256', ($class['name'] ?? 'bot').'|'.$now->format('Y-m-d')), 0, 40);
            $session = AnalyticsSession::firstOrCreate(['session_key' => $sessionKey], [
                'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => true, 'bot_name' => $class['name'],
                'landing_page' => $path, 'device_type' => 'bot', 'browser' => $class['name'], 'os' => 'bot',
            ]);
            $session->increment('page_views', 1, ['last_activity_at' => $now, 'exit_page' => $path]);
        } elseif ($analyticsAllowed) {
            $visitorKey = $this->validKey($request->cookie(self::VISITOR_COOKIE));
            $isNewVisitor = false;
            if (! $visitorKey) {
                $visitorKey = Str::random(40);
                $isNewVisitor = true;
                $cookies[] = cookie(self::VISITOR_COOKIE, $visitorKey, 60 * 24 * 365, '/', null, $request->isSecure(), false, false, 'Lax');
            }
            $geo = $this->geo->resolve($request);
            $visitor = Visitor::firstOrCreate(['visitor_key' => $visitorKey], [
                'first_seen_at' => $now, 'last_seen_at' => $now, 'sessions_count' => 0,
                'country' => $geo['country'], 'device_type' => $ua->deviceType(), 'browser' => $ua->browser(), 'os' => $ua->os(),
            ]);
            if (! $visitor->wasRecentlyCreated) {
                $isNewVisitor = false;
            }

            $sessionKey = $this->validKey($request->cookie(self::SESSION_COOKIE));
            if ($sessionKey) {
                $session = AnalyticsSession::where('session_key', $sessionKey)->first();
                if ($session && ($session->last_activity_at->lt($now->copy()->subMinutes(self::SESSION_TIMEOUT_MIN)) || $session->visitor_id !== $visitor->id)) {
                    $session = null; // expired => new session
                }
            }
            if (! $session) {
                $sessionKey = Str::random(40);
                $utm = $this->utm($request);
                $src = $this->classifySource($referrer, $utm);
                $session = AnalyticsSession::create([
                    'session_key' => $sessionKey,
                    'visitor_id' => $visitor->id,
                    'started_at' => $now,
                    'last_activity_at' => $now,
                    'page_views' => 0,
                    'landing_page' => mb_substr($path, 0, 500),
                    'exit_page' => mb_substr($path, 0, 500),
                    'referrer' => $referrer ? mb_substr($referrer, 0, 1000) : null,
                    'referrer_host' => $referrer ? mb_substr((string) parse_url($referrer, PHP_URL_HOST), 0, 190) : null,
                    'source' => $src['source'],
                    'medium' => $src['medium'],
                    'campaign' => $utm['utm_campaign'] ?? null,
                    'utm_source' => $utm['utm_source'] ?? null,
                    'utm_medium' => $utm['utm_medium'] ?? null,
                    'utm_campaign' => $utm['utm_campaign'] ?? null,
                    'utm_term' => $utm['utm_term'] ?? null,
                    'utm_content' => $utm['utm_content'] ?? null,
                    'device_type' => $ua->deviceType(),
                    'browser' => $ua->browser(),
                    'browser_version' => $ua->browserVersion(),
                    'os' => $ua->os(),
                    'os_version' => $ua->osVersion(),
                    'country' => $geo['country'],
                    'city' => $geo['city'],
                    'is_returning' => ! $isNewVisitor && $visitor->sessions_count > 0,
                    'is_bot' => false,
                    'ip_hash' => $this->bots->ipHash($request->ip()),
                ]);
                $visitor->increment('sessions_count', 1, ['last_seen_at' => $now, 'country' => $geo['country'] ?: $visitor->country]);
            } else {
                $visitor->update(['last_seen_at' => $now]);
            }
            // rolling session cookie
            $cookies[] = cookie(self::SESSION_COOKIE, $sessionKey, self::SESSION_TIMEOUT_MIN, '/', null, $request->isSecure(), true, false, 'Lax');
            $session->page_views++;
            $session->last_activity_at = $now;
            $session->exit_page = mb_substr($path, 0, 500);
            $session->duration = (int) max(0, $session->started_at->diffInSeconds($now)); // Carbon 3 diffs are signed
            $session->save();
        } else {
            // Consent not given (required region): cookieless, identifier-free single-hit session.
            $geo = $this->geo->resolve($request);
            $utm = $this->utm($request);
            $src = $this->classifySource($referrer, $utm);
            $session = AnalyticsSession::create([
                'session_key' => 'anon-'.Str::random(34),
                'started_at' => $now, 'last_activity_at' => $now, 'page_views' => 1,
                'landing_page' => mb_substr($path, 0, 500), 'exit_page' => mb_substr($path, 0, 500),
                'referrer_host' => $referrer ? mb_substr((string) parse_url($referrer, PHP_URL_HOST), 0, 190) : null,
                'source' => $src['source'], 'medium' => $src['medium'],
                'device_type' => $ua->deviceType(), 'browser' => $ua->browser(), 'os' => $ua->os(),
                'country' => $geo['country'], 'is_returning' => false, 'is_bot' => false,
            ]);
        }

        $pv = PageView::create([
            'session_id' => $session->id,
            'visitor_id' => $visitor?->id,
            'post_id' => $page['post_id'] ?? null,
            'page_type' => $page['page_type'] ?? 'other',
            'path' => mb_substr($path, 0, 500),
            'title' => isset($page['title']) ? mb_substr($page['title'], 0, 255) : null,
            'referrer' => $referrer ? mb_substr($referrer, 0, 1000) : null,
            'is_bot' => $isBot,
            'viewed_at' => $now,
        ]);

        if (! $isBot) {
            if (! empty($page['post_id'])) {
                Post::whereKey($page['post_id'])->increment('views_count');
                $uvKey = 'uv:'.($visitor?->id ?? 'anon-'.$session->id).':'.$page['post_id'];
                if (! Cache::has($uvKey)) {
                    Cache::put($uvKey, 1, 60 * 60 * 24);
                    Post::whereKey($page['post_id'])->increment('unique_views_count');
                }
            }
            RealtimeVisitor::updateOrCreate(['session_key' => $session->session_key], [
                'post_id' => $page['post_id'] ?? null,
                'path' => mb_substr($path, 0, 500),
                'title' => isset($page['title']) ? mb_substr($page['title'], 0, 255) : null,
                'page_type' => $page['page_type'] ?? 'other',
                'device_type' => $session->device_type,
                'country' => $session->country,
                'source' => $session->source,
                'last_seen_at' => $now,
            ]);
            if (mt_rand(1, 50) === 1) {
                RealtimeVisitor::where('last_seen_at', '<', $now->copy()->subMinutes(5))->delete();
            }
        }

        return [$pv->id, $session->session_key, $cookies];
    }

    /** Heartbeat from the browser: engagement time delta + max scroll depth. */
    public function heartbeat(Request $request, int $pageViewId, int $engagedDelta, int $scroll): bool
    {
        $sessionKey = $this->validKey($request->cookie(self::SESSION_COOKIE)) ?: $request->input('sk');
        $pv = PageView::with('session')->find($pageViewId);
        if (! $pv || ! $pv->session || $pv->session->session_key !== $sessionKey || $pv->is_bot) {
            return false;
        }
        $engagedDelta = max(0, min($engagedDelta, 120)); // cap: max heartbeat interval sanity
        $scroll = max(0, min($scroll, 100));
        $pv->engagement_time += $engagedDelta;
        $pv->scroll_depth = max($pv->scroll_depth, $scroll);
        $pv->save();
        $now = now();
        $s = $pv->session;
        $s->engagement_time += $engagedDelta;
        $s->last_activity_at = $now;
        $s->duration = (int) max($s->duration, $s->started_at->diffInSeconds($now));
        $s->save();
        RealtimeVisitor::where('session_key', $sessionKey)->update(['last_seen_at' => $now]);
        return true;
    }

    public function videoEvent(Request $request, array $data): bool
    {
        $sessionKey = $this->validKey($request->cookie(self::SESSION_COOKIE)) ?: $request->input('sk');
        $session = $sessionKey ? AnalyticsSession::where('session_key', $sessionKey)->first() : null;
        if ($session && $session->is_bot) {
            return false;
        }
        $event = $data['event'];
        $playId = mb_substr((string) ($data['play_id'] ?? ''), 0, 40) ?: null;
        // De-duplicate milestone events per play
        if ($playId && in_array($event, ['start', 'p25', 'p50', 'p75', 'p90', 'complete'], true)) {
            if (VideoEvent::where('play_id', $playId)->where('event', $event)->exists()) {
                return true;
            }
        }
        VideoEvent::create([
            'session_id' => $session?->id,
            'visitor_id' => $session?->visitor_id,
            'post_id' => $data['post_id'],
            'event' => $event,
            'provider' => mb_substr((string) ($data['provider'] ?? ''), 0, 20) ?: null,
            'watch_seconds' => max(0, min((int) ($data['watch_seconds'] ?? 0), 120)),
            'position' => max(0, (int) ($data['position'] ?? 0)),
            'duration' => max(0, (int) ($data['duration'] ?? 0)),
            'play_id' => $playId,
            'created_at' => now(),
        ]);
        if ($event === 'start') {
            Post::whereKey($data['post_id'])->increment('video_plays_count');
            if (! empty($data['duration'])) {
                Post::whereKey($data['post_id'])->whereNull('video_duration')->update(['video_duration' => (int) $data['duration']]);
            }
        }
        return true;
    }

    public function event(Request $request, string $type, ?string $value, array $extra = [], ?int $postId = null): void
    {
        $sessionKey = $this->validKey($request->cookie(self::SESSION_COOKIE)) ?: $request->input('sk');
        $session = $sessionKey ? AnalyticsSession::where('session_key', $sessionKey)->first() : null;
        if ($session && $session->is_bot) {
            return;
        }
        AnalyticsEvent::create([
            'session_id' => $session?->id,
            'visitor_id' => $session?->visitor_id,
            'post_id' => $postId,
            'event_type' => mb_substr($type, 0, 40),
            'event_value' => $value ? mb_substr($value, 0, 500) : null,
            'event_data' => $extra ?: null,
            'path' => mb_substr((string) ($extra['path'] ?? $request->headers->get('referer', '')), 0, 500) ?: null,
            'created_at' => now(),
        ]);
        if ($type === 'share' && $postId) {
            Post::whereKey($postId)->increment('shares_count');
        }
    }

    public function logSearch(Request $request, string $query, int $results): void
    {
        $sessionKey = $this->validKey($request->cookie(self::SESSION_COOKIE));
        $session = $sessionKey ? AnalyticsSession::where('session_key', $sessionKey)->first() : null;
        $isBot = $this->bots->classify($request)['is_bot'];
        SearchLog::create([
            'query' => mb_substr($query, 0, 255),
            'query_normalized' => mb_substr(Str::lower(trim(preg_replace('/\s+/', ' ', $query))), 0, 255),
            'results_count' => $results,
            'session_id' => $session?->id,
            'visitor_id' => $session?->visitor_id,
            'is_bot' => $isBot,
            'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------------------------------

    protected function validKey(?string $key): ?string
    {
        return $key && preg_match('/^[A-Za-z0-9\-]{20,64}$/', $key) ? $key : null;
    }

    protected function cleanReferrer(?string $ref, string $host): ?string
    {
        if (! $ref) {
            return null;
        }
        $refHost = parse_url($ref, PHP_URL_HOST);
        if (! $refHost || strcasecmp($refHost, $host) === 0) {
            return null; // internal navigation
        }
        if ($this->bots->isSpamReferrer($ref)) {
            return null;
        }
        return $ref;
    }

    protected function utm(Request $request): array
    {
        $out = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $k) {
            $v = $request->query($k);
            if (is_string($v) && $v !== '') {
                $out[$k] = mb_substr(strip_tags($v), 0, 150);
            }
        }
        return $out;
    }

    /** @return array{source:string, medium:string} */
    public function classifySource(?string $referrer, array $utm): array
    {
        if (! empty($utm['utm_source'])) {
            return ['source' => Str::lower($utm['utm_source']), 'medium' => Str::lower($utm['utm_medium'] ?? 'campaign')];
        }
        if (! $referrer) {
            return ['source' => 'direct', 'medium' => 'none'];
        }
        $host = Str::lower((string) parse_url($referrer, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|l|lm)\./', '', $host);
        $search = ['google.' => 'google', 'bing.com' => 'bing', 'yahoo.' => 'yahoo', 'duckduckgo' => 'duckduckgo', 'yandex' => 'yandex', 'baidu' => 'baidu', 'ecosia' => 'ecosia', 'ask.com' => 'ask'];
        foreach ($search as $needle => $name) {
            if (str_contains($host, $needle)) {
                return ['source' => $name, 'medium' => 'organic'];
            }
        }
        $social = ['facebook' => 'facebook', 'fb.com' => 'facebook', 'instagram' => 'instagram', 'twitter' => 'twitter', 't.co' => 'twitter', 'x.com' => 'twitter', 'youtube' => 'youtube', 'youtu.be' => 'youtube', 'tiktok' => 'tiktok', 'linkedin' => 'linkedin', 'pinterest' => 'pinterest', 'reddit' => 'reddit', 'whatsapp' => 'whatsapp', 'telegram' => 'telegram', 't.me' => 'telegram', 'snapchat' => 'snapchat', 'threads.net' => 'threads', 'quora' => 'quora', 'tumblr' => 'tumblr', 'discord' => 'discord'];
        foreach ($social as $needle => $name) {
            if (str_contains($host, $needle)) {
                return ['source' => $name, 'medium' => 'social'];
            }
        }
        if (preg_match('/mail\.|outlook|gmail|yahoo mail/', $host)) {
            return ['source' => $host, 'medium' => 'email'];
        }
        return ['source' => mb_substr($host, 0, 100) ?: 'referral', 'medium' => 'referral'];
    }

    public static function deviceFromRequest(Request $request): string
    {
        return (new UserAgentParser((string) $request->userAgent()))->deviceType();
    }

    // -------------------------------------------------------------------------------------------------
    //  Aggregation (scheduled + lazy)
    // -------------------------------------------------------------------------------------------------

    public function aggregateDay(Carbon $date): void
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $day = $start->toDateString();

        $human = PageView::whereBetween('viewed_at', [$start, $end])->where('is_bot', false);
        $pageViews = (clone $human)->count();
        $vlogViews = (clone $human)->where('page_type', 'vlog')->count();
        $articleViews = (clone $human)->where('page_type', 'article')->count();
        $botViews = PageView::whereBetween('viewed_at', [$start, $end])->where('is_bot', true)->count();
        $avgScroll = (float) ((clone $human)->avg('scroll_depth') ?? 0);

        $sessionsQ = AnalyticsSession::whereBetween('started_at', [$start, $end])->where('is_bot', false);
        $sessions = (clone $sessionsQ)->count();
        $uniqueVisitors = (clone $sessionsQ)->whereNotNull('visitor_id')->distinct('visitor_id')->count('visitor_id')
            + (clone $sessionsQ)->whereNull('visitor_id')->count();
        $newVisitors = (clone $sessionsQ)->where('is_returning', false)->count();
        $returning = (clone $sessionsQ)->where('is_returning', true)->count();
        $totalDuration = (int) (clone $sessionsQ)->sum('duration');
        $totalEngagement = (int) (clone $sessionsQ)->sum('engagement_time');
        $bounces = (clone $sessionsQ)->where('page_views', '<=', 1)->where('engagement_time', '<', 10)->count();
        $engaged = (clone $sessionsQ)->where(fn ($q) => $q->where('page_views', '>=', 2)->orWhere('engagement_time', '>=', 10))->count();
        $botSessions = AnalyticsSession::whereBetween('started_at', [$start, $end])->where('is_bot', true)->count();

        $videoQ = VideoEvent::whereBetween('created_at', [$start, $end]);
        $videoPlays = (clone $videoQ)->where('event', 'start')->count();
        $videoUnique = (clone $videoQ)->where('event', 'start')->whereNotNull('visitor_id')->distinct('visitor_id')->count('visitor_id');
        $videoCompletes = (clone $videoQ)->where('event', 'complete')->count();
        $watchTime = (int) (clone $videoQ)->where('event', 'heartbeat')->sum('watch_seconds');

        $searchQ = SearchLog::whereBetween('created_at', [$start, $end])->where('is_bot', false);
        $searches = (clone $searchQ)->count();
        $zeroSearches = (clone $searchQ)->where('results_count', 0)->count();

        $evQ = AnalyticsEvent::whereBetween('created_at', [$start, $end]);
        $shares = (clone $evQ)->where('event_type', 'share')->count();
        $outbound = (clone $evQ)->where('event_type', 'outbound_click')->count();

        AnalyticsDaily::updateOrCreate(['date' => $day], [
            'page_views' => $pageViews, 'unique_visitors' => $uniqueVisitors, 'sessions' => $sessions,
            'new_visitors' => $newVisitors, 'returning_visitors' => $returning, 'total_duration' => $totalDuration,
            'total_engagement' => $totalEngagement, 'bounces' => $bounces, 'engaged_sessions' => $engaged,
            'vlog_views' => $vlogViews, 'article_views' => $articleViews, 'video_plays' => $videoPlays,
            'video_unique_viewers' => $videoUnique, 'video_completes' => $videoCompletes, 'watch_time' => $watchTime,
            'searches' => $searches, 'zero_result_searches' => $zeroSearches, 'shares' => $shares, 'outbound_clicks' => $outbound,
            'bot_views' => $botViews, 'bot_sessions' => $botSessions, 'avg_scroll_depth' => round($avgScroll, 2),
        ]);

        // Dimensions from sessions
        AnalyticsDimensionDaily::where('date', $day)->delete();
        $dimensionColumns = [
            'country' => 'country', 'city' => 'city', 'device' => 'device_type', 'browser' => 'browser', 'os' => 'os',
            'source' => 'source', 'medium' => 'medium', 'referrer' => 'referrer_host', 'landing' => 'landing_page',
            'exit' => 'exit_page', 'utm_source' => 'utm_source', 'utm_medium' => 'utm_medium', 'utm_campaign' => 'utm_campaign',
        ];
        $rows = [];
        foreach ($dimensionColumns as $dim => $col) {
            $agg = AnalyticsSession::whereBetween('started_at', [$start, $end])->where('is_bot', false)->whereNotNull($col)
                ->selectRaw("`$col` as v, COUNT(*) as s, SUM(page_views) as pv, COUNT(DISTINCT visitor_id) as vis, SUM(duration) as dur")
                ->groupBy('v')->orderByDesc('s')->limit(500)->get();
            foreach ($agg as $r) {
                if ($r->v === null || $r->v === '') {
                    continue;
                }
                $rows[] = ['date' => $day, 'dimension' => $dim, 'value' => mb_substr((string) $r->v, 0, 190), 'page_views' => (int) $r->pv, 'sessions' => (int) $r->s, 'visitors' => (int) $r->vis, 'duration' => (int) $r->dur];
            }
        }
        $pt = PageView::whereBetween('viewed_at', [$start, $end])->where('is_bot', false)
            ->selectRaw('page_type as v, COUNT(*) as pv, COUNT(DISTINCT session_id) as s, COUNT(DISTINCT visitor_id) as vis')->groupBy('v')->get();
        foreach ($pt as $r) {
            $rows[] = ['date' => $day, 'dimension' => 'page_type', 'value' => (string) $r->v, 'page_views' => (int) $r->pv, 'sessions' => (int) $r->s, 'visitors' => (int) $r->vis, 'duration' => 0];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            AnalyticsDimensionDaily::insert($chunk);
        }

        // Content daily
        $content = PageView::whereBetween('viewed_at', [$start, $end])->where('is_bot', false)->whereNotNull('post_id')
            ->selectRaw('post_id, COUNT(*) as views, COUNT(DISTINCT COALESCE(visitor_id, session_id)) as uv, SUM(engagement_time) as eng, AVG(scroll_depth) as sc')
            ->groupBy('post_id')->get()->keyBy('post_id');
        $video = VideoEvent::whereBetween('created_at', [$start, $end])
            ->selectRaw("post_id,
                SUM(event='start') as starts, COUNT(DISTINCT CASE WHEN event='start' THEN visitor_id END) as uvv,
                SUM(event='p25') as p25, SUM(event='p50') as p50, SUM(event='p75') as p75, SUM(event='p90') as p90,
                SUM(event='complete') as completes, SUM(CASE WHEN event='heartbeat' THEN watch_seconds ELSE 0 END) as wt")
            ->groupBy('post_id')->get()->keyBy('post_id');
        $events = AnalyticsEvent::whereBetween('created_at', [$start, $end])->whereNotNull('post_id')
            ->selectRaw("post_id, SUM(event_type='share') as shares, SUM(event_type='outbound_click') as outbound")->groupBy('post_id')->get()->keyBy('post_id');
        $postIds = array_unique(array_merge($content->keys()->all(), $video->keys()->all(), $events->keys()->all()));
        foreach ($postIds as $pid) {
            $c = $content[$pid] ?? null;
            $v = $video[$pid] ?? null;
            $e = $events[$pid] ?? null;
            ContentDaily::updateOrCreate(['date' => $day, 'post_id' => $pid], [
                'views' => (int) ($c->views ?? 0), 'unique_views' => (int) ($c->uv ?? 0), 'engagement_time' => (int) ($c->eng ?? 0),
                'avg_scroll_depth' => round((float) ($c->sc ?? 0), 2),
                'video_starts' => (int) ($v->starts ?? 0), 'video_unique_viewers' => (int) ($v->uvv ?? 0),
                'p25' => (int) ($v->p25 ?? 0), 'p50' => (int) ($v->p50 ?? 0), 'p75' => (int) ($v->p75 ?? 0), 'p90' => (int) ($v->p90 ?? 0),
                'completes' => (int) ($v->completes ?? 0), 'watch_time' => (int) ($v->wt ?? 0),
                'shares' => (int) ($e->shares ?? 0), 'outbound_clicks' => (int) ($e->outbound ?? 0),
            ]);
        }
        // Content dimensions (country/device/source) via sessions join
        ContentDimensionDaily::where('date', $day)->delete();
        $cd = [];
        foreach (['country' => 'country', 'device' => 'device_type', 'source' => 'source'] as $dim => $col) {
            $agg = DB::table('page_views as pv')->join('analytics_sessions as s', 's.id', '=', 'pv.session_id')
                ->whereBetween('pv.viewed_at', [$start, $end])->where('pv.is_bot', false)->whereNotNull('pv.post_id')->whereNotNull("s.$col")
                ->selectRaw("pv.post_id, s.`$col` as v, COUNT(*) as views")->groupBy('pv.post_id', 'v')->get();
            foreach ($agg as $r) {
                $cd[] = ['date' => $day, 'post_id' => $r->post_id, 'dimension' => $dim, 'value' => mb_substr((string) $r->v, 0, 190), 'views' => (int) $r->views];
            }
        }
        foreach (array_chunk($cd, 500) as $chunk) {
            ContentDimensionDaily::insert($chunk);
        }
    }

    /** Re-aggregate today at most every N minutes when a dashboard is opened (no cron needed for small sites). */
    public function ensureFresh(int $minutes = 5): void
    {
        $lock = Cache::lock('analytics:aggregate-today', 120);
        if (Cache::has('analytics:today-fresh')) {
            return;
        }
        if ($lock->get()) {
            try {
                $this->aggregateDay(now());
                if (! AnalyticsDaily::where('date', now()->subDay()->toDateString())->exists() && PageView::where('viewed_at', '<', now()->startOfDay())->exists()) {
                    $this->aggregateDay(now()->subDay());
                }
                Cache::put('analytics:today-fresh', 1, $minutes * 60);
            } finally {
                $lock->release();
            }
        }
    }

    // -------------------------------------------------------------------------------------------------
    //  Reading (dashboards)
    // -------------------------------------------------------------------------------------------------

    public function totals(Carbon $from, Carbon $to): array
    {
        $r = AnalyticsDaily::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(page_views) page_views, SUM(unique_visitors) unique_visitors, SUM(sessions) sessions, SUM(new_visitors) new_visitors,
                SUM(returning_visitors) returning_visitors, SUM(total_duration) total_duration, SUM(total_engagement) total_engagement,
                SUM(bounces) bounces, SUM(engaged_sessions) engaged_sessions, SUM(vlog_views) vlog_views, SUM(article_views) article_views,
                SUM(video_plays) video_plays, SUM(video_unique_viewers) video_unique_viewers, SUM(video_completes) video_completes, SUM(watch_time) watch_time,
                SUM(searches) searches, SUM(zero_result_searches) zero_result_searches, SUM(shares) shares, SUM(outbound_clicks) outbound_clicks,
                SUM(bot_views) bot_views, SUM(bot_sessions) bot_sessions, AVG(avg_scroll_depth) avg_scroll_depth')
            ->first();
        $t = array_map(fn ($v) => (float) ($v ?? 0), $r ? $r->toArray() : []);
        $t = $t + array_fill_keys(['page_views', 'unique_visitors', 'sessions', 'new_visitors', 'returning_visitors', 'total_duration', 'total_engagement', 'bounces', 'engaged_sessions', 'vlog_views', 'article_views', 'video_plays', 'video_unique_viewers', 'video_completes', 'watch_time', 'searches', 'zero_result_searches', 'shares', 'outbound_clicks', 'bot_views', 'bot_sessions', 'avg_scroll_depth'], 0.0);
        $t['avg_session_duration'] = $t['sessions'] > 0 ? $t['total_duration'] / $t['sessions'] : 0;
        $t['avg_engagement_time'] = $t['sessions'] > 0 ? $t['total_engagement'] / $t['sessions'] : 0;
        $t['bounce_rate'] = $t['sessions'] > 0 ? $t['bounces'] / $t['sessions'] * 100 : 0;
        $t['engagement_rate'] = $t['sessions'] > 0 ? $t['engaged_sessions'] / $t['sessions'] * 100 : 0;
        $t['pages_per_session'] = $t['sessions'] > 0 ? $t['page_views'] / $t['sessions'] : 0;
        $t['play_rate'] = $t['vlog_views'] > 0 ? $t['video_plays'] / $t['vlog_views'] * 100 : 0;
        $t['completion_rate'] = $t['video_plays'] > 0 ? $t['video_completes'] / $t['video_plays'] * 100 : 0;
        $t['avg_watch_time'] = $t['video_plays'] > 0 ? $t['watch_time'] / $t['video_plays'] : 0;
        return $t;
    }

    /** Time series. Granularity auto: day (<= 92 days), week, month, or year. */
    public function series(Carbon $from, Carbon $to, array $metrics = ['page_views', 'unique_visitors', 'sessions'], ?string $granularity = null): array
    {
        $days = $from->diffInDays($to) + 1;
        $granularity = $granularity ?: ($days <= 92 ? 'day' : ($days <= 400 ? 'week' : 'month'));
        $rows = AnalyticsDaily::whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date')->get()->keyBy(fn ($r) => $r->date->toDateString());
        $buckets = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $d) {
            $key = match ($granularity) {
                'week' => $d->copy()->startOfWeek()->toDateString(),
                'month' => $d->format('Y-m-01'),
                'year' => $d->format('Y-01-01'),
                default => $d->toDateString(),
            };
            if (! isset($buckets[$key])) {
                $buckets[$key] = array_fill_keys($metrics, 0);
            }
            $row = $rows[$d->toDateString()] ?? null;
            if ($row) {
                foreach ($metrics as $m) {
                    $buckets[$key][$m] += (float) $row->{$m};
                }
            }
        }
        $labels = array_keys($buckets);
        $datasets = [];
        foreach ($metrics as $m) {
            $datasets[$m] = array_map(fn ($b) => $b[$m], array_values($buckets));
        }
        return ['labels' => $labels, 'datasets' => $datasets, 'granularity' => $granularity];
    }

    public function dimension(string $dimension, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return AnalyticsDimensionDaily::where('dimension', $dimension)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('value, SUM(page_views) page_views, SUM(sessions) sessions, SUM(visitors) visitors, SUM(duration) duration')
            ->groupBy('value')->orderByDesc('sessions')->limit($limit)->get()->map(fn ($r) => [
                'value' => $r->value, 'page_views' => (int) $r->page_views, 'sessions' => (int) $r->sessions, 'visitors' => (int) $r->visitors,
                'avg_duration' => $r->sessions > 0 ? (int) ($r->duration / $r->sessions) : 0,
            ])->all();
    }

    public function topContent(Carbon $from, Carbon $to, int $limit = 10, ?string $type = null, string $orderBy = 'views'): array
    {
        $q = ContentDaily::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('post_id, SUM(views) views, SUM(unique_views) unique_views, SUM(engagement_time) engagement_time, SUM(video_starts) video_starts,
                SUM(completes) completes, SUM(watch_time) watch_time, SUM(shares) shares, AVG(avg_scroll_depth) scroll')
            ->groupBy('post_id')->orderByDesc($orderBy)->limit($limit);
        if ($type) {
            $q->whereHas('post', fn ($p) => $p->where('type', $type));
        }
        $rows = $q->with('post:id,title,slug,type,featured_image,thumbnail,video_type,video_url,published_at,category_id')->get();
        return $rows->filter(fn ($r) => $r->post)->map(fn ($r) => [
            'post' => $r->post, 'views' => (int) $r->views, 'unique_views' => (int) $r->unique_views,
            'avg_engagement' => $r->views > 0 ? (int) ($r->engagement_time / $r->views) : 0,
            'video_starts' => (int) $r->video_starts, 'completes' => (int) $r->completes,
            'completion_rate' => $r->video_starts > 0 ? round($r->completes / $r->video_starts * 100, 1) : 0,
            'watch_time' => (int) $r->watch_time, 'shares' => (int) $r->shares, 'scroll' => round((float) $r->scroll, 1),
        ])->values()->all();
    }

    public function contentTotals(int $postId, Carbon $from, Carbon $to): array
    {
        $r = ContentDaily::where('post_id', $postId)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(views) views, SUM(unique_views) unique_views, SUM(engagement_time) engagement_time, AVG(avg_scroll_depth) scroll,
                SUM(video_starts) video_starts, SUM(video_unique_viewers) video_unique_viewers, SUM(p25) p25, SUM(p50) p50, SUM(p75) p75, SUM(p90) p90,
                SUM(completes) completes, SUM(watch_time) watch_time, SUM(shares) shares, SUM(outbound_clicks) outbound_clicks')->first();
        $t = array_map(fn ($v) => (float) ($v ?? 0), $r ? $r->toArray() : []);
        $t += array_fill_keys(['views', 'unique_views', 'engagement_time', 'scroll', 'video_starts', 'video_unique_viewers', 'p25', 'p50', 'p75', 'p90', 'completes', 'watch_time', 'shares', 'outbound_clicks'], 0.0);
        $t['avg_engagement'] = $t['views'] > 0 ? $t['engagement_time'] / $t['views'] : 0;
        $t['avg_watch_time'] = $t['video_starts'] > 0 ? $t['watch_time'] / $t['video_starts'] : 0;
        $t['completion_rate'] = $t['video_starts'] > 0 ? $t['completes'] / $t['video_starts'] * 100 : 0;
        $t['play_rate'] = $t['views'] > 0 ? $t['video_starts'] / $t['views'] * 100 : 0;
        return $t;
    }

    public function contentSeries(int $postId, Carbon $from, Carbon $to): array
    {
        $rows = ContentDaily::where('post_id', $postId)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->keyBy(fn ($r) => $r->date->toDateString());
        $labels = [];
        $views = [];
        $plays = [];
        $watch = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $k = $d->toDateString();
            $labels[] = $k;
            $views[] = (int) ($rows[$k]->views ?? 0);
            $plays[] = (int) ($rows[$k]->video_starts ?? 0);
            $watch[] = (int) ($rows[$k]->watch_time ?? 0);
        }
        return compact('labels', 'views', 'plays', 'watch');
    }

    public function contentDimension(int $postId, string $dimension, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return ContentDimensionDaily::where('post_id', $postId)->where('dimension', $dimension)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('value, SUM(views) views')->groupBy('value')->orderByDesc('views')->limit($limit)->get()
            ->map(fn ($r) => ['value' => $r->value, 'views' => (int) $r->views])->all();
    }

    public function realtime(): array
    {
        $since = now()->subMinutes(5);
        RealtimeVisitor::where('last_seen_at', '<', $since)->delete();
        $rows = RealtimeVisitor::with('post:id,title,slug,type')->where('last_seen_at', '>=', $since)->get();
        $group = fn (string $col) => $rows->groupBy(fn ($r) => $r->{$col} ?: 'unknown')->map->count()->sortDesc()->take(8)->all();
        return [
            'online' => $rows->count(),
            'pages' => $rows->groupBy('path')->map(fn ($g) => ['path' => $g->first()->path, 'title' => $g->first()->title, 'count' => $g->count()])->sortByDesc('count')->take(10)->values()->all(),
            'vlogs' => $rows->whereNotNull('post_id')->groupBy('post_id')->map(fn ($g) => ['post' => $g->first()->post, 'count' => $g->count()])->filter(fn ($r) => $r['post'])->sortByDesc('count')->take(10)->values()->all(),
            'devices' => $group('device_type'),
            'countries' => $group('country'),
            'sources' => $group('source'),
            'last_minute' => PageView::where('viewed_at', '>=', now()->subMinute())->where('is_bot', false)->count(),
        ];
    }

    public function searchTerms(Carbon $from, Carbon $to, bool $zeroOnly = false, int $limit = 20): array
    {
        return SearchLog::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->where('is_bot', false)
            ->when($zeroOnly, fn ($q) => $q->where('results_count', 0))
            ->selectRaw('query_normalized as term, COUNT(*) as searches, AVG(results_count) as avg_results, MAX(created_at) as last_at')
            ->groupBy('term')->orderByDesc('searches')->limit($limit)->get()->map(fn ($r) => [
                'term' => $r->term, 'searches' => (int) $r->searches, 'avg_results' => round((float) $r->avg_results, 1), 'last_at' => $r->last_at,
            ])->all();
    }

    public function suggestions(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $posts = Post::published()->where('title', 'like', $like)->orderByDesc('views_count')->limit($limit)->get(['id', 'title', 'slug', 'type', 'thumbnail', 'featured_image', 'video_type', 'video_url']);
        return $posts->map(fn ($p) => ['title' => $p->title, 'url' => $p->url, 'type' => $p->type, 'image' => $p->thumbnail_url])->all();
    }
}
