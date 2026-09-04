@extends('layouts.app')

@section('content')
@php $adsOn = $adsAllowed ?? true; $between = $siteNav['adSlots']['between_content'] ?? null; @endphp
<section class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <span>{{ $heading }}</span></nav>
    <h1 class="section-title mt-2">{{ $heading }}</h1>
    @if($subheading ?? null)<p class="section-sub">{{ $subheading }}</p>@endif

    @if($posts->count())
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $i => $p)
                @include('partials.post-card', ['p' => $p, 'lazy' => $i > 2])
                @if($adsOn && $between && $i === 5 && $posts->count() > 8)
                    <div class="sm:col-span-2 lg:col-span-3">@include('partials.ad', ['slot' => $between, 'adsAllowed' => $adsOn])</div>
                @endif
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @else
        <p class="mt-10 rounded-2xl bg-slate-50 p-10 text-center text-slate-500">Nothing here yet. Check back soon.</p>
    @endif
</section>
@endsection
