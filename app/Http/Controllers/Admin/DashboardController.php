<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Post;
use App\Models\SystemLog;
use App\Services\AdSenseService;
use App\Services\AnalyticsService;
use App\Services\SearchConsoleService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analytics,
        protected AdSenseService $adsense,
        protected SearchConsoleService $gsc,
    ) {
    }

    public function index(Request $request)
    {
        $range = DateRange::fromRequest($request, '30d');
        $this->analytics->ensureFresh();

        $totals = $this->analytics->totals($range->from, $range->to);
        $prev = $this->analytics->totals($range->prevFrom, $range->prevTo);
        $today = $this->analytics->totals(now()->startOfDay(), now()->startOfDay());
        $allTime = $this->analytics->totals(now()->subYears(3), now());
        $series = $this->analytics->series($range->from, $range->to, ['page_views', 'unique_visitors', 'sessions']);

        $gscTotals = $this->gsc->totals($range->from, $range->to);
        $gscPrev = $this->gsc->totals($range->prevFrom, $range->prevTo);
        $gscSeries = $this->gsc->isConnected() ? $this->gsc->series($range->from, $range->to) : null;

        $adsTotals = $this->adsense->totals($range->from, $range->to);
        $adsPrev = $this->adsense->totals($range->prevFrom, $range->prevTo);
        $adsSeries = $this->adsense->isConnected() ? $this->adsense->series($range->from, $range->to, $range->days() > 92 ? 'week' : 'day') : null;

        $topContent = $this->analytics->topContent($range->from, $range->to, 8);
        $trending = $this->analytics->topContent(now()->subDays(6), now(), 6);
        $sources = $this->analytics->dimension('source', $range->from, $range->to, 8);
        $countries = $this->analytics->dimension('country', $range->from, $range->to, 8);
        $devices = $this->analytics->dimension('device', $range->from, $range->to, 4);
        $realtime = $this->analytics->realtime();
        $notifications = AdminNotification::where('is_read', false)->latest('created_at')->limit(5)->get();
        $recentErrors = SystemLog::whereIn('type', ['500', 'exception'])->latest('created_at')->limit(5)->get();
        $counts = [
            'published' => Post::published()->count(),
            'drafts' => Post::where('status', 'draft')->count(),
            'scheduled' => Post::where('status', 'scheduled')->count(),
            'pending_comments' => \App\Models\Comment::where('status', 'pending')->count(),
        ];

        return view('admin.dashboard', compact(
            'range', 'totals', 'prev', 'today', 'allTime', 'series', 'gscTotals', 'gscPrev', 'gscSeries', 'adsTotals', 'adsPrev', 'adsSeries',
            'topContent', 'trending', 'sources', 'countries', 'devices', 'realtime', 'notifications', 'recentErrors', 'counts'
        ));
    }

    public function realtime()
    {
        return response()->json($this->analytics->realtime());
    }
}
