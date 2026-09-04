@extends('layouts.admin')
@section('title', 'Reports & Export')
@section('actions')@include('admin.partials.range')@endsection
@section('content')
<p class="alert-info">Exports use the date range selected above ({{ $range->label() }}). AdSense and Search Console sections contain only data synced from the official Google APIs; when nothing is synced the report says "Data unavailable".</p>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach([
        ['traffic', 'Traffic Report', 'Daily page views, visitors, sessions, sources, countries, devices, landing pages.'],
        ['content', 'Content Report', 'Per-post views, engagement, video plays, completion, shares + internal searches.'],
        ['seo', 'SEO Report', 'Search Console summary, top queries and pages, and an on-page SEO audit of all posts.'],
        ['adsense', 'AdSense Report', 'Earnings, impressions, clicks, CTR, RPM by day, platform, country and ad unit.'],
        ['video', 'Video Performance Report', 'Plays, unique viewers, completion rate, watch time per video.'],
    ] as [$key, $title, $desc])
        <div class="card">
            <h2 class="text-base font-semibold">{{ $title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $desc }}</p>
            <div class="mt-4 flex gap-2">
                @foreach(['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $f => $fl)
                    <a href="{{ route('admin.reports.export', $range->query(['report' => $key, 'format' => $f])) }}" class="btn-secondary btn-sm">{{ $fl }}</a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
