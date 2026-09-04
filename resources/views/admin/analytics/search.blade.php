@extends('layouts.admin')
@section('title', 'Site Search')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
<div class="grid grid-cols-2 gap-3 md:grid-cols-3">
    @include('admin.partials.stat', ['label' => 'Searches', 'value' => number_format($totals['searches'])])
    @include('admin.partials.stat', ['label' => 'Zero-result searches', 'value' => number_format($totals['zero_result_searches']), 'note' => $totals['searches'] > 0 ? round($totals['zero_result_searches'] / $totals['searches'] * 100, 1).'% of searches' : null])
    @include('admin.partials.stat', ['label' => 'Sessions with search', 'value' => $totals['sessions'] > 0 ? round($totals['searches'] / $totals['sessions'] * 100, 1).'%' : '0%'])
</div>
<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <div class="card"><h2 class="card-title">All search terms</h2><table class="table mt-3"><thead><tr><th>Term</th><th class="text-right">Searches</th><th class="text-right">Avg results</th><th>Last</th></tr></thead><tbody>@forelse($searches as $s)<tr><td>{{ $s['term'] }}</td><td class="text-right">{{ $s['searches'] }}</td><td class="text-right">{{ $s['avg_results'] }}</td><td class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($s['last_at'])->diffForHumans() }}</td></tr>@empty<tr><td colspan="4" class="text-center text-slate-400">No searches yet.</td></tr>@endforelse</tbody></table></div>
    <div class="card"><h2 class="card-title">Zero-result searches</h2><p class="help">What visitors wanted but could not find — ideas for new content.</p><table class="table mt-3"><thead><tr><th>Term</th><th class="text-right">Searches</th><th>Last</th></tr></thead><tbody>@forelse($zeroSearches as $s)<tr><td>{{ $s['term'] }}</td><td class="text-right">{{ $s['searches'] }}</td><td class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($s['last_at'])->diffForHumans() }}</td></tr>@empty<tr><td colspan="3" class="text-center text-slate-400">None.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
