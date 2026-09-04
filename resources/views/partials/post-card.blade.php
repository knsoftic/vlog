@php
    /** @var \App\Models\Post $p */
    $img = $p->thumbnail_url;
    $lazy = ($lazy ?? true) ? 'lazy' : 'eager';
    $size = $size ?? 'md';
@endphp
<article class="card group animate-on-scroll {{ $size === 'lg' ? 'md:col-span-2' : '' }}">
    <a href="{{ $p->url }}" class="card-media block" aria-label="{{ $p->title }}">
        @if($img)
            <img src="{{ $img }}" alt="{{ $p->featured_image_alt ?: $p->title }}" loading="{{ $lazy }}" decoding="async" width="640" height="360">
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-600 text-white"><span class="text-4xl font-black opacity-30">{{ mb_substr($p->title, 0, 1) }}</span></div>
        @endif
        @if($p->hasVideo())
            <span class="play-badge"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
            @if($p->video_duration)<span class="duration-badge">{{ gmdate($p->video_duration >= 3600 ? 'G:i:s' : 'i:s', $p->video_duration) }}</span>@endif
        @endif
        @if($p->is_trending)<span class="badge-brand absolute left-3 top-3">Trending</span>@endif
    </a>
    <div class="p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            @if($p->category)<a href="{{ $p->category->url }}" class="hover:text-slate-700" style="color:var(--brand)">{{ $p->category->name }}</a><span>·</span>@endif
            <span>{{ $p->type === 'vlog' ? 'Vlog' : 'Article' }}</span>
        </div>
        <h3 class="mt-1.5 line-clamp-2 text-base font-bold leading-snug text-slate-900 {{ $size === 'lg' ? 'md:text-xl' : '' }}"><a href="{{ $p->url }}" class="hover:underline decoration-2 underline-offset-2">{{ $p->title }}</a></h3>
        @if($size !== 'sm' && $p->excerpt)<p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $p->excerpt }}</p>@endif
        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
            @if($p->author)<img src="{{ $p->author->avatar_url }}" alt="" class="h-5 w-5 rounded-full" loading="lazy" width="20" height="20"><a href="{{ $p->author->url }}" class="font-medium text-slate-700 hover:underline">{{ $p->author->name }}</a><span>·</span>@endif
            <time datetime="{{ $p->published_at?->toIso8601String() }}">{{ $p->published_at?->format('M j, Y') }}</time>
            <span class="ml-auto">{{ compact_number($p->views_count) }} views</span>
        </div>
    </div>
</article>
