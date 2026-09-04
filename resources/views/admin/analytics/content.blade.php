@extends('layouts.admin')
@section('title', 'Content Analytics')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    @foreach([null => 'All', 'vlog' => 'Vlogs', 'article' => 'Articles'] as $k => $l)<a href="{{ route('admin.analytics.content', $range->query(['type' => $k, 'sort' => $sort])) }}" class="tab {{ $type === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach
    <span class="ml-auto text-xs text-slate-500">Sort by</span>
    @foreach(['views' => 'Views', 'unique_views' => 'Unique', 'engagement_time' => 'Engagement', 'video_starts' => 'Plays', 'watch_time' => 'Watch time', 'shares' => 'Shares'] as $k => $l)<a href="{{ route('admin.analytics.content', $range->query(['type' => $type, 'sort' => $k])) }}" class="tab {{ $sort === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach
</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Title</th><th>Type</th><th class="text-right">Views</th><th class="text-right">Unique</th><th class="text-right">Avg engagement</th><th class="text-right">Scroll</th><th class="text-right">Plays</th><th class="text-right">Completion</th><th class="text-right">Watch time</th><th class="text-right">Shares</th></tr></thead>
    <tbody>
    @forelse($topContent as $i => $r)
        <tr><td class="text-slate-400">{{ $i + 1 }}</td><td><a href="{{ route('admin.posts.analytics', $r['post']) }}" class="font-medium text-indigo-700 hover:underline">{{ $r['post']->title }}</a></td><td class="text-xs uppercase text-slate-400">{{ $r['post']->type }}</td><td class="text-right">{{ number_format($r['views']) }}</td><td class="text-right">{{ number_format($r['unique_views']) }}</td><td class="text-right">{{ human_duration($r['avg_engagement']) }}</td><td class="text-right">{{ $r['scroll'] }}%</td><td class="text-right">{{ number_format($r['video_starts']) }}</td><td class="text-right">{{ $r['completion_rate'] }}%</td><td class="text-right">{{ human_duration($r['watch_time']) }}</td><td class="text-right">{{ $r['shares'] }}</td></tr>
    @empty<tr><td colspan="11" class="py-10 text-center text-slate-400">No content data for this period.</td></tr>@endforelse
    </tbody></table></div>
<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <div class="card"><h2 class="card-title">Top internal searches</h2><table class="table mt-3"><thead><tr><th>Term</th><th class="text-right">Searches</th><th class="text-right">Avg results</th></tr></thead><tbody>@forelse($searches as $s)<tr><td>{{ $s['term'] }}</td><td class="text-right">{{ $s['searches'] }}</td><td class="text-right">{{ $s['avg_results'] }}</td></tr>@empty<tr><td colspan="3" class="text-center text-slate-400">None yet.</td></tr>@endforelse</tbody></table></div>
    <div class="card"><h2 class="card-title">Zero-result searches <span class="font-normal text-slate-400">— content opportunities</span></h2><table class="table mt-3"><thead><tr><th>Term</th><th class="text-right">Searches</th></tr></thead><tbody>@forelse($zeroSearches as $s)<tr><td>{{ $s['term'] }}</td><td class="text-right">{{ $s['searches'] }}</td></tr>@empty<tr><td colspan="2" class="text-center text-slate-400">None — every search found something.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
