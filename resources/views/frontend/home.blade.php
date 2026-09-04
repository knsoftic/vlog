@extends('layouts.app')

@section('content')
@php $adsOn = $adsAllowed ?? true; $between = $siteNav['adSlots']['between_content'] ?? null; $sectionIndex = 0; @endphp
@foreach($sections as $s)
    @php $items = $data[$s->key] ?? null; @endphp
    @if($s->key === 'hero' && $items && $items->count())
        @php $main = $items->first(); $rest = $items->slice(1, 4); @endphp
        <section class="container-x pt-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <a href="{{ $main->url }}" class="group relative block overflow-hidden rounded-3xl bg-slate-900 lg:col-span-2" style="min-height:420px">
                    @if($main->featured_image_url)<img src="{{ $main->featured_image_url }}" alt="{{ $main->featured_image_alt ?: $main->title }}" class="absolute inset-0 h-full w-full object-cover opacity-80 transition duration-700 group-hover:scale-105" fetchpriority="high" width="1280" height="720">@endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                    <div class="absolute bottom-0 p-6 sm:p-8">
                        <div class="flex items-center gap-2">
                            <span class="badge-brand">{{ $s->title }}</span>
                            @if($main->category)<span class="rounded-full bg-white/20 px-2.5 py-0.5 text-[11px] font-semibold text-white backdrop-blur">{{ $main->category->name }}</span>@endif
                        </div>
                        <h1 class="mt-3 max-w-2xl text-2xl font-extrabold leading-tight text-white sm:text-4xl">{{ $main->title }}</h1>
                        @if($main->excerpt)<p class="mt-3 line-clamp-2 max-w-xl text-sm text-white/80 sm:text-base">{{ $main->excerpt }}</p>@endif
                        <div class="mt-4 flex items-center gap-3 text-xs text-white/70">
                            @if($main->author)<img src="{{ $main->author->avatar_url }}" alt="" class="h-6 w-6 rounded-full" width="24" height="24"> {{ $main->author->name }} ·@endif {{ $main->published_at?->format('M j, Y') }}
                            @if($main->hasVideo())<span class="ml-2 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 font-semibold text-slate-900"><svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg> Watch</span>@endif
                        </div>
                    </div>
                </a>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach($rest as $p)
                        <a href="{{ $p->url }}" class="group flex gap-3 rounded-2xl p-2 ring-1 ring-slate-200/70 transition hover:bg-slate-50">
                            <div class="relative h-20 w-32 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                @if($p->thumbnail_url)<img src="{{ $p->thumbnail_url }}" alt="" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" width="128" height="80">@endif
                            </div>
                            <div class="min-w-0 py-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide" style="color:var(--brand)">{{ $p->category?->name ?? ucfirst($p->type) }}</p>
                                <h3 class="line-clamp-2 text-sm font-bold text-slate-900">{{ $p->title }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $p->published_at?->format('M j') }} · {{ compact_number($p->views_count) }} views</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif($s->key === 'categories' && $items && $items->count())
        <section class="container-x mt-14">
            <div class="flex items-end justify-between"><div><h2 class="section-title">{{ $s->title }}</h2><p class="section-sub">{{ $s->subtitle }}</p></div><a href="{{ route('categories') }}" class="btn-ghost hidden sm:inline-flex">All categories</a></div>
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($items as $c)
                    <a href="{{ $c->url }}" class="group relative flex h-32 items-end overflow-hidden rounded-2xl bg-slate-800 p-4 text-white">
                        @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60 transition group-hover:scale-105" loading="lazy">@else<div class="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-900"></div>@endif
                        <div class="relative"><h3 class="font-bold">{{ $c->name }}</h3><p class="text-xs text-white/70">{{ $c->posts_count }} posts</p></div>
                    </a>
                @endforeach
            </div>
        </section>
    @elseif($s->key === 'newsletter')
        <section class="container-x mt-14">
            <div class="rounded-3xl bg-slate-900 px-6 py-12 text-center text-white sm:px-12">
                <h2 class="text-2xl font-extrabold sm:text-3xl">{{ $s->title }}</h2>
                <p class="mt-2 text-slate-300">{{ $s->subtitle }}</p>
                <form action="{{ route('contact.submit') }}" method="post" class="mx-auto mt-6 flex max-w-md gap-2">@csrf<input type="hidden" name="name" value="Newsletter"><input type="hidden" name="subject" value="Newsletter subscription"><input type="hidden" name="message" value="Please add me to the newsletter."><input type="email" name="email" required placeholder="you@example.com" class="flex-1 rounded-full px-4 py-3 text-slate-900"><button class="btn-primary">Subscribe</button></form>
            </div>
        </section>
    @elseif($items && $items->count())
        <section class="container-x mt-14">
            <div class="flex items-end justify-between">
                <div><h2 class="section-title">{{ $s->title }}</h2>@if($s->subtitle)<p class="section-sub">{{ $s->subtitle }}</p>@endif</div>
                @php $more = match($s->key) { 'latest' => route('vlogs'), 'trending' => route('trending'), 'popular' => route('popular'), 'articles' => route('articles'), default => null }; @endphp
                @if($more)<a href="{{ $more }}" class="btn-ghost hidden sm:inline-flex">View all</a>@endif
            </div>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-{{ $s->key === 'articles' ? '3' : '4' }}">
                @foreach($items as $p)
                    @include('partials.post-card', ['p' => $p, 'lazy' => true])
                @endforeach
            </div>
        </section>
        @php $sectionIndex++; @endphp
        @if($adsOn && $between && $sectionIndex === 2)
            <div class="container-x">@include('partials.ad', ['slot' => $between, 'adsAllowed' => $adsOn])</div>
        @endif
    @endif
@endforeach

@if(collect($data)->flatten(1)->isEmpty())
    <section class="container-x py-24 text-center">
        <h1 class="text-3xl font-extrabold">Welcome to {{ setting('site.name') }}</h1>
        <p class="mt-3 text-slate-500">No content has been published yet. Sign in to the <a href="{{ route('admin.login') }}" class="underline">admin panel</a> to add your first vlog.</p>
    </section>
@endif
@endsection
