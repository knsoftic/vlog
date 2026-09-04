@extends('layouts.admin')
@section('title', 'Ad Placement')
@section('content')
<div class="grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2" x-data="sortable((ids) => fetch('{{ route('admin.monetization.placement.update') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ order: ids }) }))">
        <p class="alert-info">Drag to reorder how slots are prioritised. Placements are restricted to the recommended safe zones below; the layout keeps ads visually separate from content, labelled, and away from navigation and the video play button.</p>
        <div x-ref="list" class="space-y-2">
            @foreach($slots as $s)
                <div draggable="true" data-id="{{ $s->id }}" @dragstart="start($event, {{ $loop->index }})" @dragover="over($event)" @drop="drop($event, {{ $loop->index }})" class="card flex cursor-grab items-center gap-4 py-3 active:cursor-grabbing">
                    <span class="text-slate-300">⋮⋮</span>
                    <div class="flex-1"><p class="font-semibold">{{ $s->name }} <span class="{{ $s->enabled ? 'badge-green' : 'badge-gray' }}">{{ $s->enabled ? 'on' : 'off' }}</span> @unless($s->is_safe_zone)<span class="badge-yellow">outside safe zone</span>@endunless</p><p class="text-xs text-slate-500">{{ $s->position }}</p></div>
                    <div class="flex gap-1 text-[11px]">@foreach(['desktop', 'tablet', 'mobile'] as $d)<span class="{{ $s->{$d} ? 'badge-blue' : 'badge-gray' }}">{{ $d }}</span>@endforeach</div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card">
        <h2 class="card-title">Placement safety rules (built in)</h2>
        <ul class="mt-3 space-y-2 text-sm text-slate-600">
            <li>✔ Every ad has a neutral "{{ setting('adsense.label', 'Advertisement') }}" label and reserved space (no CLS).</li>
            <li>✔ Header ad sits below the navigation bar with padding; footer ad sits above footer links.</li>
            <li>✔ In-article ad is inserted after paragraph {{ $slots->firstWhere('key', 'in_article')?->paragraph_offset ?? 3 }}, never next to the video player or share buttons.</li>
            <li>✔ Sidebar ad is off on mobile by default to keep content readable.</li>
            <li>✔ No ads on thin pages (under {{ setting('adsense.min_words_for_ads', 150) }} words), search pages, previews, 404s or admin pages.</li>
            <li>✔ Ads hidden for logged-in admins and bots.</li>
            <li>✔ No pop-unders, redirects, auto-refresh, floating/sticky overlays or download-style ad styling.</li>
            <li>✔ Ad code with click/refresh/hide logic is rejected on save.</li>
        </ul>
        <a href="{{ route('admin.monetization.checklist') }}" class="btn-secondary mt-4">Run policy checklist</a>
    </div>
</div>
@endsection
