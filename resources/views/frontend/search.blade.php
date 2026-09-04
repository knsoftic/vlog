@extends('layouts.app')

@section('content')
<section class="container-x pt-10">
    <h1 class="section-title">Search</h1>
    <form action="{{ route('search') }}" method="get" class="mt-5 max-w-2xl" data-search role="search">
        <label class="sr-only" for="q">Search</label>
        <div class="flex gap-2">
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Search vlogs, articles, categories, tags, authors…" autocomplete="off" autofocus class="flex-1 rounded-full border border-slate-200 px-5 py-3 text-base shadow-sm focus:outline-none focus:ring-2" style="--tw-ring-color:var(--brand)">
            <button class="btn-primary">Search</button>
        </div>
    </form>

    @if($q !== '')
        <p class="mt-6 text-sm text-slate-500">{{ $posts->total() }} result{{ $posts->total() === 1 ? '' : 's' }} for <strong class="text-slate-800">“{{ $q }}”</strong></p>
        @if($posts->count())
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($posts as $i => $p)@include('partials.post-card', ['p' => $p, 'lazy' => $i > 2])@endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @else
            <div class="mt-8 rounded-2xl bg-slate-50 p-10 text-center">
                <p class="font-semibold text-slate-700">No results found.</p>
                <p class="mt-1 text-sm text-slate-500">Try different keywords or browse a topic below.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">@foreach($siteNav['categories'] as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach</div>
            </div>
        @endif
    @endif
</section>
@endsection
