@extends('layouts.admin')
@section('title', 'Monetag')
@section('content')
@php
    $adsenseOn = $serving->adsenseActive();
    $status = ! setting_bool('monetag.enabled') ? ['Off', 'text-slate-500'] : ($adsenseOn ? ['Blocked: AdSense is enabled', 'text-rose-600'] : ['Serving', 'text-emerald-600']);
    $verify = setting('monetag.verify_meta');
@endphp

<div class="alert-info">
    <b>Monetag</b> ke formats site-wide hain: ek dafa tag paste karein, har public page par chalega.
    Admins aur bots ko ads nahi dikhte, is liye check karne ke liye site private window mein kholein.
    @if($adsenseOn)<br><b class="text-rose-700">AdSense enabled hai:</b> is liye Monetag ke saare formats band rahenge. Popunder, push prompt aur interstitial AdSense ke saath allowed nahi.@endif
</div>

<form method="post" action="{{ route('admin.monetization.monetag.update') }}" class="space-y-5">@csrf @method('PUT')
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Monetag settings</h2>
                <span class="text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
            </div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="enabled" value="1" class="checkbox mt-0.5" @checked(setting_bool('monetag.enabled'))>
                <span><b>Enable Monetag</b><br><span class="help">Master switch for every format on this page. Verification tag below works even when this is off.</span></span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="require_consent" value="1" class="checkbox mt-0.5" @checked(setting_bool('monetag.require_consent', true))>
                <span><b>Require advertising consent</b><br><span class="help">In consent regions (EEA/UK/CH) Monetag loads only after the visitor accepts advertising cookies.</span></span></label>
        </div>

        <div class="card space-y-3 lg:col-span-2">
            <h2 class="text-base font-semibold">1. Site verification</h2>
            <p class="text-sm">Monetag → <b>Sites → Add site</b> → pinecasttv.com → verification method <b>"Meta tag"</b> chunein, tag copy kar ke yahan paste karein, Save karein, phir Monetag mein <b>Verify</b> dabayein.</p>
            <input name="verify_meta" value="{{ old('verify_meta', $verify) }}" class="input font-mono text-xs" placeholder='<meta name="monetag" content="…">'>
            @error('verify_meta')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @if($verify)<p class="help">Live on every page as <code>&lt;meta name="monetag" content="{{ $verify }}"&gt;</code></p>@endif
        </div>
    </div>

    <div class="card space-y-4">
        <div>
            <h2 class="text-base font-semibold">2. Ad formats</h2>
            <p class="text-xs text-slate-500">Monetag → Sites → <b>Add zone</b> → format chunein → <b>Get tag</b>. Har format ka tag apne box mein paste karein aur tick karein.</p>
        </div>
        <div class="grid gap-5 lg:grid-cols-2">
            @foreach($formats as $key => [$label, $help])
                <div class="space-y-2 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="{{ $key }}_enabled" value="1" class="checkbox" @checked(setting_bool("monetag.{$key}_enabled"))> {{ $label }}</label>
                    <textarea name="{{ $key }}_code" rows="4" class="textarea font-mono text-xs" placeholder="<script>…</script>">{{ old("{$key}_code", setting("monetag.{$key}_code")) }}</textarea>
                    @error("{$key}_code")<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="help">{{ $help }}</p>
                </div>
            @endforeach
            <div class="space-y-2 rounded-xl border border-slate-200 p-4">
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="directlink_enabled" value="1" class="checkbox" @checked(setting_bool('monetag.directlink_enabled'))> Direct Link</label>
                <input name="directlink_url" value="{{ old('directlink_url', setting('monetag.directlink_url')) }}" class="input font-mono text-xs" placeholder="https://…">
                @error('directlink_url')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <label class="label">Link text</label>
                <input name="directlink_label" value="{{ old('directlink_label', setting('monetag.directlink_label', 'Sponsored offer')) }}" class="input" maxlength="60">
                @error('directlink_label')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="help">Shown under each article as a link labelled "Sponsored" (rel="sponsored"). Never disguised as a Download, Play or Next button.</p>
            </div>
        </div>
    </div>

    <div class="card space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">3. sw.js (sirf Push Notifications ke liye)</h2>
            @if(setting('monetag.sw_js'))
                <a href="{{ url('/sw.js') }}" target="_blank" rel="noopener" class="text-xs font-semibold {{ $swFileOk ? 'text-emerald-600' : 'text-amber-600' }}">{{ $swFileOk ? 'Live at /sw.js ↗' : 'Saved, check /sw.js ↗' }}</a>
            @endif
        </div>
        <p class="text-sm">Push zone ke "Get tag" page se <b>sw.js</b> download karein, file Notepad mein kholein aur poora content yahan paste karein. Site isey <code>https://pinecasttv.com/sw.js</code> par serve karegi.</p>
        <textarea name="sw_js" rows="5" class="textarea font-mono text-xs" placeholder="self.options = { … }&#10;self.lary = &quot;&quot;&#10;importScripts('https://…/sw.js')">{{ old('sw_js', setting('monetag.sw_js')) }}</textarea>
        @error('sw_js')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        <p class="help">Monetag ads.txt line de to <a href="{{ route('admin.monetization.ads-txt') }}" class="underline">Ads.txt</a> mein add karein.</p>
    </div>

    <button class="btn-primary">Save Monetag</button>
</form>
@endsection
