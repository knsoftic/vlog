@extends('layouts.admin')
@section('title', 'AdSense Dashboard')
@section('actions')
    @if($connected)@include('admin.partials.range')<form method="post" action="{{ route('admin.monetization.sync') }}">@csrf<button class="btn-secondary">Sync now</button></form>@endif
@endsection
@section('content')
@if(!$connected)
    <div class="card max-w-2xl">
        <h2 class="text-lg font-semibold">Connect the AdSense Management API</h2>
        <p class="mt-2 text-sm text-slate-600">All earnings and performance numbers on this page come exclusively from Google's authorised reporting API. Nothing is estimated or calculated locally, and no local script ever counts ad clicks.</p>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-600"><li>Enable the <b>AdSense Management API</b> in your Google Cloud project and add the OAuth Client ID/Secret under <a href="{{ route('admin.settings.edit', 'google') }}" class="underline">Settings → Google Integrations</a>.</li><li>Click connect and sign in with the Google account that owns the AdSense account.</li><li>Reports sync automatically every 6 hours.</li></ol>
        <a href="{{ route('admin.google.connect', 'adsense') }}" class="btn-primary mt-4">Connect with Google</a>
    </div>
@else
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-white p-3 text-sm ring-1 ring-slate-200">
        <span class="badge-green">Connected</span><span>{{ $token->account_label ?: $token->account_id }}</span>
        <span class="text-slate-500">Last sync: {{ $token->last_synced_at?->diffForHumans() ?? 'never' }} @if($token->last_status === 'failed')<span class="badge-red">failed</span> <span class="text-rose-600">{{ \Illuminate\Support\Str::limit($token->last_error, 120) }}</span>@endif</span>
        <span class="ml-auto flex gap-1">@foreach(['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'] as $g => $gl)<a href="{{ route('admin.monetization.dashboard', $range->query(['granularity' => $g])) }}" class="tab {{ $granularity === $g ? 'active' : '' }}">{{ $gl }}</a>@endforeach</span>
        <form method="post" action="{{ route('admin.google.disconnect', 'adsense') }}" data-confirm="Disconnect AdSense?">@csrf<button class="btn-secondary btn-sm">Disconnect</button></form>
    </div>
    @php $cur = $summary['currency']; $c = $range->compare; $fmt = fn($v) => $cur.' '.number_format($v, 2); @endphp
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach([['Today', 'today'], ['Yesterday', 'yesterday'], ['Last 7 days', 'last_7'], ['Last 30 days', 'last_30'], ['This month', 'this_month'], ['Previous month', 'last_month']] as [$l, $k])
            <div class="stat"><p class="stat-label">{{ $l }} earnings</p><p class="stat-value">{{ $fmt($summary[$k]) }}</p><p class="stat-note">estimated · API</p></div>
        @endforeach
    </div>
    @if($totals)
        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
            @include('admin.partials.stat', ['label' => 'Estimated Earnings', 'value' => $fmt($totals['earnings']), 'current' => $totals['earnings'], 'prev' => $prev['earnings'] ?? 0, 'compare' => $c])
            @include('admin.partials.stat', ['label' => 'AdSense Page Views', 'value' => compact_number($totals['page_views']), 'current' => $totals['page_views'], 'prev' => $prev['page_views'] ?? 0, 'compare' => $c, 'note' => 'as counted by AdSense'])
            @include('admin.partials.stat', ['label' => 'Ad Requests', 'value' => compact_number($totals['ad_requests']), 'note' => 'coverage '.number_format($totals['coverage'], 1).'%'])
            @include('admin.partials.stat', ['label' => 'Matched Requests', 'value' => compact_number($totals['matched_ad_requests'])])
            @include('admin.partials.stat', ['label' => 'Ad Impressions', 'value' => compact_number($totals['impressions']), 'current' => $totals['impressions'], 'prev' => $prev['impressions'] ?? 0, 'compare' => $c])
            @include('admin.partials.stat', ['label' => 'Clicks', 'value' => compact_number($totals['clicks']), 'current' => $totals['clicks'], 'prev' => $prev['clicks'] ?? 0, 'compare' => $c])
            @include('admin.partials.stat', ['label' => 'CTR / CPC', 'value' => number_format($totals['ctr'], 2).'% / '.number_format($totals['cpc'], 3)])
            @include('admin.partials.stat', ['label' => 'Page / Impr. / Req. RPM', 'value' => number_format($totals['page_rpm'], 2).' / '.number_format($totals['impression_rpm'], 2).' / '.number_format($totals['ad_request_rpm'], 2), 'note' => 'Viewability '.number_format($totals['viewability'] * 100, 1).'%'])
        </div>
        <div class="card mt-5"><h2 class="card-title">Performance ({{ $granularity }})</h2><div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'money' => true, 'datasets' => [['label' => 'Earnings', 'data' => $series['earnings']], ['label' => 'Impressions', 'data' => $series['impressions'], 'axis' => 'y1'], ['label' => 'Clicks', 'data' => $series['clicks'], 'axis' => 'y1']]]) }}'></canvas></div></div>
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            @foreach([['Platform performance', $platforms], ['Country performance', $countries], ['Ad unit performance', $adUnits]] as [$t, $rows])
                <div class="card"><h2 class="card-title">{{ $t }}</h2><table class="table mt-3"><thead><tr><th>Name</th><th class="text-right">Impr.</th><th class="text-right">Clicks</th><th class="text-right">CTR</th><th class="text-right">Earnings</th></tr></thead><tbody>@forelse($rows as $r)<tr><td class="text-xs">{{ $r['value'] }}</td><td class="text-right">{{ compact_number($r['impressions']) }}</td><td class="text-right">{{ $r['clicks'] }}</td><td class="text-right">{{ number_format($r['ctr'], 2) }}%</td><td class="text-right">{{ number_format($r['earnings'], 2) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-400">No data.</td></tr>@endforelse</tbody></table></div>
            @endforeach
        </div>
        <div class="card mt-5"><h2 class="card-title">Date-wise performance</h2><div class="table-wrap mt-3"><table class="table"><thead><tr><th>Date</th><th class="text-right">Page views</th><th class="text-right">Ad requests</th><th class="text-right">Matched</th><th class="text-right">Impressions</th><th class="text-right">Clicks</th><th class="text-right">CTR</th><th class="text-right">CPC</th><th class="text-right">Page RPM</th><th class="text-right">Viewability</th><th class="text-right">Earnings</th></tr></thead><tbody>
        @foreach($daily as $d)<tr><td>{{ $d->report_date->format('M j, Y') }}</td><td class="text-right">{{ number_format($d->page_views) }}</td><td class="text-right">{{ number_format($d->ad_requests) }}</td><td class="text-right">{{ number_format($d->matched_ad_requests) }}</td><td class="text-right">{{ number_format($d->impressions) }}</td><td class="text-right">{{ $d->clicks }}</td><td class="text-right">{{ number_format($d->ctr * 100, 2) }}%</td><td class="text-right">{{ number_format($d->cpc, 3) }}</td><td class="text-right">{{ number_format($d->page_rpm, 2) }}</td><td class="text-right">{{ number_format($d->viewability * 100, 1) }}%</td><td class="text-right font-semibold">{{ number_format($d->earnings, 2) }}</td></tr>@endforeach
        </tbody></table></div></div>
    @else
        <p class="alert-warning mt-4">Data unavailable for this period — sync pending. Click "Sync now" or wait for the scheduled sync.</p>
    @endif
@endif
@endsection
