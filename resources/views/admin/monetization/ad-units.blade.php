@extends('layouts.admin')
@section('title', 'Ad Units')
@section('content')
<p class="alert-info">Each slot is rendered centrally by the CMS with an "{{ setting('adsense.label', 'Advertisement') }}" label, reserved height (no layout shift) and safe spacing from navigation, play buttons and download links. Enter the <b>data-ad-slot</b> id from AdSense, or paste the full unit code. Code containing auto-click, auto-refresh, popup or hidden-ad logic is rejected. Global on/off: <a href="{{ route('admin.monetization.settings') }}" class="underline">Monetization Settings</a> ({{ setting_bool('adsense.enabled') ? 'enabled' : 'disabled' }}).</p>
<div class="grid gap-5 lg:grid-cols-2">
@foreach($slots as $s)
    <form method="post" action="{{ route('admin.monetization.ad-units.update', $s) }}" class="card space-y-3" x-data="{ on: {{ $s->enabled ? 'true' : 'false' }} }">@csrf @method('PUT')
        <div class="flex items-start justify-between gap-3">
            <div><h2 class="text-base font-semibold">{{ $s->name }}</h2><p class="text-xs text-slate-500">{{ $s->position }} — {{ $s->description }}</p></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" class="checkbox" x-model="on"> <span x-text="on ? 'Enabled' : 'Disabled'"></span></label>
        </div>
        @foreach($s->policyWarnings() as $w)<p class="alert-warning mb-0">{{ $w }}</p>@endforeach
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="label">Display name</label><input name="name" value="{{ $s->name }}" class="input"></div>
            <div><label class="label">AdSense ad slot id</label><input name="ad_slot_id" value="{{ $s->ad_slot_id }}" placeholder="1234567890" class="input"></div>
            <div><label class="label">Format</label><select name="ad_format" class="select">@foreach(['auto' => 'Responsive (auto)', 'rectangle' => 'Rectangle', 'horizontal' => 'Horizontal', 'vertical' => 'Vertical', 'fluid' => 'In-article (fluid)'] as $k => $l)<option value="{{ $k }}" @selected($s->ad_format === $k)>{{ $l }}</option>@endforeach</select></div>
        </div>
        <div><label class="label">Custom ad code (optional — overrides slot id)</label><textarea name="code" rows="3" class="textarea min-h-[70px] font-mono text-xs" placeholder="<ins class=&quot;adsbygoogle&quot; …></ins>">{{ $s->code }}</textarea></div>
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <span class="text-xs font-semibold uppercase text-slate-500">Show on</span>
            <label class="flex items-center gap-1.5"><input type="checkbox" name="desktop" value="1" class="checkbox" @checked($s->desktop)> Desktop</label>
            <label class="flex items-center gap-1.5"><input type="checkbox" name="tablet" value="1" class="checkbox" @checked($s->tablet)> Tablet</label>
            <label class="flex items-center gap-1.5"><input type="checkbox" name="mobile" value="1" class="checkbox" @checked($s->mobile)> Mobile</label>
            @if($s->key === 'in_article')<label class="ml-auto flex items-center gap-1.5">After paragraph <input type="number" name="paragraph_offset" min="1" max="30" value="{{ $s->paragraph_offset }}" class="input w-16 py-1"></label>@endif
        </div>
        <button class="btn-primary">Save {{ $s->name }}</button>
    </form>
@endforeach
</div>
@endsection
