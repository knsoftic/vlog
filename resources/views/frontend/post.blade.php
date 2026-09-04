@extends('layouts.app')

@section('content')
@php
    $adsOn = ($adsAllowed ?? false) && setting_bool('adsense.enabled');
    $slots = $siteNav['adSlots'];
    // Split content into paragraphs to place the in-article ad after the Nth paragraph, never adjacent to the video.
    $inArticle = $slots['in_article'] ?? null;
    $parts = preg_split('~(?<=</p>)~i', (string) $post->content, -1, PREG_SPLIT_NO_EMPTY);
    $offset = $inArticle ? max(2, (int) $inArticle->paragraph_offset) : 0;
    $showInArticle = $adsOn && $inArticle && count($parts) > $offset + 1;
    $ytId = $post->youtubeId(); $vimeoId = $post->vimeoId();
@endphp
@if($isPreview ?? false)
    <div class="bg-amber-100 py-2 text-center text-sm font-medium text-amber-900">Preview mode — this {{ $post->type }} is <strong>{{ $post->status }}</strong> and not visible to the public. <a href="{{ route('admin.'.($post->type === 'vlog' ? 'vlogs' : 'articles').'.edit', $post) }}" class="underline">Back to editor</a></div>
@endif
<article class="container-x pt-8">
    <nav class="text-xs text-slate-500" aria-label="Breadcrumb">
        @foreach($breadcrumbs as $i => [$name, $url])
            @if($i > 0)<span class="mx-1">/</span>@endif
            @if($loop->last)<span class="text-slate-700">{{ \Illuminate\Support\Str::limit($name, 60) }}</span>@else<a href="{{ $url }}" class="hover:underline">{{ $name }}</a>@endif
        @endforeach
    </nav>

    <div class="mt-8 grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <header>
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-wide">
                    @if($post->category)<a href="{{ $post->category->url }}" class="badge-brand">{{ $post->category->name }}</a>@endif
                    @if($post->subcategory)<a href="{{ $post->subcategory->url }}" class="chip">{{ $post->subcategory->name }}</a>@endif
                    <span class="text-slate-400">{{ $post->isVlog() ? 'Vlog' : 'Article' }} · {{ $post->reading_time }} min read</span>
                </div>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-4xl">{{ $post->title }}</h1>
                @if($post->excerpt)<p class="mt-4 text-lg text-slate-600">{{ $post->excerpt }}</p>@endif
                <div class="mt-5 flex flex-wrap items-center gap-4 text-sm text-slate-500">
                    @if($post->author)
                        <a href="{{ $post->author->url }}" class="flex items-center gap-2 font-medium text-slate-800"><img src="{{ $post->author->avatar_url }}" alt="" class="h-9 w-9 rounded-full object-cover" width="36" height="36">{{ $post->author->name }}</a>
                    @endif
                    <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('F j, Y') }}</time>
                    <span>{{ compact_number($post->views_count) }} views</span>
                    @if($post->hasVideo() && $post->video_plays_count)<span>{{ compact_number($post->video_plays_count) }} plays</span>@endif
                </div>
            </header>

            {{-- Video player (click-to-load for performance; no ads adjacent to the play button) --}}
            @if($post->hasVideo())
                <div class="video-frame group mt-6"
                     @if($ytId) data-video="youtube" data-id="{{ $ytId }}"
                     @elseif($vimeoId) data-video="vimeo" data-id="{{ $vimeoId }}"
                     @elseif($post->selfHostedVideoUrl()) data-video="html5" data-src="{{ $post->selfHostedVideoUrl() }}" data-poster="{{ $post->thumbnail_url }}"
                     @elseif($post->video_type === 'external') data-video="external"
                     @endif>
                    @if($post->video_type === 'external' && $post->video_embed)
                        <template>{!! $post->video_embed !!}</template>
                    @endif
                    <div class="video-poster" role="button" tabindex="0" aria-label="Play video: {{ $post->title }}">
                        @if($post->thumbnail_url)<img src="{{ $post->thumbnail_url }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="1280" height="720" fetchpriority="high">@endif
                        <span class="play"><svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
                    </div>
                </div>
            @elseif($post->featured_image_url)
                <figure class="mt-6 overflow-hidden rounded-2xl"><img src="{{ $post->featured_image_url }}" alt="{{ $post->featured_image_alt ?: $post->title }}" class="h-auto w-full" width="1280" height="720" fetchpriority="high"></figure>
            @endif

            {{-- Body --}}
            <div class="prose-content mt-8">
                @if($showInArticle)
                    {!! implode('', array_slice($parts, 0, $offset)) !!}
                    @include('partials.ad', ['slot' => $inArticle, 'adsAllowed' => $adsOn])
                    {!! implode('', array_slice($parts, $offset)) !!}
                @else
                    {!! $post->content !!}
                @endif
            </div>

            {{-- Tags + share --}}
            <div class="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6">
                <div class="flex flex-wrap gap-2">@foreach($post->tags as $t)<a href="{{ $t->url }}" class="chip">#{{ $t->name }}</a>@endforeach</div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-slate-500">Share:</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($post->url) }}" target="_blank" rel="noopener" data-share="facebook" class="chip">Facebook</a>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode($post->url) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" data-share="twitter" class="chip">X</a>
                    <a href="https://wa.me/?text={{ urlencode($post->title.' '.$post->url) }}" target="_blank" rel="noopener" data-share="whatsapp" class="chip">WhatsApp</a>
                    <button type="button" data-share="copy" class="chip">Copy link</button>
                </div>
            </div>

            @if($adsOn && isset($slots['below_content']))
                @include('partials.ad', ['slot' => $slots['below_content'], 'adsAllowed' => $adsOn])
            @endif

            {{-- Author box --}}
            @if($post->author)
                <div class="mt-10 flex gap-4 rounded-2xl bg-slate-50 p-5">
                    <img src="{{ $post->author->avatar_url }}" alt="" class="h-16 w-16 shrink-0 rounded-full object-cover" width="64" height="64" loading="lazy">
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Written by</p><a href="{{ $post->author->url }}" class="text-lg font-bold text-slate-900 hover:underline">{{ $post->author->name }}</a>@if($post->author->bio)<p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($post->author->bio), 220) }}</p>@endif</div>
                </div>
            @endif

            {{-- Comments --}}
            @if($post->allow_comments && !($isPreview ?? false))
                <section id="comments" class="mt-12">
                    <h2 class="text-xl font-bold">Comments ({{ $comments->count() }})</h2>
                    @if(session('success'))<p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
                    <div class="mt-5 space-y-5">
                        @forelse($comments as $c)
                            <div class="rounded-2xl ring-1 ring-slate-200/70 p-4">
                                <p class="text-sm font-semibold">{{ $c->name }} <span class="ml-2 text-xs font-normal text-slate-400">{{ $c->created_at->diffForHumans() }}</span></p>
                                <p class="mt-1 text-sm text-slate-700">{{ $c->content }}</p>
                                @foreach($c->replies as $r)<div class="mt-3 border-l-2 border-slate-200 pl-3"><p class="text-sm font-semibold">{{ $r->name }} <span class="ml-2 text-xs font-normal text-slate-400">{{ $r->created_at->diffForHumans() }}</span></p><p class="mt-1 text-sm text-slate-700">{{ $r->content }}</p></div>@endforeach
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Be the first to comment.</p>
                        @endforelse
                    </div>
                    <form method="post" action="{{ route('comments.store', $post) }}" class="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-5 sm:grid-cols-2">
                        @csrf
                        <h3 class="text-base font-semibold sm:col-span-2">Leave a comment</h3>
                        @if($errors->any())<p class="text-sm text-rose-600 sm:col-span-2">{{ $errors->first() }}</p>@endif
                        <input name="name" required maxlength="100" placeholder="Your name" value="{{ old('name') }}" class="rounded-lg border-slate-200 px-3 py-2 text-sm">
                        <input name="email" type="email" maxlength="190" placeholder="Email (optional, never shown)" value="{{ old('email') }}" class="rounded-lg border-slate-200 px-3 py-2 text-sm">
                        <input name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                        <textarea name="content" required minlength="3" maxlength="3000" rows="4" placeholder="Your comment…" class="rounded-lg border-slate-200 px-3 py-2 text-sm sm:col-span-2">{{ old('content') }}</textarea>
                        <div class="sm:col-span-2"><button class="btn-primary">Post comment</button></div>
                    </form>
                </section>
            @endif
        </div>

        {{-- Sidebar --}}
        <aside class="lg:col-span-4">
            <div class="lg:sticky lg:top-24 space-y-8">
                @if($adsOn && isset($slots['sidebar']))
                    @include('partials.ad', ['slot' => $slots['sidebar'], 'adsAllowed' => $adsOn])
                @endif
                @if($related->count())
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Related</h2>
                        <div class="mt-4 space-y-4">
                            @foreach($related->take(4) as $p)
                                <a href="{{ $p->url }}" class="group flex gap-3">
                                    <div class="relative h-16 w-28 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if($p->thumbnail_url)<img src="{{ $p->thumbnail_url }}" alt="" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" width="112" height="64">@endif</div>
                                    <div class="min-w-0"><h3 class="line-clamp-2 text-sm font-semibold text-slate-900 group-hover:underline">{{ $p->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ $p->published_at?->format('M j, Y') }}</p></div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($siteNav['categories']->count())
                    <div><h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Topics</h2><div class="mt-3 flex flex-wrap gap-2">@foreach($siteNav['categories'] as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach</div></div>
                @endif
            </div>
        </aside>
    </div>

    {{-- Related grid --}}
    @if($related->count())
        <section class="mt-16 border-t border-slate-100 pt-10">
            <h2 class="section-title">You might also like</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($related->take(6) as $i => $p)
                    @include('partials.post-card', ['p' => $p])
                    @if($adsOn && isset($slots['related']) && $i === 2)
                        <div class="sm:col-span-2 lg:col-span-3">@include('partials.ad', ['slot' => $slots['related'], 'adsAllowed' => $adsOn])</div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
