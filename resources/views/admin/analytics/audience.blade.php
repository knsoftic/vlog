@extends('layouts.admin')
@section('title', 'Audience')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @include('admin.partials.stat', ['label' => 'Unique Visitors', 'value' => compact_number($totals['unique_visitors'])])
    @include('admin.partials.stat', ['label' => 'New Visitors', 'value' => compact_number($totals['new_visitors']), 'note' => $totals['sessions'] > 0 ? round($totals['new_visitors'] / $totals['sessions'] * 100).'% of sessions' : null])
    @include('admin.partials.stat', ['label' => 'Returning Visitors', 'value' => compact_number($totals['returning_visitors'])])
    @include('admin.partials.stat', ['label' => 'Avg Session', 'value' => human_duration($totals['avg_session_duration'])])
</div>
<div class="card mt-5"><h2 class="card-title">New vs returning</h2><div class="chart-box mt-3"><canvas data-type="bar" data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => [['label' => 'New', 'data' => $series['datasets']['new_visitors']], ['label' => 'Returning', 'data' => $series['datasets']['returning_visitors']]]]) }}'></canvas></div></div>
<div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    @include('admin.partials.barlist', ['title' => 'Countries', 'rows' => $country, 'country' => true])
    @include('admin.partials.barlist', ['title' => 'Cities', 'rows' => $city, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'Devices', 'rows' => $device])
    @include('admin.partials.barlist', ['title' => 'Browsers', 'rows' => $browser, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'Operating systems', 'rows' => $os, 'capitalize' => false])
    <div class="card text-sm text-slate-500"><h3 class="card-title">Privacy note</h3><p class="mt-2">Visitors are identified by a random first-party cookie, never by IP. Country comes from CDN headers or an optional local GeoIP database; city is only recorded when enabled in Settings → Analytics. Raw data is deleted after {{ setting('analytics.retention_days', 365) }} days.</p></div>
</div>
@endsection
