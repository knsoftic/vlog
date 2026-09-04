@extends('layouts.app')

@section('content')
<section class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <span>Author</span></nav>
    <div class="mt-4 flex flex-col gap-6 rounded-3xl bg-slate-50 p-6 sm:flex-row sm:items-center sm:p-8">
        <img src="{{ $author->avatar_url }}" alt="{{ $author->name }}" class="h-28 w-28 shrink-0 rounded-full object-cover ring-4 ring-white" width="112" height="112">
        <div class="min-w-0 flex-1">
            <h1 class="text-3xl font-extrabold text-slate-900">{{ $author->name }}</h1>
            @if($author->bio)<p class="mt-2 max-w-2xl text-slate-600">{{ strip_tags($author->bio) }}</p>@endif
            <div class="mt-3 flex flex-wrap gap-2">@foreach(($author->social_links ?? []) as $net => $url)@if($url)<a href="{{ $url }}" rel="noopener nofollow" target="_blank" class="chip capitalize">{{ $net }}</a>@endif @endforeach</div>
        </div>
        <dl class="grid grid-cols-2 gap-4 text-center sm:grid-cols-4">
            <div><dt class="text-[11px] font-semibold uppercase text-slate-400">Posts</dt><dd class="text-xl font-bold">{{ $stats['posts'] }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase text-slate-400">Views</dt><dd class="text-xl font-bold">{{ compact_number($stats['views']) }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase text-slate-400">Video plays</dt><dd class="text-xl font-bold">{{ compact_number($stats['video_views']) }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase text-slate-400">Avg engagement</dt><dd class="text-xl font-bold">{{ human_duration($stats['avg_engagement']) }}</dd></div>
        </dl>
    </div>
    @if($posts->count())
        <h2 class="section-title mt-10">Latest from {{ $author->name }}</h2>
        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $i => $p)@include('partials.post-card', ['p' => $p, 'lazy' => $i > 2])@endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @else
        <p class="mt-10 rounded-2xl bg-slate-50 p-10 text-center text-slate-500">No published posts yet.</p>
    @endif
</section>
@endsection
