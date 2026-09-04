<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AnalyticsService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics)
    {
    }

    protected function base(Request $request): array
    {
        $range = DateRange::fromRequest($request, '30d');
        $this->analytics->ensureFresh();
        return ['range' => $range, 'totals' => $this->analytics->totals($range->from, $range->to), 'prev' => $this->analytics->totals($range->prevFrom, $range->prevTo)];
    }

    public function overview(Request $request)
    {
        $d = $this->base($request);
        $d['series'] = $this->analytics->series($d['range']->from, $d['range']->to, ['page_views', 'unique_visitors', 'sessions', 'new_visitors', 'returning_visitors']);
        $d['prevSeries'] = $d['range']->compare ? $this->analytics->series($d['range']->prevFrom, $d['range']->prevTo, ['page_views', 'unique_visitors', 'sessions']) : null;
        $d['pageTypes'] = $this->analytics->dimension('page_type', $d['range']->from, $d['range']->to, 10);
        $d['topContent'] = $this->analytics->topContent($d['range']->from, $d['range']->to, 10);
        $d['landing'] = $this->analytics->dimension('landing', $d['range']->from, $d['range']->to, 10);
        $d['exit'] = $this->analytics->dimension('exit', $d['range']->from, $d['range']->to, 10);
        return view('admin.analytics.overview', $d);
    }

    public function realtime()
    {
        return view('admin.analytics.realtime', ['realtime' => $this->analytics->realtime()]);
    }

    public function traffic(Request $request)
    {
        $d = $this->base($request);
        $d['series'] = $this->analytics->series($d['range']->from, $d['range']->to, ['page_views', 'sessions', 'unique_visitors', 'bot_views']);
        $d['landing'] = $this->analytics->dimension('landing', $d['range']->from, $d['range']->to, 15);
        $d['exit'] = $this->analytics->dimension('exit', $d['range']->from, $d['range']->to, 15);
        $d['pageTypes'] = $this->analytics->dimension('page_type', $d['range']->from, $d['range']->to, 10);
        return view('admin.analytics.traffic', $d);
    }

    public function content(Request $request)
    {
        $d = $this->base($request);
        $type = in_array($request->type, ['vlog', 'article'], true) ? $request->type : null;
        $sort = in_array($request->sort, ['views', 'unique_views', 'engagement_time', 'video_starts', 'watch_time', 'shares'], true) ? $request->sort : 'views';
        $d['type'] = $type;
        $d['sort'] = $sort;
        $d['topContent'] = $this->analytics->topContent($d['range']->from, $d['range']->to, 50, $type, $sort);
        $d['searches'] = $this->analytics->searchTerms($d['range']->from, $d['range']->to, false, 15);
        $d['zeroSearches'] = $this->analytics->searchTerms($d['range']->from, $d['range']->to, true, 15);
        return view('admin.analytics.content', $d);
    }

    public function video(Request $request)
    {
        $d = $this->base($request);
        $d['series'] = $this->analytics->series($d['range']->from, $d['range']->to, ['video_plays', 'video_completes', 'watch_time', 'video_unique_viewers']);
        $d['topVideos'] = $this->analytics->topContent($d['range']->from, $d['range']->to, 30, 'vlog', 'video_starts');
        $funnel = \App\Models\ContentDaily::whereBetween('date', [$d['range']->from->toDateString(), $d['range']->to->toDateString()])
            ->selectRaw('SUM(video_starts) s, SUM(p25) p25, SUM(p50) p50, SUM(p75) p75, SUM(p90) p90, SUM(completes) c')->first();
        $d['funnel'] = ['Starts' => (int) ($funnel->s ?? 0), '25%' => (int) ($funnel->p25 ?? 0), '50%' => (int) ($funnel->p50 ?? 0), '75%' => (int) ($funnel->p75 ?? 0), '90%' => (int) ($funnel->p90 ?? 0), 'Completed' => (int) ($funnel->c ?? 0)];
        return view('admin.analytics.video', $d);
    }

    public function audience(Request $request)
    {
        $d = $this->base($request);
        foreach (['country', 'city', 'device', 'browser', 'os'] as $dim) {
            $d[$dim] = $this->analytics->dimension($dim, $d['range']->from, $d['range']->to, 15);
        }
        $d['series'] = $this->analytics->series($d['range']->from, $d['range']->to, ['new_visitors', 'returning_visitors']);
        return view('admin.analytics.audience', $d);
    }

    public function sources(Request $request)
    {
        $d = $this->base($request);
        foreach (['source', 'medium', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign'] as $dim) {
            $d[$dim] = $this->analytics->dimension($dim, $d['range']->from, $d['range']->to, 15);
        }
        return view('admin.analytics.sources', $d);
    }

    public function search(Request $request)
    {
        $d = $this->base($request);
        $d['searches'] = $this->analytics->searchTerms($d['range']->from, $d['range']->to, false, 50);
        $d['zeroSearches'] = $this->analytics->searchTerms($d['range']->from, $d['range']->to, true, 50);
        return view('admin.analytics.search', $d);
    }
}
