@extends('layouts.admin')
@section('title', 'Dashboard')
@section('actions')@include('admin.partials.range')@endsection

@section('content')
@php $c = $range->compare; $cur = (int) setting('adsense.currency', 'USD'); @endphp

{{-- Master overview cards: three impression types are deliberately kept in separate, clearly-labelled cards --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @include('admin.partials.stat', ['label' => 'Total Visitors', 'value' => compact_number($allTime['unique_visitors']), 'note' => 'All time (unique)'])
    @include('admin.partials.stat', ['label' => 'Today Visitors', 'value' => compact_number($today['unique_visitors']), 'note' => compact_number($today['page_views']).' page views today'])
    @include('admin.partials.stat', ['label' => 'Website Page Views', 'value' => compact_number($totals['page_views']), 'current' => $totals['page_views'], 'prev' => $prev['page_views'], 'compare' => $c, 'note' => 'Pages viewed on this site'])
    @include('admin.partials.stat', ['label' => 'Vlog Views', 'value' => compact_number($totals['vlog_views']), 'current' => $totals['vlog_views'], 'prev' => $prev['vlog_views'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Video Views', 'value' => compact_number($totals['video_plays']), 'current' => $totals['video_plays'], 'prev' => $prev['video_plays'], 'compare' => $c, 'note' => 'Actual video starts'])
    @include('admin.partials.stat', ['label' => 'Watch Time', 'value' => human_duration($totals['watch_time']), 'current' => $totals['watch_time'], 'prev' => $prev['watch_time'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Google Search Impressions', 'value' => $gscTotals ? compact_number($gscTotals['impressions']) : null, 'current' => $gscTotals['impressions'] ?? 0, 'prev' => $gscPrev['impressions'] ?? 0, 'compare' => $c, 'unavailable' => !$gscTotals, 'note' => $gscTotals ? 'Times shown in Google Search' : 'Connect Search Console'])
    @include('admin.partials.stat', ['label' => 'Google Search Clicks', 'value' => $gscTotals ? compact_number($gscTotals['clicks']) : null, 'current' => $gscTotals['clicks'] ?? 0, 'prev' => $gscPrev['clicks'] ?? 0, 'compare' => $c, 'unavailable' => !$gscTotals, 'note' => $gscTotals ? number_format($gscTotals['ctr'], 2).'% CTR' : 'Connect Search Console'])
    @include('admin.partials.stat', ['label' => 'AdSense Ad Impressions', 'value' => $adsTotals ? compact_number($adsTotals['impressions']) : null, 'current' => $adsTotals['impressions'] ?? 0, 'prev' => $adsPrev['impressions'] ?? 0, 'compare' => $c, 'unavailable' => !$adsTotals, 'note' => $adsTotals ? 'Ads displayed (API)' : 'Connect AdSense API'])
    @include('admin.partials.stat', ['label' => 'AdSense Clicks', 'value' => $adsTotals ? compact_number($adsTotals['clicks']) : null, 'current' => $adsTotals['clicks'] ?? 0, 'prev' => $adsPrev['clicks'] ?? 0, 'compare' => $c, 'unavailable' => !$adsTotals, 'note' => $adsTotals ? number_format($adsTotals['ctr'], 2).'% CTR' : 'Connect AdSense API'])
    @include('admin.partials.stat', ['label' => 'Estimated AdSense Earnings', 'value' => $adsTotals ? $adsTotals['currency'].' '.number_format($adsTotals['earnings'], 2) : null, 'current' => $adsTotals['earnings'] ?? 0, 'prev' => $adsPrev['earnings'] ?? 0, 'compare' => $c, 'unavailable' => !$adsTotals, 'note' => $adsTotals ? 'Synced '.($adsTotals['synced_at']?->diffForHumans() ?? '') : 'Connect AdSense API'])
    @include('admin.partials.stat', ['label' => 'Engagement', 'value' => number_format($totals['engagement_rate'], 1).'%', 'note' => human_duration($totals['avg_session_duration']).' avg session · '.number_format($totals['pages_per_session'], 1).' pages/session'])
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between"><h2 class="card-title">Traffic Overview</h2><span class="text-xs text-slate-400">{{ ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'][$series['granularity']] ?? 'Daily' }} · human traffic only</span></div>
        <div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => [['label' => 'Page views', 'data' => $series['datasets']['page_views']], ['label' => 'Unique visitors', 'data' => $series['datasets']['unique_visitors']], ['label' => 'Sessions', 'data' => $series['datasets']['sessions']]]]) }}'></canvas></div>
    </div>
    <div class="card" x-data="realtime('{{ route('admin.realtime.json') }}', @js($realtime))">
        <div class="flex items-center justify-between"><h2 class="card-title">Real-Time</h2><span class="flex items-center gap-1 text-xs text-emerald-600"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> live</span></div>
        <p class="mt-2 text-4xl font-bold" x-text="data.online"></p><p class="text-xs text-slate-500">visitors online (last 5 min) · <span x-text="data.last_minute"></span> views last minute</p>
        <h3 class="mt-4 text-[11px] font-semibold uppercase text-slate-400">Active pages</h3>
        <ul class="mt-1 space-y-1 text-sm"><template x-for="p in data.pages.slice(0,5)" :key="p.path"><li class="flex justify-between gap-2"><span class="truncate text-slate-700" x-text="p.title || p.path"></span><span class="font-semibold" x-text="p.count"></span></li></template><li x-show="!data.pages.length" class="text-slate-400">No one online right now.</li></ul>
        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
            <div><p class="font-semibold uppercase text-slate-400">Device</p><template x-for="(n,k) in data.devices" :key="k"><p><span x-text="k"></span>: <b x-text="n"></b></p></template></div>
            <div><p class="font-semibold uppercase text-slate-400">Country</p><template x-for="(n,k) in data.countries" :key="k"><p><span x-text="k"></span>: <b x-text="n"></b></p></template></div>
            <div><p class="font-semibold uppercase text-slate-400">Source</p><template x-for="(n,k) in data.sources" :key="k"><p><span x-text="k"></span>: <b x-text="n"></b></p></template></div>
        </div>
        <a href="{{ route('admin.analytics.realtime') }}" class="mt-3 block text-xs font-medium text-indigo-600">Open real-time view →</a>
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2 xl:grid-cols-4">
    <div class="card xl:col-span-2">
        <h2 class="card-title">Top Vlogs & Articles</h2>
        <table class="table mt-3"><thead><tr><th>Title</th><th class="text-right">Views</th><th class="text-right">Plays</th><th class="text-right">Avg eng.</th></tr></thead><tbody>
            @forelse($topContent as $row)<tr><td><a href="{{ route('admin.posts.analytics', $row['post']) }}" class="line-clamp-1 font-medium text-indigo-700 hover:underline">{{ $row['post']->title }}</a></td><td class="text-right">{{ number_format($row['views']) }}</td><td class="text-right">{{ number_format($row['video_starts']) }}</td><td class="text-right">{{ human_duration($row['avg_engagement']) }}</td></tr>
            @empty<tr><td colspan="4" class="text-center text-slate-400">No data for this period yet.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="card">
        <h2 class="card-title">Trending (7 days)</h2>
        <ol class="mt-3 space-y-2 text-sm">
            @forelse($trending as $i => $row)<li class="flex gap-2"><span class="w-5 text-slate-400">{{ $i + 1 }}.</span><a href="{{ $row['post']->url }}" target="_blank" class="line-clamp-1 flex-1 hover:underline">{{ $row['post']->title }}</a><span class="font-semibold">{{ compact_number($row['views']) }}</span></li>
            @empty<li class="text-slate-400">No data yet.</li>@endforelse
        </ol>
    </div>
    <div class="card">
        <h2 class="card-title">Traffic Sources</h2>
        @php $max = max(1, $sources[0]['sessions'] ?? 1); @endphp
        <div class="mt-3 space-y-2">
            @forelse($sources as $s)<div class="bar-row"><span class="w-24 truncate capitalize">{{ $s['value'] }}</span><div class="flex-1"><div class="bar" style="width: {{ round($s['sessions'] / $max * 100) }}%"></div></div><span class="w-12 text-right text-xs font-semibold">{{ compact_number($s['sessions']) }}</span></div>
            @empty<p class="text-sm text-slate-400">No data yet.</p>@endforelse
        </div>
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2 xl:grid-cols-4">
    <div class="card">
        <h2 class="card-title">Country Breakdown</h2>
        @php $max = max(1, $countries[0]['sessions'] ?? 1); @endphp
        <div class="mt-3 space-y-2">
            @forelse($countries as $s)<div class="bar-row"><span class="w-28 truncate">{{ \App\Services\GeoService::countryName($s['value']) }}</span><div class="flex-1"><div class="bar" style="width: {{ round($s['sessions'] / $max * 100) }}%"></div></div><span class="w-12 text-right text-xs font-semibold">{{ compact_number($s['sessions']) }}</span></div>
            @empty<p class="text-sm text-slate-400">No data yet (country needs a CDN header or GeoIP database).</p>@endforelse
        </div>
    </div>
    <div class="card">
        <h2 class="card-title">Device Breakdown</h2>
        <div class="chart-box-sm mt-3"><canvas data-type="doughnut" data-chart='{{ json_encode(['labels' => array_map(fn($d) => ucfirst($d['value']), $devices), 'datasets' => [['label' => 'Sessions', 'data' => array_map(fn($d) => $d['sessions'], $devices)]]]) }}'></canvas></div>
    </div>
    <div class="card">
        <h2 class="card-title">Search Performance</h2>
        @if($gscSeries && count($gscSeries['labels']))
            <div class="chart-box-sm mt-3"><canvas data-chart='{{ json_encode(['labels' => $gscSeries['labels'], 'datasets' => [['label' => 'Clicks', 'data' => $gscSeries['clicks']], ['label' => 'Impressions', 'data' => $gscSeries['impressions'], 'axis' => 'y1']]]) }}'></canvas></div>
        @else
            <p class="mt-3 text-sm text-slate-400">Data unavailable — connect Google Search Console under Settings → Google Integrations.</p>
        @endif
    </div>
    <div class="card">
        <h2 class="card-title">AdSense Performance</h2>
        @if($adsSeries && count($adsSeries['labels']))
            <div class="chart-box-sm mt-3"><canvas data-chart='{{ json_encode(['labels' => $adsSeries['labels'], 'money' => true, 'datasets' => [['label' => 'Earnings', 'data' => $adsSeries['earnings']], ['label' => 'Impressions', 'data' => $adsSeries['impressions'], 'axis' => 'y1']]]) }}'></canvas></div>
        @else
            <p class="mt-3 text-sm text-slate-400">Data unavailable — connect the AdSense Management API under Monetization → Settings.</p>
        @endif
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <div class="card">
        <h2 class="card-title">Video Engagement</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-slate-500">Play rate</dt><dd class="text-lg font-bold">{{ number_format($totals['play_rate'], 1) }}%</dd></div>
            <div><dt class="text-xs text-slate-500">Completion rate</dt><dd class="text-lg font-bold">{{ number_format($totals['completion_rate'], 1) }}%</dd></div>
            <div><dt class="text-xs text-slate-500">Unique viewers</dt><dd class="text-lg font-bold">{{ compact_number($totals['video_unique_viewers']) }}</dd></div>
            <div><dt class="text-xs text-slate-500">Avg watch time</dt><dd class="text-lg font-bold">{{ human_duration($totals['avg_watch_time']) }}</dd></div>
        </dl>
    </div>
    <div class="card">
        <h2 class="card-title">Content</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-slate-500">Published</dt><dd class="text-lg font-bold">{{ $counts['published'] }}</dd></div>
            <div><dt class="text-xs text-slate-500">Drafts</dt><dd class="text-lg font-bold">{{ $counts['drafts'] }}</dd></div>
            <div><dt class="text-xs text-slate-500">Scheduled</dt><dd class="text-lg font-bold">{{ $counts['scheduled'] }}</dd></div>
            <div><dt class="text-xs text-slate-500">Pending comments</dt><dd class="text-lg font-bold">{{ $counts['pending_comments'] }}</dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-400">Bot traffic excluded: {{ compact_number($totals['bot_views']) }} bot views in this period.</p>
    </div>
    <div class="card">
        <h2 class="card-title">Notifications & Health</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse($notifications as $n)<li class="flex gap-2"><span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $n->severity === 'critical' ? 'bg-rose-500' : ($n->severity === 'warning' ? 'bg-amber-500' : 'bg-indigo-500') }}"></span><a href="{{ route('admin.notifications') }}" class="line-clamp-1">{{ $n->title }}</a></li>
            @empty<li class="text-slate-400">No unread notifications.</li>@endforelse
        </ul>
        @if($recentErrors->count())<p class="mt-3 text-xs text-rose-600">{{ $recentErrors->count() }} recent server error(s) — <a href="{{ route('admin.logs.system') }}" class="underline">System Health</a></p>@endif
    </div>
</div>
@endsection
