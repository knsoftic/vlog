@extends('layouts.admin')
@section('title', 'Real-Time')
@section('content')
<div x-data="realtime('{{ route('admin.realtime.json') }}', @js($realtime))">
    <div class="grid gap-4 md:grid-cols-3">
        <div class="stat"><p class="stat-label">Visitors online</p><p class="stat-value text-5xl" x-text="data.online"></p><p class="stat-note">active in the last 5 minutes</p></div>
        <div class="stat"><p class="stat-label">Page views · last minute</p><p class="stat-value text-5xl" x-text="data.last_minute"></p><p class="stat-note">human traffic only</p></div>
        <div class="stat"><p class="stat-label">Refresh</p><p class="mt-2 flex items-center gap-2 text-sm text-emerald-600"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> auto every 10 s</p><button @click="refresh()" class="btn-secondary btn-sm mt-2">Refresh now</button></div>
    </div>
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="card"><h2 class="card-title">Active pages</h2><table class="table mt-3"><thead><tr><th>Page</th><th class="text-right">Visitors</th></tr></thead><tbody><template x-for="p in data.pages" :key="p.path"><tr><td><p class="font-medium" x-text="p.title || p.path"></p><p class="text-xs text-slate-400" x-text="p.path"></p></td><td class="text-right font-semibold" x-text="p.count"></td></tr></template><tr x-show="!data.pages.length"><td colspan="2" class="text-center text-slate-400">Nobody online right now.</td></tr></tbody></table></div>
        <div class="card"><h2 class="card-title">Vlogs being viewed</h2><table class="table mt-3"><thead><tr><th>Vlog / article</th><th class="text-right">Viewers</th></tr></thead><tbody><template x-for="v in data.vlogs" :key="v.post.id"><tr><td class="font-medium" x-text="v.post.title"></td><td class="text-right font-semibold" x-text="v.count"></td></tr></template><tr x-show="!data.vlogs.length"><td colspan="2" class="text-center text-slate-400">No content pages active.</td></tr></tbody></table></div>
    </div>
    <div class="mt-5 grid gap-5 md:grid-cols-3">
        <template x-for="[title, key] in [['Devices','devices'],['Countries (approx.)','countries'],['Traffic sources','sources']]">
            <div class="card"><h2 class="card-title" x-text="title"></h2><ul class="mt-3 space-y-1 text-sm"><template x-for="(n, k) in data[key]" :key="k"><li class="flex justify-between"><span class="capitalize" x-text="k"></span><b x-text="n"></b></li></template><li x-show="!Object.keys(data[key]).length" class="text-slate-400">—</li></ul></div>
        </template>
    </div>
</div>
@endsection
