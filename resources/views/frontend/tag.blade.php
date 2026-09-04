@extends('layouts.app')

@section('content')
<section class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <span>Tag</span></nav>
    <h1 class="section-title mt-2">#{{ $tag->name }}</h1>
    <p class="section-sub">{{ $tag->description ?: $posts->total().' posts tagged with '.$tag->name }}</p>
    @if($posts->count())
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $i => $p)@include('partials.post-card', ['p' => $p, 'lazy' => $i > 2])@endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @else
        <p class="mt-10 rounded-2xl bg-slate-50 p-10 text-center text-slate-500">No posts with this tag yet.</p>
    @endif
</section>
@endsection
