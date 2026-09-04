@extends('layouts.admin')
@section('title', 'Traffic')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
@php $c = $range->compare; @endphp
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @include('admin.partials.stat', ['label' => 'Page Views', 'value' => compact_number($totals['page_views']), 'current' => $totals['page_views'], 'prev' => $prev['page_views'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Sessions', 'value' => compact_number($totals['sessions']), 'current' => $totals['sessions'], 'prev' => $prev['sessions'], 'compare' => $c])
    @include('admin.partials.stat', ['label' => 'Bounce Rate', 'value' => number_format($totals['bounce_rate'], 1).'%', 'note' => 'single page, <10s engagement'])
    @include('admin.partials.stat', ['label' => 'Bot Views (excluded)', 'value' => compact_number($totals['bot_views']), 'note' => compact_number($totals['bot_sessions']).' bot sessions · crawlers never blocked'])
</div>
<div class="card mt-5"><h2 class="card-title">Human vs bot traffic</h2><div class="chart-box mt-3"><canvas data-chart='{{ json_encode(['labels' => $series['labels'], 'datasets' => [['label' => 'Page views', 'data' => $series['datasets']['page_views']], ['label' => 'Sessions', 'data' => $series['datasets']['sessions']], ['label' => 'Bot views', 'data' => $series['datasets']['bot_views'], 'dashed' => true, 'color' => '#94a3b8']]]) }}'></canvas></div></div>
<div class="mt-5 grid gap-5 lg:grid-cols-3">
    @include('admin.partials.barlist', ['title' => 'Landing pages', 'rows' => $landing, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'Exit pages', 'rows' => $exit, 'capitalize' => false])
    @include('admin.partials.barlist', ['title' => 'Page types', 'rows' => $pageTypes, 'metric' => 'page_views'])
</div>
@endsection
