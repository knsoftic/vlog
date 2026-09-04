@extends('layouts.admin')
@section('title', 'Video Analytics')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
@php $c = $range->compare; @endphp
<div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
    @include('admin.partials.stat', ['label' => 'Video Starts', 'value' => compact_number($totals['video_plays']), 'current' => $totals['video_plays'], 'prev' => $prev['video_plays'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Unique Viewers', 'value' => compact_number($totals['video_unique_viewers'])])
    @include('admin.partials.stat', ['label' => 'Completed Views', 'value' => compact_number($totals['video_completes']), 'note' => '≥ 97% watched'])
    @include('admin.partials.stat', ['label' => 'Completion Rate', 'value' => number_format($totals['completion_rate'], 1).'%'])
    @include('admin.partials.stat', ['label' => 'Play Rate', 'value' => number_format($totals['play_rate'], 1).'%', 'note' => 'starts ÷ vlog page views'])
    @include('admin.partials.stat', ['label' => 'Total Watch Time', 'value' => human_duration($totals['watch_time']), 'current' => $totals['watch_time'], 'prev' => $prev['watch_time'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Avg Watch Time', 'value' => human_duration($totals['avg_watch_time'])])
    @include('admin.partials.stat', ['label' => 'Vlog Page Views', 'value' => compact_number($totals['vlog_views'])])
</div>
<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <div class="card xl:col-span-2"><h2 class="card-title">Plays, completes & watch time</h2><div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => [['label' => 'Starts', 'data' => $series['datasets']['video_plays']], ['label' => 'Completes', 'data' => $series['datasets']['video_completes']], ['label' => 'Watch time (s)', 'data' => $series['datasets']['watch_time'], 'axis' => 'y1']]]) }}'></canvas></div></div>
    <div class="card"><h2 class="card-title">Retention funnel</h2>@php $mx = max(1, $funnel['Starts']); @endphp<div class="mt-3 space-y-2">@foreach($funnel as $k => $v)<div class="bar-row"><span class="w-20">{{ $k }}</span><div class="flex-1"><div class="bar" style="width: {{ round($v / $mx * 100) }}%"></div></div><span class="w-14 text-right text-xs font-semibold">{{ number_format($v) }}</span></div>@endforeach</div><p class="help mt-3">Milestones are counted once per play; a page load alone never counts as a view.</p></div>
</div>
<div class="card mt-5">
    <h2 class="card-title">Per video</h2>
    <table class="table mt-3"><thead><tr><th>Video</th><th class="text-right">Page views</th><th class="text-right">Starts</th><th class="text-right">Play rate</th><th class="text-right">Completed</th><th class="text-right">Completion</th><th class="text-right">Watch time</th></tr></thead><tbody>
    @forelse($topVideos as $r)<tr><td><a href="{{ route('admin.posts.analytics', $r['post']) }}" class="font-medium text-indigo-700 hover:underline">{{ $r['post']->title }}</a> <span class="text-xs text-slate-400">{{ $r['post']->video_type }}</span></td><td class="text-right">{{ number_format($r['views']) }}</td><td class="text-right">{{ number_format($r['video_starts']) }}</td><td class="text-right">{{ $r['views'] > 0 ? round($r['video_starts'] / $r['views'] * 100, 1) : 0 }}%</td><td class="text-right">{{ number_format($r['completes']) }}</td><td class="text-right">{{ $r['completion_rate'] }}%</td><td class="text-right">{{ human_duration($r['watch_time']) }}</td></tr>
    @empty<tr><td colspan="7" class="text-center text-slate-400">No video data yet.</td></tr>@endforelse</tbody></table>
</div>
@endsection
