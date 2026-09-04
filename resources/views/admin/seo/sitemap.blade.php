@extends('layouts.admin')
@section('title', 'Sitemap')
@section('actions')<a href="{{ route('sitemap') }}" target="_blank" class="btn-secondary">Open /sitemap.xml</a><form method="post" action="{{ route('admin.seo.sitemap.regenerate') }}">@csrf<button class="btn-primary">Regenerate</button></form>@endsection
@section('content')
<div class="grid gap-3 sm:grid-cols-4">
    @include('admin.partials.stat', ['label' => 'Published posts', 'value' => $counts['posts']])
    @include('admin.partials.stat', ['label' => 'Excluded (thin / noindex)', 'value' => $counts['excluded_thin']])
    @include('admin.partials.stat', ['label' => 'Categories', 'value' => $counts['categories']])
    @include('admin.partials.stat', ['label' => 'Pages', 'value' => $counts['pages']])
</div>
<div class="card mt-5">
    <p class="text-sm text-slate-600">The sitemap is generated dynamically (cached 30 min) and includes image and video extensions. It is referenced from <a href="{{ route('robots') }}" target="_blank" class="underline">robots.txt</a>. Status: {{ setting_bool('seo.sitemap_enabled', true) ? '<span class="badge-green">enabled</span>' : '<span class="badge-red">disabled</span>' }}</p>
    @if($lastLog)<p class="alert-warning mt-3">Last problem: {{ $lastLog->message }} ({{ $lastLog->created_at->diffForHumans() }})</p>@endif
    <pre class="mt-4 max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">{{ $preview }}</pre>
</div>
@endsection
