@extends('layouts.admin')
@section('title', 'Google Search Console')
@section('actions')
    @if($connected)@include('admin.partials.range')<form method="post" action="{{ route('admin.google.sync', 'search_console') }}">@csrf<button class="btn-secondary">Sync now</button></form>@endif
@endsection
@section('content')
@if(!$connected)
    <div class="card max-w-2xl">
        <h2 class="text-lg font-semibold">Connect Google Search Console</h2>
        <p class="mt-2 text-sm text-slate-600">Authorise read-only access to your Search Console property to see clicks, impressions, CTR, position, top queries, pages, countries and devices right here. Tokens are stored encrypted and never exposed to the frontend.</p>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-600"><li>Add your OAuth Client ID/Secret under <a href="{{ route('admin.settings.edit', 'google') }}" class="underline">Settings → Google Integrations</a>.</li><li>Click connect and choose the Google account that owns the property.</li><li>Pick the property and sync.</li></ol>
        <a href="{{ route('admin.google.connect', 'search_console') }}" class="btn-primary mt-4">Connect with Google</a>
    </div>
@else
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-white p-3 text-sm ring-1 ring-slate-200">
        <span class="badge-green">Connected</span>
        <span>Property: <b>{{ $token->account_id ?: 'not selected' }}</b></span>
        <span class="text-slate-500">Last sync: {{ $token->last_synced_at?->diffForHumans() ?? 'never' }} @if($token->last_status === 'failed')<span class="badge-red">failed</span> <span class="text-rose-600">{{ \Illuminate\Support\Str::limit($token->last_error, 120) }}</span>@endif</span>
        <form method="post" action="{{ route('admin.google.disconnect', 'search_console') }}" class="ml-auto" data-confirm="Disconnect Search Console?">@csrf<button class="btn-secondary btn-sm">Disconnect</button></form>
    </div>
    @if(!$token->account_id)
        <div class="card mb-5 max-w-xl"><h2 class="card-title">Select a property</h2>
            <form method="post" action="{{ route('admin.seo.search-console.site') }}" class="mt-3 flex gap-2">@csrf<select name="site_url" class="select">@foreach($sites as $s)<option value="{{ $s['url'] }}">{{ $s['url'] }} ({{ $s['permission'] }})</option>@endforeach @if(setting('gsc.site_url'))<option value="{{ setting('gsc.site_url') }}">{{ setting('gsc.site_url') }} (from settings)</option>@endif</select><button class="btn-primary">Use property</button></form>
            @if(!$sites)<p class="help mt-2">No properties returned. Make sure the Google account has access to the site in Search Console.</p>@endif
        </div>
    @endif
    @php $c = $range->compare; @endphp
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @include('admin.partials.stat', ['label' => 'Search Clicks', 'value' => $totals ? number_format($totals['clicks']) : null, 'current' => $totals['clicks'] ?? 0, 'prev' => $prev['clicks'] ?? 0, 'compare' => $c, 'unavailable' => !$totals, 'note' => $totals ? null : 'Sync pending'])
        @include('admin.partials.stat', ['label' => 'Search Impressions', 'value' => $totals ? number_format($totals['impressions']) : null, 'current' => $totals['impressions'] ?? 0, 'prev' => $prev['impressions'] ?? 0, 'compare' => $c, 'unavailable' => !$totals, 'note' => $totals ? null : 'Sync pending'])
        @include('admin.partials.stat', ['label' => 'CTR', 'value' => $totals ? number_format($totals['ctr'], 2).'%' : null, 'unavailable' => !$totals])
        @include('admin.partials.stat', ['label' => 'Average Position', 'value' => $totals ? number_format($totals['position'], 1) : null, 'unavailable' => !$totals, 'note' => $totals ? 'Data lags ~2 days' : null])
    </div>
    @if($series && count($series['labels']))
        <div class="card mt-5"><h2 class="card-title">Clicks, impressions & position</h2><div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => [['label' => 'Clicks', 'data' => $series['clicks']], ['label' => 'Impressions', 'data' => $series['impressions'], 'axis' => 'y1'], ['label' => 'Position', 'data' => $series['position'], 'dashed' => true, 'color' => '#94a3b8', 'axis' => 'y1']]]) }}'></canvas></div></div>
    @endif
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="card"><h2 class="card-title">Top queries</h2><table class="table mt-3"><thead><tr><th>Query</th><th class="text-right">Clicks</th><th class="text-right">Impressions</th><th class="text-right">CTR</th><th class="text-right">Position</th></tr></thead><tbody>@forelse($queries as $q)<tr><td>{{ $q['value'] }}</td><td class="text-right">{{ number_format($q['clicks']) }}</td><td class="text-right">{{ number_format($q['impressions']) }}</td><td class="text-right">{{ number_format($q['ctr'], 2) }}%</td><td class="text-right">{{ $q['position'] }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-400">No data yet.</td></tr>@endforelse</tbody></table></div>
        <div class="card"><h2 class="card-title">Top pages</h2><table class="table mt-3"><thead><tr><th>Page</th><th class="text-right">Clicks</th><th class="text-right">Impressions</th><th class="text-right">CTR</th><th class="text-right">Position</th></tr></thead><tbody>@forelse($pages as $q)<tr><td class="max-w-xs truncate text-xs" title="{{ $q['value'] }}">{{ str_replace(url('/'), '', $q['value']) }}</td><td class="text-right">{{ number_format($q['clicks']) }}</td><td class="text-right">{{ number_format($q['impressions']) }}</td><td class="text-right">{{ number_format($q['ctr'], 2) }}%</td><td class="text-right">{{ $q['position'] }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-400">No data yet.</td></tr>@endforelse</tbody></table></div>
        <div class="card"><h2 class="card-title">Countries</h2><table class="table mt-3"><thead><tr><th>Country</th><th class="text-right">Clicks</th><th class="text-right">Impressions</th><th class="text-right">CTR</th></tr></thead><tbody>@forelse($countries as $q)<tr><td>{{ \App\Services\GeoService::countryName(strtoupper(substr($q['value'], 0, 2))) }} <span class="text-xs text-slate-400">{{ $q['value'] }}</span></td><td class="text-right">{{ number_format($q['clicks']) }}</td><td class="text-right">{{ number_format($q['impressions']) }}</td><td class="text-right">{{ number_format($q['ctr'], 2) }}%</td></tr>@empty<tr><td colspan="4" class="text-center text-slate-400">No data yet.</td></tr>@endforelse</tbody></table></div>
        <div class="card"><h2 class="card-title">Devices</h2><table class="table mt-3"><thead><tr><th>Device</th><th class="text-right">Clicks</th><th class="text-right">Impressions</th><th class="text-right">CTR</th><th class="text-right">Position</th></tr></thead><tbody>@forelse($devices as $q)<tr><td class="capitalize">{{ strtolower($q['value']) }}</td><td class="text-right">{{ number_format($q['clicks']) }}</td><td class="text-right">{{ number_format($q['impressions']) }}</td><td class="text-right">{{ number_format($q['ctr'], 2) }}%</td><td class="text-right">{{ $q['position'] }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-400">No data yet.</td></tr>@endforelse</tbody></table></div>
    </div>
@endif
@endsection
