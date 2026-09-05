@php
    $meta = $meta ?? app(\App\Services\SeoService::class)->meta();
    $consent = app(\App\Http\Middleware\TrackPageView::class)->consentState(request());
    $adsEnabled = setting_bool('adsense.enabled') && setting('adsense.client_id') && !(auth()->check() && setting_bool('adsense.hide_for_admins', true)) && !($isPreview ?? false);
    $adsAllowed = ($adsAllowed ?? true) && $adsEnabled;
    $isBot = app(\App\Services\BotDetector::class)->classify(request())['is_bot'];
    if ($isBot && setting_bool('adsense.hide_for_bots', true)) { $adsAllowed = false; }
    $brand = setting('brand.primary_color', '#e11d48');
    $ga4 = setting_bool('analytics.ga4_enabled') && setting('analytics.ga4_id') ? setting('analytics.ga4_id') : null;
    $socialLinks = json_decode((string) setting('site.social_links', '{}'), true) ?: [];
    $adsenseCfg = $adsAllowed ? ['client' => 'ca-'.ltrim(str_replace('ca-', '', (string) setting('adsense.client_id')), '-'), 'lazy' => setting_bool('adsense.lazy_load', true)] : null;
    $vhRoutes = ['consent' => route('consent.store'), 'heartbeat' => route('track.heartbeat'), 'video' => route('track.video'), 'event' => route('track.event'), 'suggest' => route('search.suggest')];
    $vhConfig = [
        'csrf' => csrf_token(),
        'track' => setting_bool('analytics.internal_enabled', true) && ! ($isPreview ?? false),
        'heartbeat' => (int) setting('analytics.heartbeat_seconds', 15),
        'consentEnabled' => setting_bool('consent.enabled', true),
        'consentRequired' => (bool) $consent['required'],
        'ga4' => $ga4,
        'adsense' => $adsenseCfg,
        'postId' => $post->id ?? null,
        'postType' => $post->type ?? null,
        'routes' => $vhRoutes,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">
    <meta name="robots" content="{{ $meta['robots'] }}">
    @if(!empty($meta['keywords']))<meta name="keywords" content="{{ $meta['keywords'] }}">@endif
    @if(!empty($meta['author']))<meta name="author" content="{{ $meta['author'] }}">@endif
    <meta name="publisher" content="{{ $meta['publisher'] }}">
    <meta name="copyright" content="&copy; {{ date('Y') }} {{ $meta['publisher'] }}">
    <link rel="canonical" href="{{ $meta['canonical'] }}">
    <meta property="og:type" content="{{ $meta['og_type'] }}">
    <meta property="og:site_name" content="{{ setting('site.name') }}">
    <meta property="og:locale" content="{{ $meta['locale'] }}">
    <meta property="og:title" content="{{ $meta['og_title'] }}">
    <meta property="og:description" content="{{ $meta['og_description'] }}">
    <meta property="og:url" content="{{ $meta['canonical'] }}">
    @if(!empty($meta['og_image']))<meta property="og:image" content="{{ $meta['og_image'] }}">@endif
    @if(!empty($meta['published_time']))<meta property="article:published_time" content="{{ $meta['published_time'] }}"><meta property="article:modified_time" content="{{ $meta['modified_time'] }}">@endif
    @if(!empty($meta['author_url']))<meta property="article:author" content="{{ $meta['author_url'] }}">@endif
    @if(!empty($meta['section']))<meta property="article:section" content="{{ $meta['section'] }}">@endif
    @foreach(($meta['tags'] ?? []) as $tag)<meta property="article:tag" content="{{ $tag }}">@endforeach
    @if(setting('seo.facebook_page'))<meta property="article:publisher" content="{{ setting('seo.facebook_page') }}">@endif
    <meta name="twitter:card" content="{{ $meta['twitter_card'] }}">
    @if(!empty($meta['twitter_creator']))<meta name="twitter:creator" content="{{ $meta['twitter_creator'] }}">@endif
    @if(setting('seo.twitter_handle'))<meta name="twitter:site" content="{{ setting('seo.twitter_handle') }}">@endif
    <meta name="twitter:title" content="{{ $meta['og_title'] }}">
    <meta name="twitter:description" content="{{ $meta['og_description'] }}">
    @if(!empty($meta['og_image']))<meta name="twitter:image" content="{{ $meta['og_image'] }}">@endif
    @if(setting('seo.google_verification'))<meta name="google-site-verification" content="{{ setting('seo.google_verification') }}">@endif
    @if(setting('seo.bing_verification'))<meta name="msvalidate.01" content="{{ setting('seo.bing_verification') }}">@endif
    <meta name="theme-color" content="{{ $brand }}">
    @if(setting('site.favicon'))<link rel="icon" href="{{ media_url(setting('site.favicon')) }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @if($adsAllowed)<link rel="preconnect" href="https://pagead2.googlesyndication.com">@endif
    <style>:root{--brand:{{ $brand }};--accent:{{ setting('brand.accent_color', '#0f172a') }}}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Google Consent Mode v2: defaults are set BEFORE any Google tag loads --}}
    @if(setting_bool('consent.consent_mode_v2', true))
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            'ad_storage': '{{ $consent['advertising'] ? 'granted' : 'denied' }}',
            'ad_user_data': '{{ $consent['advertising'] ? 'granted' : 'denied' }}',
            'ad_personalization': '{{ $consent['advertising'] ? 'granted' : 'denied' }}',
            'analytics_storage': '{{ $consent['analytics'] ? 'granted' : 'denied' }}',
            'wait_for_update': 500
        });
    </script>
    @endif
    @if(setting('consent.cmp') === 'external' && setting('consent.cmp_script'))
        {!! setting('consent.cmp_script') !!}
    @endif
    <script>window.VH = {!! json_encode($vhConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
    @foreach(($meta['schema'] ?? []) as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-2">Skip to content</a>

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/90 backdrop-blur">
        <div class="container-x flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900">
                @if(setting('site.logo'))
                    <img src="{{ media_url(setting('site.logo')) }}" alt="{{ setting('site.name') }}" class="h-9 w-auto" width="120" height="36">
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background:var(--brand)"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
                    <span>{{ setting('site.name') }}</span>
                @endif
            </a>
            <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                @foreach($siteNav['headerMenu'] as $item)
                    <a href="{{ $item->url }}" target="{{ $item->target }}" class="rounded-full px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 {{ request()->is(ltrim($item->url, '/') ?: '/') ? 'bg-slate-100 text-slate-900' : '' }}">{{ $item->label }}</a>
                @endforeach
                @if($siteNav['categories']->count())
                <div class="group relative">
                    <button class="rounded-full px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Topics ▾</button>
                    <div class="invisible absolute left-0 top-full z-50 mt-1 w-56 rounded-xl bg-white p-2 opacity-0 shadow-xl ring-1 ring-slate-200 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                        @foreach($siteNav['categories'] as $c)
                            <a href="{{ $c->url }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $c->name }}</a>
                        @endforeach
                    </div>
                </div>
                @endif
            </nav>
            <div class="flex items-center gap-2">
                <form action="{{ route('search') }}" method="get" class="hidden md:block" data-search role="search">
                    <label class="sr-only" for="q-header">Search</label>
                    <input id="q-header" type="search" name="q" value="{{ request('q') }}" placeholder="Search vlogs…" autocomplete="off" class="w-48 rounded-full border-0 bg-slate-100 px-4 py-2 text-sm ring-1 ring-transparent transition focus:w-64 focus:bg-white focus:ring-slate-300 focus:outline-none">
                </form>
                <button type="button" class="rounded-full p-2 text-slate-600 hover:bg-slate-100 md:hidden" data-search-toggle aria-label="Search"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
                <button type="button" class="rounded-full p-2 text-slate-600 hover:bg-slate-100 lg:hidden" data-nav-toggle aria-expanded="false" aria-label="Menu"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            </div>
        </div>
        <div class="container-x hidden pb-3 md:hidden" data-search-bar hidden>
            <form action="{{ route('search') }}" method="get" data-search><input type="search" name="q" placeholder="Search vlogs, articles, topics…" autocomplete="off" class="w-full rounded-full bg-slate-100 px-4 py-2.5 text-sm focus:outline-none"></form>
        </div>
        <nav class="hidden border-t border-slate-100 bg-white lg:hidden" data-nav aria-label="Mobile">
            <div class="container-x flex flex-col py-2">
                @foreach($siteNav['headerMenu'] as $item)<a href="{{ $item->url }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $item->label }}</a>@endforeach
                <div class="mt-1 flex flex-wrap gap-2 px-3 py-2">@foreach($siteNav['categories'] as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach</div>
            </div>
        </nav>
    </header>

    @if($adsAllowed && isset($siteNav['adSlots']['header']))
        @include('partials.ad', ['slot' => $siteNav['adSlots']['header'], 'adsAllowed' => $adsAllowed])
    @endif

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    @if($adsAllowed && isset($siteNav['adSlots']['footer']))
        <div class="container-x">@include('partials.ad', ['slot' => $siteNav['adSlots']['footer'], 'adsAllowed' => $adsAllowed])</div>
    @endif

    {{-- Footer --}}
    <footer class="mt-16 border-t border-slate-100 bg-slate-50">
        <div class="container-x grid gap-10 py-12 md:grid-cols-4">
            <div class="md:col-span-2">
                <a href="{{ route('home') }}" class="text-lg font-extrabold text-slate-900">{{ setting('site.name') }}</a>
                <p class="mt-3 max-w-md text-sm text-slate-500">{{ setting('site.description') }}</p>
                <div class="mt-4 flex gap-3">
                    @foreach($socialLinks as $net => $url)
                        @if($url)<a href="{{ $url }}" rel="noopener nofollow" target="_blank" class="chip capitalize">{{ $net }}</a>@endif
                    @endforeach
                </div>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Explore</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @if(setting_bool('content.vlogs_enabled', true))<li><a href="{{ route('vlogs') }}" class="text-slate-600 hover:text-slate-900">Latest Vlogs</a></li>@endif
                    @if(setting_bool('content.articles_enabled', true))<li><a href="{{ route('articles') }}" class="text-slate-600 hover:text-slate-900">Articles</a></li>@endif
                    <li><a href="{{ route('trending') }}" class="text-slate-600 hover:text-slate-900">Trending</a></li>
                    <li><a href="{{ route('categories') }}" class="text-slate-600 hover:text-slate-900">Categories</a></li>
                    @foreach($siteNav['footerMenu'] as $item)<li><a href="{{ $item->url }}" class="text-slate-600 hover:text-slate-900">{{ $item->label }}</a></li>@endforeach
                </ul>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Legal</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach($siteNav['footerPages'] as $p)<li><a href="{{ route('page.show', $p->slug) }}" class="text-slate-600 hover:text-slate-900">{{ $p->title }}</a></li>@endforeach
                    @if(setting_bool('consent.enabled', true))<li><a href="#" data-consent-open class="text-slate-600 hover:text-slate-900">Cookie preferences</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-200/70">
            <div class="container-x flex flex-col items-center justify-between gap-2 py-5 text-xs text-slate-500 sm:flex-row">
                <p>&copy; {{ date('Y') }} {{ setting('site.name') }}. {{ setting('site.footer_text') ?: 'All rights reserved.' }}</p>
                <p><a href="{{ route('page.show', 'privacy-policy') }}" class="hover:text-slate-900">Privacy Policy</a> · <a href="{{ route('sitemap') }}" class="hover:text-slate-900">Sitemap</a></p>
            </div>
        </div>
    </footer>

    @if(setting_bool('consent.enabled', true) && setting('consent.cmp') !== 'external')
        @include('partials.consent')
    @endif
    @stack('scripts')
</body>
</html>
