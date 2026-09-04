<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AnalyticsService;
use App\Services\BotDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * First-party tracking beacon endpoints (/api/track/*). CSRF-exempt, rate limited, bot-filtered.
 * Only records genuine browser-reported engagement; nothing here can inflate page views.
 */
class TrackingController extends Controller
{
    public function __construct(protected AnalyticsService $analytics, protected BotDetector $bots)
    {
    }

    protected function guard(Request $request, int $max = 120): bool
    {
        if (! setting_bool('analytics.internal_enabled', true)) {
            return false;
        }
        if ($this->bots->classify($request)['is_bot']) {
            return false;
        }
        $key = 'track:'.$this->bots->ipHash($request->ip());
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }
        RateLimiter::hit($key, 60);
        return true;
    }

    public function heartbeat(Request $request)
    {
        if (! $this->guard($request)) {
            return response()->noContent();
        }
        $data = $request->validate(['pv' => 'required|integer', 'engaged' => 'required|integer|min:0|max:120', 'scroll' => 'required|integer|min:0|max:100']);
        $this->analytics->heartbeat($request, (int) $data['pv'], (int) $data['engaged'], (int) $data['scroll']);
        return response()->noContent();
    }

    public function video(Request $request)
    {
        if (! $this->guard($request, 240)) {
            return response()->noContent();
        }
        $data = $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'event' => 'required|in:start,p25,p50,p75,p90,complete,heartbeat',
            'provider' => 'nullable|string|max:20',
            'position' => 'nullable|integer|min:0',
            'duration' => 'nullable|integer|min:0',
            'watch_seconds' => 'nullable|integer|min:0|max:120',
            'play_id' => 'nullable|string|max:40',
        ]);
        $this->analytics->videoEvent($request, $data);
        return response()->noContent();
    }

    public function event(Request $request)
    {
        if (! $this->guard($request)) {
            return response()->noContent();
        }
        $data = $request->validate([
            'type' => 'required|in:share,outbound_click,scroll,consent,newsletter,comment',
            'value' => 'nullable|string|max:500',
            'post_id' => 'nullable|integer',
            'path' => 'nullable|string|max:500',
            'data' => 'nullable|array',
        ]);
        $postId = ! empty($data['post_id']) && Post::whereKey($data['post_id'])->exists() ? (int) $data['post_id'] : null;
        $this->analytics->event($request, $data['type'], $data['value'] ?? null, ($data['data'] ?? []) + ['path' => $data['path'] ?? null], $postId);
        return response()->noContent();
    }
}
