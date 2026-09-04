@extends('layouts.app')

@section('content')
<section class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <span>Categories</span></nav>
    <h1 class="section-title mt-2">Explore Categories</h1>
    <p class="section-sub">Browse every topic we cover.</p>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($categories as $c)
            <div class="card group p-0">
                <a href="{{ $c->url }}" class="relative block h-36 overflow-hidden bg-slate-800">
                    @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="h-full w-full object-cover opacity-70 transition group-hover:scale-105" loading="lazy">@else<div class="h-full w-full bg-gradient-to-br from-slate-700 to-slate-900"></div>@endif
                    <div class="absolute bottom-0 p-4 text-white"><h2 class="text-lg font-bold">{{ $c->name }}</h2><p class="text-xs text-white/70">{{ $c->posts_count }} posts</p></div>
                </a>
                <div class="p-4">
                    @if($c->description)<p class="line-clamp-2 text-sm text-slate-500">{{ $c->description }}</p>@endif
                    @if($c->children->count())<div class="mt-3 flex flex-wrap gap-1.5">@foreach($c->children as $s)<a href="{{ $s->url }}" class="chip">{{ $s->name }}</a>@endforeach</div>@endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
