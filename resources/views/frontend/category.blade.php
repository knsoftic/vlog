@extends('layouts.app')

@section('content')
<section class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <a href="{{ route('categories') }}" class="hover:underline">Categories</a>@if($category->parent) <span class="mx-1">/</span> <a href="{{ $category->parent->url }}" class="hover:underline">{{ $category->parent->name }}</a>@endif <span class="mx-1">/</span> <span>{{ $category->name }}</span></nav>
    <div class="mt-4 flex flex-col gap-6 md:flex-row md:items-center">
        @if($category->image_url)<img src="{{ $category->image_url }}" alt="" class="h-32 w-full rounded-2xl object-cover md:w-56" width="224" height="128">@endif
        <div><h1 class="section-title">{{ $category->name }}</h1>@if($category->description)<p class="section-sub max-w-2xl">{{ $category->description }}</p>@endif<p class="mt-2 text-xs text-slate-400">{{ $posts->total() }} posts</p></div>
    </div>
    @if($category->children->count())
        <div class="mt-6 flex flex-wrap gap-2">@foreach($category->children as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach</div>
    @endif
    @if($posts->count())
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $i => $p)@include('partials.post-card', ['p' => $p, 'lazy' => $i > 2])@endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @else
        <p class="mt-10 rounded-2xl bg-slate-50 p-10 text-center text-slate-500">No posts in this category yet.</p>
    @endif
</section>
@endsection
