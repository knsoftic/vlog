@extends('layouts.admin')
@section('title', 'Traffic Sources')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    @include('admin.partials.barlist', ['title' => 'Sources', 'rows' => $source])
    @include('admin.partials.barlist', ['title' => 'Mediums', 'rows' => $medium])
    @include('admin.partials.barlist', ['title' => 'Referrer domains', 'rows' => $referrer, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'UTM source', 'rows' => $utm_source, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'UTM medium', 'rows' => $utm_medium, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'UTM campaign', 'rows' => $utm_campaign, 'capitalize' => false])
</div>
<div class="card mt-5">
    <h2 class="card-title">Source detail</h2>
    <table class="table mt-3"><thead><tr><th>Source</th><th class="text-right">Sessions</th><th class="text-right">Page views</th><th class="text-right">Visitors</th><th class="text-right">Avg duration</th></tr></thead><tbody>
    @forelse($source as $r)<tr><td class="capitalize">{{ $r['value'] }}</td><td class="text-right">{{ number_format($r['sessions']) }}</td><td class="text-right">{{ number_format($r['page_views']) }}</td><td class="text-right">{{ number_format($r['visitors']) }}</td><td class="text-right">{{ human_duration($r['avg_duration']) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-400">No data.</td></tr>@endforelse
    </tbody></table>
    <p class="help mt-2">Referral spam domains are filtered before attribution. Direct = no referrer and no UTM.</p>
</div>
@endsection
