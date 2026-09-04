@extends('layouts.app')

@section('content')
<article class="container-x pt-10">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:underline">Home</a> <span class="mx-1">/</span> <span>{{ $page->title }}</span></nav>
    <div class="mx-auto max-w-3xl">
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $page->title }}</h1>
        <p class="mt-2 text-xs text-slate-400">Last updated {{ $page->updated_at->format('F j, Y') }}</p>
        <div class="prose-content mt-8">{!! $page->content !!}</div>
    </div>
</article>
@endsection
