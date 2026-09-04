<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AdSenseService;
use App\Services\AnalyticsService;
use App\Services\AuditLogger;
use App\Services\ReportExportService;
use App\Services\SearchConsoleService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        protected AnalyticsService $analytics,
        protected AdSenseService $adsense,
        protected SearchConsoleService $gsc,
        protected ReportExportService $export,
        protected AuditLogger $audit,
    ) {
    }

    public function index(Request $request)
    {
        $range = DateRange::fromRequest($request, '30d');
        return view('admin.reports.index', compact('range'));
    }

    public function export(Request $request)
    {
        $data = $request->validate(['report' => ['required', Rule::in(['traffic', 'content', 'seo', 'adsense', 'video'])], 'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])]]);
        $range = DateRange::fromRequest($request, '30d');
        $this->analytics->ensureFresh();
        $title = ucfirst($data['report']).' Report';
        $sections = match ($data['report']) {
            'traffic' => $this->traffic($range),
            'content' => $this->content($range),
            'seo' => $this->seo($range),
            'adsense' => $this->adsenseReport($range),
            'video' => $this->video($range),
        };
        $this->audit->log('report_exported', 'reports', null, "{$title} exported as {$data['format']} ({$range->label()})");
        $file = $data['report'].'-report-'.$range->from->format('Ymd').'-'.$range->to->format('Ymd');
        return $this->export->export($data['format'], $title.' — '.$range->label(), $sections, $file);
    }

    protected function traffic(DateRange $r): array
    {
        $series = $this->analytics->series($r->from, $r->to, ['page_views', 'unique_visitors', 'sessions', 'new_visitors', 'returning_visitors', 'total_duration', 'bounces', 'bot_views']);
        $rows = [];
        foreach ($series['labels'] as $i => $d) {
            $rows[] = [$d, $series['datasets']['page_views'][$i], $series['datasets']['unique_visitors'][$i], $series['datasets']['sessions'][$i], $series['datasets']['new_visitors'][$i], $series['datasets']['returning_visitors'][$i], $series['datasets']['total_duration'][$i], $series['datasets']['bounces'][$i], $series['datasets']['bot_views'][$i]];
        }
        $t = $this->analytics->totals($r->from, $r->to);
        return [
            ['title' => 'Summary', 'headers' => ['Metric', 'Value'], 'rows' => [
                ['Page views', $t['page_views']], ['Unique visitors', $t['unique_visitors']], ['Sessions', $t['sessions']], ['New visitors', $t['new_visitors']], ['Returning visitors', $t['returning_visitors']],
                ['Avg session duration (s)', round($t['avg_session_duration'])], ['Avg engagement time (s)', round($t['avg_engagement_time'])], ['Bounce rate %', round($t['bounce_rate'], 1)], ['Pages / session', round($t['pages_per_session'], 2)], ['Bot views (excluded)', $t['bot_views']],
            ]],
            ['title' => 'Daily', 'headers' => ['Date', 'Page views', 'Unique visitors', 'Sessions', 'New', 'Returning', 'Total duration (s)', 'Bounces', 'Bot views'], 'rows' => $rows],
            ['title' => 'Sources', 'headers' => ['Source', 'Sessions', 'Page views', 'Visitors'], 'rows' => array_map(fn ($x) => [$x['value'], $x['sessions'], $x['page_views'], $x['visitors']], $this->analytics->dimension('source', $r->from, $r->to, 50))],
            ['title' => 'Countries', 'headers' => ['Country', 'Sessions', 'Page views', 'Visitors'], 'rows' => array_map(fn ($x) => [$x['value'], $x['sessions'], $x['page_views'], $x['visitors']], $this->analytics->dimension('country', $r->from, $r->to, 50))],
            ['title' => 'Devices', 'headers' => ['Device', 'Sessions', 'Page views'], 'rows' => array_map(fn ($x) => [$x['value'], $x['sessions'], $x['page_views']], $this->analytics->dimension('device', $r->from, $r->to, 10))],
            ['title' => 'Landing pages', 'headers' => ['Path', 'Sessions'], 'rows' => array_map(fn ($x) => [$x['value'], $x['sessions']], $this->analytics->dimension('landing', $r->from, $r->to, 50))],
        ];
    }

    protected function content(DateRange $r): array
    {
        $top = $this->analytics->topContent($r->from, $r->to, 200);
        return [
            ['title' => 'Content performance', 'headers' => ['Title', 'Type', 'URL', 'Views', 'Unique views', 'Avg engagement (s)', 'Video plays', 'Completion %', 'Watch time (s)', 'Shares', 'Avg scroll %'], 'rows' => array_map(fn ($x) => [
                $x['post']->title, $x['post']->type, $x['post']->url, $x['views'], $x['unique_views'], $x['avg_engagement'], $x['video_starts'], $x['completion_rate'], $x['watch_time'], $x['shares'], $x['scroll'],
            ], $top)],
            ['title' => 'Internal searches', 'headers' => ['Term', 'Searches', 'Avg results'], 'rows' => array_map(fn ($x) => [$x['term'], $x['searches'], $x['avg_results']], $this->analytics->searchTerms($r->from, $r->to, false, 100))],
            ['title' => 'Zero-result searches', 'headers' => ['Term', 'Searches'], 'rows' => array_map(fn ($x) => [$x['term'], $x['searches']], $this->analytics->searchTerms($r->from, $r->to, true, 100))],
        ];
    }

    protected function seo(DateRange $r): array
    {
        $connected = $this->gsc->isConnected();
        $t = $connected ? $this->gsc->totals($r->from, $r->to) : null;
        return [
            ['title' => 'Search Console summary', 'headers' => ['Metric', 'Value'], 'rows' => $t ? [['Clicks', $t['clicks']], ['Impressions', $t['impressions']], ['CTR %', round($t['ctr'], 2)], ['Avg position', round($t['position'], 1)]] : [['Status', 'Data unavailable — Search Console not connected or not synced']]],
            ['title' => 'Top queries', 'headers' => ['Query', 'Clicks', 'Impressions', 'CTR %', 'Position'], 'rows' => $connected ? array_map(fn ($x) => [$x['value'], $x['clicks'], $x['impressions'], round($x['ctr'], 2), $x['position']], $this->gsc->breakdown('query', $r->from, $r->to, 200)) : []],
            ['title' => 'Top pages', 'headers' => ['Page', 'Clicks', 'Impressions', 'CTR %', 'Position'], 'rows' => $connected ? array_map(fn ($x) => [$x['value'], $x['clicks'], $x['impressions'], round($x['ctr'], 2), $x['position']], $this->gsc->breakdown('page', $r->from, $r->to, 200)) : []],
            ['title' => 'Content SEO audit', 'headers' => ['Title', 'URL', 'Words', 'Meta description', 'Focus keyword', 'Alt text', 'Robots'], 'rows' => Post::published()->get()->map(fn ($p) => [$p->title, $p->url, $p->word_count, $p->meta_description ? 'yes' : 'MISSING', $p->focus_keyword ?: '-', $p->featured_image_alt ? 'yes' : ($p->featured_image ? 'MISSING' : '-'), $p->robotsDirective()])->all()],
        ];
    }

    protected function adsenseReport(DateRange $r): array
    {
        if (! $this->adsense->isConnected()) {
            return [['title' => 'AdSense', 'headers' => ['Status'], 'rows' => [['Data unavailable — AdSense API not connected']]]];
        }
        $t = $this->adsense->totals($r->from, $r->to);
        $s = $this->adsense->series($r->from, $r->to, 'day');
        $daily = [];
        foreach ($s['labels'] as $i => $d) {
            $daily[] = [$d, $s['page_views'][$i], $s['impressions'][$i], $s['clicks'][$i], $s['earnings'][$i]];
        }
        return [
            ['title' => 'AdSense summary (API data)', 'headers' => ['Metric', 'Value'], 'rows' => $t ? [
                ['Estimated earnings ('.$t['currency'].')', round($t['earnings'], 2)], ['Page views', $t['page_views']], ['Ad requests', $t['ad_requests']], ['Matched requests', $t['matched_ad_requests']], ['Impressions', $t['impressions']], ['Clicks', $t['clicks']],
                ['CTR %', round($t['ctr'], 2)], ['CPC', round($t['cpc'], 4)], ['Page RPM', round($t['page_rpm'], 2)], ['Impression RPM', round($t['impression_rpm'], 2)], ['Viewability %', round($t['viewability'] * 100, 1)],
            ] : [['Status', 'No data for this period (sync pending)']]],
            ['title' => 'Daily', 'headers' => ['Date', 'Page views', 'Impressions', 'Clicks', 'Earnings'], 'rows' => $daily],
            ['title' => 'By platform', 'headers' => ['Platform', 'Page views', 'Impressions', 'Clicks', 'Earnings'], 'rows' => array_map(fn ($x) => [$x['value'], $x['page_views'], $x['impressions'], $x['clicks'], round($x['earnings'], 2)], $this->adsense->breakdown('platform', $r->from, $r->to))],
            ['title' => 'By country', 'headers' => ['Country', 'Page views', 'Impressions', 'Clicks', 'Earnings'], 'rows' => array_map(fn ($x) => [$x['value'], $x['page_views'], $x['impressions'], $x['clicks'], round($x['earnings'], 2)], $this->adsense->breakdown('country', $r->from, $r->to, 100))],
            ['title' => 'By ad unit', 'headers' => ['Ad unit', 'Impressions', 'Clicks', 'Earnings'], 'rows' => array_map(fn ($x) => [$x['value'], $x['impressions'], $x['clicks'], round($x['earnings'], 2)], $this->adsense->breakdown('ad_unit', $r->from, $r->to, 100))],
        ];
    }

    protected function video(DateRange $r): array
    {
        $top = $this->analytics->topContent($r->from, $r->to, 200, 'vlog', 'video_starts');
        $t = $this->analytics->totals($r->from, $r->to);
        return [
            ['title' => 'Video summary', 'headers' => ['Metric', 'Value'], 'rows' => [['Video plays', $t['video_plays']], ['Unique viewers', $t['video_unique_viewers']], ['Completed views', $t['video_completes']], ['Total watch time (s)', $t['watch_time']], ['Avg watch time (s)', round($t['avg_watch_time'])], ['Play rate %', round($t['play_rate'], 1)], ['Completion rate %', round($t['completion_rate'], 1)]]],
            ['title' => 'Per video', 'headers' => ['Title', 'URL', 'Page views', 'Plays', 'Completed', 'Completion %', 'Watch time (s)'], 'rows' => array_map(fn ($x) => [$x['post']->title, $x['post']->url, $x['views'], $x['video_starts'], $x['completes'], $x['completion_rate'], $x['watch_time']], $top)],
        ];
    }
}
