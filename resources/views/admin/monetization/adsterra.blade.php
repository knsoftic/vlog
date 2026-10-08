@extends('layouts.admin')
@section('title', 'Adsterra Banners')
@section('content')
@php
    $status = $serving->adsterraActive() ? ['Serving', 'text-emerald-600'] : (setting_bool('adsterra.enabled') ? ['Paused (AdSense is enabled)', 'text-amber-600'] : ['Off', 'text-slate-500']);
@endphp

<div class="alert-warning">
    <b>Sirf Banner aur Native Banner.</b> Popunder, Social Bar aur Smartlink codes yahan save nahi hote. Google AdSense un sites par ads nahi chalne deta jo pop-unders "contain or trigger" karti hain.
    AdSense apply karne se pehle Adsterra band kar dein, ya "Pause while AdSense is enabled" on rakhein (default).
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <form method="post" action="{{ route('admin.monetization.adsterra.settings') }}" class="card space-y-4 lg:col-span-1">@csrf @method('PUT')
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Adsterra settings</h2>
            <span class="text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
        </div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="adsterra_enabled" value="1" class="checkbox mt-0.5" @checked(setting_bool('adsterra.enabled'))>
            <span><b>Enable Adsterra banners</b><br><span class="help">Master switch for every Adsterra slot below.</span></span></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="adsterra_pause_when_adsense" value="1" class="checkbox mt-0.5" @checked(setting_bool('adsterra.pause_when_adsense', true))>
            <span><b>Pause while AdSense is enabled</b><br><span class="help">Recommended. Keeps the site free of other networks during AdSense review. Turn off only if you deliberately want both networks.</span></span></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="adsterra_require_consent" value="1" class="checkbox mt-0.5" @checked(setting_bool('adsterra.require_consent', true))>
            <span><b>Require advertising consent</b><br><span class="help">In consent regions (EEA/UK/CH) Adsterra loads only after the visitor accepts advertising cookies. Adsterra has no Consent Mode.</span></span></label>
        <p class="help">Admins and bots never see ads (Monetization Settings). Thin pages below the minimum word count get no ads.</p>
        <button class="btn-primary w-full">Save settings</button>
    </form>

    <div class="card space-y-3 text-sm lg:col-span-2">
        <h2 class="text-base font-semibold">Code kahan se milega</h2>
        <ol class="list-decimal space-y-1.5 pl-5">
            <li>adsterra.com → Publisher dashboard → <b>Websites</b> mein pinecasttv.com add karein.</li>
            <li><b>Get code</b> → format <b>Banner</b> (728x90, 468x60, 300x250, 320x50, 160x600) ya <b>Native Banner</b> chunein.</li>
            <li>Poora code (dono <code>&lt;script&gt;</code> tags) copy kar ke neeche slot mein paste karein.</li>
            <li>Desktop ke liye 728x90 / 300x250, mobile ke liye <b>320x50</b> ya <b>300x250</b> ka alag code "Mobile code" mein dalein. 336px se chaude banner mobile par khud skip ho jate hain.</li>
            <li>Adsterra ne ads.txt line di ho to <a href="{{ route('admin.monetization.ads-txt') }}" class="underline">Ads.txt</a> mein add karein.</li>
        </ol>
        <p class="help">Slot ki position aur Desktop / Tablet / Mobile settings <a href="{{ route('admin.monetization.ad-units') }}" class="underline">Ad Units</a> se aati hain. Agar kisi slot par AdSense active hai to wahan AdSense dikhega, warna Adsterra.</p>
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
@foreach($slots as $s)
    @php $problems = \App\Models\AdSlot::adsterraCodeProblems($s->adsterra_code); $size = $s->adsterraSize($s->adsterra_code); $mSize = $s->adsterraSize($s->adsterra_code_mobile); @endphp
    <form method="post" action="{{ route('admin.monetization.adsterra.slot', $s) }}" class="card space-y-3" x-data="{ on: {{ $s->adsterra_enabled ? 'true' : 'false' }} }">@csrf @method('PUT')
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">{{ $s->name }}</h2>
                <p class="text-xs text-slate-500">{{ $s->position }} · shows on:
                    {{ collect(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'])->filter(fn ($l, $k) => $s->{$k})->implode(', ') ?: 'no devices' }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsterra_enabled" value="1" class="checkbox" x-model="on"> <span x-text="on ? 'Enabled' : 'Disabled'"></span></label>
        </div>
        @foreach($problems as $p)<p class="alert-warning mb-0">{{ $p }}</p>@endforeach
        @if($s->enabled && setting_bool('adsense.enabled'))<p class="alert-info mb-0">AdSense is active on this slot, so AdSense will be shown here instead of Adsterra.</p>@endif
        <div>
            <label class="label">Banner / Native code @if($size)<span class="font-normal text-slate-500">— {{ $size[0] }}×{{ $size[1] }}</span>@endif</label>
            <textarea name="adsterra_code" rows="5" class="textarea font-mono text-xs" placeholder="<script type=&quot;text/javascript&quot;>&#10;  atOptions = { 'key' : '…', 'format' : 'iframe', 'height' : 90, 'width' : 728, 'params' : {} };&#10;</script>&#10;<script type=&quot;text/javascript&quot; src=&quot;//…/invoke.js&quot;></script>">{{ old('adsterra_code', $s->adsterra_code) }}</textarea>
        </div>
        <div>
            <label class="label">Mobile code (optional) @if($mSize)<span class="font-normal text-slate-500">— {{ $mSize[0] }}×{{ $mSize[1] }}</span>@endif</label>
            <textarea name="adsterra_code_mobile" rows="3" class="textarea font-mono text-xs" placeholder="320x50 or 300x250 banner code for phones">{{ old('adsterra_code_mobile', $s->adsterra_code_mobile) }}</textarea>
        </div>
        <button class="btn-primary">Save {{ $s->name }}</button>
    </form>
@endforeach
</div>
@endsection
