@php
    $u = auth()->user();
    $nav = [
        ['Dashboard', 'admin.dashboard', 'admin.dashboard', null, 'M3 12l9-9 9 9M5 10v10h14V10'],
        ['Content', null, 'admin.vlogs.*|admin.articles.*|admin.categories.*|admin.tags.*|admin.authors.*|admin.media.*|admin.comments.*', 'posts.view', 'M4 6h16M4 12h16M4 18h10', [
            ['Vlogs', 'admin.vlogs.index', 'admin.vlogs.*', 'posts.view'], ['Articles', 'admin.articles.index', 'admin.articles.*', 'posts.view'],
            ['Categories', 'admin.categories.index', 'admin.categories.*', 'categories.manage'], ['Tags', 'admin.tags.index', 'admin.tags.*', 'categories.manage'],
            ['Authors', 'admin.authors.index', 'admin.authors.*', 'users.manage'], ['Media Library', 'admin.media.index', 'admin.media.*', 'media.manage'], ['Comments', 'admin.comments.index', 'admin.comments.*', 'comments.moderate'],
        ]],
        ['Analytics', null, 'admin.analytics.*|admin.reports.*', 'analytics.view', 'M4 19h16M7 16V9m5 7V5m5 11v-4', [
            ['Overview', 'admin.analytics.overview', 'admin.analytics.overview', null], ['Real-Time', 'admin.analytics.realtime', 'admin.analytics.realtime', null], ['Traffic', 'admin.analytics.traffic', 'admin.analytics.traffic', null],
            ['Content', 'admin.analytics.content', 'admin.analytics.content', null], ['Video Analytics', 'admin.analytics.video', 'admin.analytics.video', null], ['Audience', 'admin.analytics.audience', 'admin.analytics.audience', null],
            ['Sources', 'admin.analytics.sources', 'admin.analytics.sources', null], ['Site Search', 'admin.analytics.search', 'admin.analytics.search', null], ['Reports & Export', 'admin.reports.index', 'admin.reports.*', null],
        ]],
        ['SEO', null, 'admin.seo.*', 'seo.manage', 'M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z', [
            ['SEO Overview', 'admin.seo.overview', 'admin.seo.overview', null], ['Search Console', 'admin.seo.search-console', 'admin.seo.search-console', null], ['Sitemap', 'admin.seo.sitemap', 'admin.seo.sitemap', null],
            ['Redirects', 'admin.seo.redirects', 'admin.seo.redirects', null], ['Broken Links', 'admin.seo.broken-links', 'admin.seo.broken-links', null],
        ]],
        ['Monetization', null, 'admin.monetization.*', 'monetization.manage', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-8V6m0 10v2m9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0z', [
            ['AdSense Dashboard', 'admin.monetization.dashboard', 'admin.monetization.dashboard', null], ['Ad Units', 'admin.monetization.ad-units', 'admin.monetization.ad-units', null], ['Ad Placement', 'admin.monetization.placement', 'admin.monetization.placement', null],
            ['Ads.txt', 'admin.monetization.ads-txt', 'admin.monetization.ads-txt', null], ['Monetization Settings', 'admin.monetization.settings', 'admin.monetization.settings', null], ['Policy Checklist', 'admin.monetization.checklist', 'admin.monetization.checklist', null],
        ]],
        ['Pages', null, 'admin.pages.*|admin.appearance', 'pages.manage', 'M7 3h7l5 5v13H7z M14 3v5h5', [
            ['All Pages', 'admin.pages.index', 'admin.pages.*', null], ['Home Sections & Menus', 'admin.appearance', 'admin.appearance', null],
        ]],
        ['Users', null, 'admin.users.*|admin.roles.*|admin.permissions', 'users.manage', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21a8 8 0 0 1 16 0', [
            ['Admins & Users', 'admin.users.index', 'admin.users.*', 'users.manage'], ['Roles', 'admin.roles.index', 'admin.roles.*', 'roles.manage'], ['Permissions', 'admin.permissions', 'admin.permissions', 'roles.manage'],
        ]],
        ['Logs', null, 'admin.logs.*', 'logs.view', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2', [
            ['Admin Logs', 'admin.logs.admin', 'admin.logs.admin', null], ['Security Logs', 'admin.logs.security', 'admin.logs.security', null], ['System Health', 'admin.logs.system', 'admin.logs.system', null],
        ]],
        ['Settings', null, 'admin.settings.*|admin.backups.*', 'settings.manage', 'M10.3 4.3a1 1 0 0 1 3.4 0l.3 1.2a7 7 0 0 1 1.7 1l1.2-.4a1 1 0 0 1 1.2 1.5l-.9.9a7 7 0 0 1 0 2l.9.9a1 1 0 0 1-1.2 1.5l-1.2-.4a7 7 0 0 1-1.7 1l-.3 1.2a1 1 0 0 1-3.4 0l-.3-1.2a7 7 0 0 1-1.7-1l-1.2.4a1 1 0 0 1-1.2-1.5l.9-.9a7 7 0 0 1 0-2l-.9-.9a1 1 0 0 1 1.2-1.5l1.2.4a7 7 0 0 1 1.7-1z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', [
            ['General', 'admin.settings.edit', 'admin.settings.edit', null, 'general'], ['Branding', 'admin.settings.edit', null, null, 'branding'], ['Analytics', 'admin.settings.edit', null, null, 'analytics'],
            ['Google Integrations', 'admin.settings.edit', null, null, 'google'], ['Email', 'admin.settings.edit', null, null, 'email'], ['Cookie Consent', 'admin.settings.edit', null, null, 'consent'],
            ['SEO', 'admin.settings.edit', null, null, 'seo'], ['Backup', 'admin.backups.index', 'admin.backups.*', 'backups.manage'], ['Performance', 'admin.settings.edit', null, null, 'performance'], ['Security', 'admin.settings.edit', null, null, 'security'],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ setting('site.name') }} Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>window.VHA = { mediaUrl: '{{ route('admin.media.index') }}', storageUrl: '{{ asset('storage') }}' };</script>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @stack('head')
</head>
<body x-data="{ sidebar: false }">
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-slate-900 p-4 transition lg:static lg:translate-x-0" :class="sidebar && 'translate-x-0'">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-2 text-white">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
            <span class="text-base font-bold">{{ setting('site.name') }}</span>
        </a>
        <nav class="mt-6 flex flex-col gap-0.5">
            @foreach($nav as $item)
                @php [$label, $route, $pattern, $perm, $icon, $children] = $item + [5 => null]; @endphp
                @if($perm && !$u->hasPermission($perm)) @continue @endif
                @php $active = request()->routeIs(...explode('|', $pattern)); $tabActive = fn($t) => request()->routeIs('admin.settings.edit') && (request()->route('tab') ?? 'general') === $t; @endphp
                @if($children)
                    <div x-data="{ open: {{ $active ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="sidebar-link w-full {{ $active ? 'active' : '' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                            <span class="flex-1 text-left">{{ $label }}</span>
                            <svg class="h-3.5 w-3.5 transition" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="sidebar-sub mt-0.5">
                            @foreach($children as $c)
                                @php [$cl, $cr, $cp, $cperm, $ctab] = $c + [4 => null]; @endphp
                                @if($cperm && !$u->hasPermission($cperm)) @continue @endif
                                <a href="{{ $ctab ? route($cr, $ctab) : route($cr) }}" class="{{ ($ctab ? $tabActive($ctab) : ($cp && request()->routeIs(...explode('|', $cp)))) ? 'active' : '' }}">{{ $cl }}</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ route($route) }}" class="sidebar-link {{ $active ? 'active' : '' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>{{ $label }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="mt-8 border-t border-slate-800 pt-4">
            <a href="{{ route('home') }}" target="_blank" class="sidebar-link"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M5 8v11h11"/></svg>View site</a>
        </div>
    </aside>
    <div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button @click="sidebar = true" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open menu"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <h1 class="truncate text-base font-semibold text-slate-800">@yield('title', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-2">
                @hasSection('actions')<div class="hidden items-center gap-2 sm:flex">@yield('actions')</div>@endif
                <div x-data="notifications('{{ route('admin.notifications.latest') }}', {{ $unreadNotifications ?? 0 }})" class="relative">
                    <button @click="toggle()" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Notifications">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 0 1-6 0v-1m6 0H9"/></svg>
                        <span x-show="unread > 0" x-cloak class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white" x-text="unread"></span>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 mt-2 w-80 rounded-xl bg-white p-2 shadow-xl ring-1 ring-slate-200">
                        <template x-if="!items.length"><p class="p-3 text-sm text-slate-500">No notifications.</p></template>
                        <template x-for="n in items" :key="n.id">
                            <form method="post" :action="'{{ route('admin.notifications') }}/' + n.id + '/read'">@csrf
                                <button class="block w-full rounded-lg p-2 text-left hover:bg-slate-50" :class="!n.is_read && 'bg-indigo-50/60'">
                                    <p class="text-sm font-medium text-slate-800" x-text="n.title"></p>
                                    <p class="line-clamp-2 text-xs text-slate-500" x-text="n.message"></p>
                                </button>
                            </form>
                        </template>
                        <a href="{{ route('admin.notifications') }}" class="block p-2 text-center text-xs font-medium text-indigo-600">View all</a>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open=!open" class="flex items-center gap-2 rounded-lg p-1 hover:bg-slate-100">
                        <img src="{{ $u->avatar_url }}" alt="" class="h-8 w-8 rounded-full object-cover">
                        <span class="hidden text-sm font-medium sm:block">{{ $u->name }}</span>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 mt-2 w-48 rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-slate-200">
                        <p class="px-3 py-1.5 text-[11px] font-semibold uppercase text-slate-400">{{ $u->role?->name }}</p>
                        <a href="{{ route('admin.profile') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">My profile</a>
                        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert-error">{{ session('error') }}</div>@endif
            @if(session('warning'))<div class="alert-warning">{{ session('warning') }}</div>@endif
            @if($errors->any())<div class="alert-error"><ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            @hasSection('actions')<div class="mb-4 flex flex-wrap gap-2 sm:hidden">@yield('actions')</div>@endif
            @yield('content')
        </main>
    </div>
</div>

{{-- Media picker modal --}}
<div x-data="mediaPicker" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div @click.outside="open=false" class="flex max-h-[90vh] w-full max-w-5xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-200 p-4">
            <h3 class="font-semibold">Media Library</h3>
            <input type="search" x-model.debounce.400ms="q" @input="page=1; load()" placeholder="Search…" class="input max-w-xs">
            <select x-model="type" @change="page=1; load()" class="select max-w-[140px]"><option value="">All</option><option value="image">Images</option><option value="video">Videos</option></select>
            <label class="btn-primary cursor-pointer">Upload<input type="file" multiple class="hidden" @change="upload($event.target.files)"></label>
            <button @click="open=false" class="ml-auto text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <p x-show="error" x-cloak class="alert-error m-4 mb-0" x-text="error"></p>
        <p x-show="uploading" x-cloak class="px-4 pt-3 text-sm text-slate-500">Uploading…</p>
        <div class="grid flex-1 grid-cols-3 gap-3 overflow-y-auto p-4 sm:grid-cols-4 md:grid-cols-6">
            <template x-for="m in items" :key="m.id">
                <div class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200 hover:ring-indigo-500" @click="choose(m)">
                    <template x-if="m.type === 'image'"><img :src="m.thumb_url || m.url" :alt="m.alt || ''" class="h-full w-full object-cover" loading="lazy"></template>
                    <template x-if="m.type !== 'image'"><div class="flex h-full items-center justify-center p-2 text-center text-xs text-slate-500" x-text="m.original_name"></div></template>
                    <button @click.stop="remove(m)" class="absolute right-1 top-1 hidden rounded bg-white/90 px-1.5 text-xs text-rose-600 group-hover:block">Delete</button>
                </div>
            </template>
            <p x-show="!loading && !items.length" class="col-span-full py-10 text-center text-sm text-slate-500">No media yet. Upload something.</p>
        </div>
        <div class="flex items-center justify-between border-t border-slate-200 p-3 text-sm">
            <button class="btn-secondary btn-sm" :disabled="page<=1" @click="page--; load()">← Prev</button>
            <span x-text="'Page ' + page + ' of ' + last"></span>
            <button class="btn-secondary btn-sm" :disabled="page>=last" @click="page++; load()">Next →</button>
        </div>
    </div>
</div>
@stack('scripts')
</body>
</html>
