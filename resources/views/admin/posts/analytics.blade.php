@extends('layouts.admin')
@section('title', 'Content Analytics')
@section('actions')@include('admin.partials.range')<a href="{{ $post->url }}" target="_blank" class="btn-secondary">View</a>@endsection

@section('content')
@php $c = $range->compare; @endphp
<div class="card mb-5 flex items-center gap-4">
    <div class="h-16 w-28 overflow-hidden rounded-lg bg-slate-100">@if($post->thumbnail_url)<img src="{{ $post->thumbnail_url }}" class="h-full w-full object-cover" alt="">@endif</div>
    <div class="min-w-0"><h2 class="truncate text-lg font-bold">{{ $post->title }}</h2><p class="text-xs text-slate-500">{{ ucfirst($post->type) }} · {{ $post->category?->name }} · {{ $post->author?->name }} · published {{ $post->published_at?->format('M j, Y') }}</p></div>
    <div class="ml-auto text-right"><p class="text-2xl font-bold">{{ number_format($post->views_count) }}</p><p class="text-xs text-slate-500">all-time views · {{ number_format($post->unique_views_count) }} unique</p></div>
</div>

<div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
    @include('admin.partials.stat', ['label' => 'Total Views', 'value' => number_format($totals['views']), 'current' => $totals['views'], 'prev' => $prev['views'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Unique Views', 'value' => number_format($totals['unique_views']), 'current' => $totals['unique_views'], 'prev' => $prev['unique_views'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Today', 'value' => number_format($today['views'])])
    @include('admin.partials.stat', ['label' => '7 Day Views', 'value' => number_format($d7['views']), 'current' => $d7['views'], 'prev' => $d7prev['views'], 'compare' => true, 'note' => 'vs previous 7 days'])
    @include('admin.partials.stat', ['label' => '30 Day Views', 'value' => number_format($d30['views'])])
    @include('admin.partials.stat', ['label' => 'This Month', 'value' => number_format($month['views']), 'current' => $month['views'], 'prev' => $lastMonth['views'], 'compare' => true, 'note' => 'vs previous month'])
    @include('admin.partials.stat', ['label' => 'Avg Engagement', 'value' => human_duration($totals['avg_engagement']), 'note' => number_format($totals['scroll'], 0).'% avg scroll'])
    @include('admin.partials.stat', ['label' => 'Avg Watch Time', 'value' => human_duration($totals['avg_watch_time']), 'note' => number_format($totals['video_starts']).' plays'])
    @include('admin.partials.stat', ['label' => 'Completion Rate', 'value' => number_format($totals['completion_rate'], 1).'%', 'note' => number_format($totals['completes']).' completed'])
    @include('admin.partials.stat', ['label' => 'Google Search Clicks', 'value' => $search ? number_format($search['clicks']) : null, 'unavailable' => !$search, 'note' => $search ? 'Position '.number_format($search['position'], 1) : 'No Search Console data for this URL'])
    @include('admin.partials.stat', ['label' => 'Google Search Impressions', 'value' => $search ? number_format($search['impressions']) : null, 'unavailable' => !$search, 'note' => $search ? number_format($search['ctr'], 2).'% CTR' : 'Connect / sync Search Console'])
    @include('admin.partials.stat', ['label' => 'Social Shares', 'value' => number_format($totals['shares']), 'note' => number_format($totals['outbound_clicks']).' outbound clicks'])
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <div class="card xl:col-span-2"><h3 class="card-title">Daily views, plays & watch time</h3><div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => [['label' => 'Views', 'data' => $series['views']], ['label' => 'Video plays', 'data' => $series['plays']], ['label' => 'Watch time (s)', 'data' => $series['watch'], 'axis' => 'y1']]]) }}'></canvas></div></div>
    <div class="card">
        <h3 class="card-title">Video funnel</h3>
        @php $f = ['Starts' => $totals['video_starts'], '25%' => $totals['p25'], '50%' => $totals['p50'], '75%' => $totals['p75'], '90%' => $totals['p90'], 'Complete' => $totals['completes']]; $mx = max(1, $totals['video_starts']); @endphp
        <div class="mt-3 space-y-2">@foreach($f as $k => $v)<div class="bar-row"><span class="w-16">{{ $k }}</span><div class="flex-1"><div class="bar" style="width: {{ round($v / $mx * 100) }}%"></div></div><span class="w-12 text-right text-xs font-semibold">{{ number_format($v) }}</span></div>@endforeach</div>
        <p class="mt-3 text-xs text-slate-400">{{ number_format($totals['video_unique_viewers']) }} unique viewers · play rate {{ number_format($totals['play_rate'], 1) }}%</p>
    </div>
</div>

<div class="mt-5 grid gap-5 md:grid-cols-3">
    @foreach([['Traffic Sources', $sources, false], ['Countries', $countries, true], ['Devices', $devices, false]] as [$title, $rows, $isCountry])
        <div class="card"><h3 class="card-title">{{ $title }}</h3>@php $mx = max(1, $rows[0]['views'] ?? 1); @endphp
            <div class="mt-3 space-y-2">@forelse($rows as $r)<div class="bar-row"><span class="w-28 truncate capitalize">{{ $isCountry ? \App\Services\GeoService::countryName($r['value']) : $r['value'] }}</span><div class="flex-1"><div class="bar" style="width: {{ round($r['views'] / $mx * 100) }}%"></div></div><span class="w-10 text-right text-xs font-semibold">{{ number_format($r['views']) }}</span></div>@empty<p class="text-sm text-slate-400">No data.</p>@endforelse</div>
        </div>
    @endforeach
</div>
@endsection
