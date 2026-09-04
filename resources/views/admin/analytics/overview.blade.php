@extends('layouts.admin')
@section('title', 'Analytics Overview')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
@php $c = $range->compare; @endphp
<div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
    @include('admin.partials.stat', ['label' => 'Page Views', 'value' => compact_number($totals['page_views']), 'current' => $totals['page_views'], 'prev' => $prev['page_views'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Unique Visitors', 'value' => compact_number($totals['unique_visitors']), 'current' => $totals['unique_visitors'], 'prev' => $prev['unique_visitors'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Sessions', 'value' => compact_number($totals['sessions']), 'current' => $totals['sessions'], 'prev' => $prev['sessions'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'New / Returning', 'value' => compact_number($totals['new_visitors']).' / '.compact_number($totals['returning_visitors'])])
    @include('admin.partials.stat', ['label' => 'Avg Session Duration', 'value' => human_duration($totals['avg_session_duration']), 'current' => $totals['avg_session_duration'], 'prev' => $prev['avg_session_duration'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Avg Engagement Time', 'value' => human_duration($totals['avg_engagement_time']), 'current' => $totals['avg_engagement_time'], 'prev' => $prev['avg_engagement_time'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Engagement Rate', 'value' => number_format($totals['engagement_rate'], 1).'%', 'note' => 'Bounce '.number_format($totals['bounce_rate'], 1).'%'])
    @include('admin.partials.stat', ['label' => 'Pages / Session', 'value' => number_format($totals['pages_per_session'], 2)])
    @include('admin.partials.stat', ['label' => 'Vlog / Article Views', 'value' => compact_number($totals['vlog_views']).' / '.compact_number($totals['article_views'])])
    @include('admin.partials.stat', ['label' => 'Video Plays', 'value' => compact_number($totals['video_plays']), 'current' => $totals['video_plays'], 'prev' => $prev['video_plays'], 'compare' => $c, 'note' => human_duration($totals['watch_time']).' watch time'])
    @include('admin.partials.stat', ['label' => 'Avg Scroll Depth', 'value' => number_format($totals['avg_scroll_depth'], 0).'%'])
    @include('admin.partials.stat', ['label' => 'Searches / Shares / Outbound', 'value' => compact_number($totals['searches']).' / '.compact_number($totals['shares']).' / '.compact_number($totals['outbound_clicks'])])
</div>
<div class="card mt-5">
    <div class="flex items-center justify-between"><h2 class="card-title">Traffic</h2><span class="text-xs text-slate-400">{{ ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'][$series['granularity']] ?? 'Daily' }} · bots excluded ({{ compact_number($totals['bot_views']) }} bot views)</span></div>
    @php $ds = [['label' => 'Page views', 'data' => $series['datasets']['page_views']], ['label' => 'Unique visitors', 'data' => $series['datasets']['unique_visitors']], ['label' => 'Sessions', 'data' => $series['datasets']['sessions']]];
    if ($prevSeries) { $ds[] = ['label' => 'Page views (previous)', 'data' => $prevSeries['datasets']['page_views'], 'dashed' => true, 'color' => '#94a3b8']; } @endphp
    <div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => $ds]) }}'></canvas></div>
</div>
<div class="mt-5 grid gap-5 lg:grid-cols-3">
    @include('admin.partials.barlist', ['title' => 'Page types', 'rows' => $pageTypes, 'metric' => 'page_views'])
    @include('admin.partials.barlist', ['title' => 'Landing pages', 'rows' => $landing, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'Exit pages', 'rows' => $exit, 'capitalize' => false])
</div>
<div class="card mt-5">
    <h2 class="card-title">Top content</h2>
    <table class="table mt-3"><thead><tr><th>Title</th><th>Type</th><th class="text-right">Views</th><th class="text-right">Unique</th><th class="text-right">Avg eng.</th><th class="text-right">Plays</th><th class="text-right">Completion</th><th class="text-right">Shares</th></tr></thead><tbody>
    @forelse($topContent as $r)<tr><td><a href="{{ route('admin.posts.analytics', $r['post']) }}" class="font-medium text-indigo-700 hover:underline">{{ $r['post']->title }}</a></td><td class="text-xs uppercase text-slate-400">{{ $r['post']->type }}</td><td class="text-right">{{ number_format($r['views']) }}</td><td class="text-right">{{ number_format($r['unique_views']) }}</td><td class="text-right">{{ human_duration($r['avg_engagement']) }}</td><td class="text-right">{{ number_format($r['video_starts']) }}</td><td class="text-right">{{ $r['completion_rate'] }}%</td><td class="text-right">{{ $r['shares'] }}</td></tr>
    @empty<tr><td colspan="8" class="text-center text-slate-400">No data yet.</td></tr>@endforelse</tbody></table>
</div>
@endsection
