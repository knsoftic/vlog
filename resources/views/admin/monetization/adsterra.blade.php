@extends('layouts.admin')
@section('title', 'Adsterra Ads')
@section('content')
@php
    $adsenseOn = $serving->adsenseActive();
    $status = $serving->adsterraActive() ? ['Serving', 'text-emerald-600'] : (setting_bool('adsterra.enabled') ? ['Paused (AdSense is enabled)', 'text-amber-600'] : ['Off', 'text-slate-500']);
    $scriptStatus = ! setting_bool('adsterra.enabled') ? ['Off (master switch)', 'text-slate-500'] : ($adsenseOn ? ['Blocked: AdSense is enabled', 'text-rose-600'] : ['Allowed', 'text-emerald-600']);
@endphp

<div class="alert-info">
    <b>Kya kahan lagta hai:</b> Banner (468x60, 728x90, 300x250, 320x50, 160x300, 160x600) aur Native Banner neeche <b>har ad position</b> mein.
    Popunder, Social Bar aur Smartlink site-wide hain, unka alag section neeche hai.
    @if($adsenseOn)<br><b class="text-rose-700">AdSense enabled hai:</b> is liye Popunder aur Social Bar site par band rahenge. Google AdSense un sites par ads nahi chalata jo pop-unders chalati hain.@endif
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <form method="post" action="{{ route('admin.monetization.adsterra.settings') }}" class="card space-y-4 lg:col-span-1">@csrf @method('PUT')
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Adsterra settings</h2>
            <span class="text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
        </div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="adsterra_enabled" value="1" class="checkbox mt-0.5" @checked(setting_bool('adsterra.enabled'))>
            <span><b>Enable Adsterra</b><br><span class="help">Master switch for every Adsterra format on this page.</span></span></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="adsterra_pause_when_adsense" value="1" class="checkbox mt-0.5" @checked(setting_bool('adsterra.pause_when_adsense', true))>
            <span><b>Pause banners while AdSense is enabled</b><br><span class="help">Recommended during AdSense review. Popunder and Social Bar are always off while AdSense is enabled, whatever this is set to.</span></span></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="adsterra_require_consent" value="1" class="checkbox mt-0.5" @checked(setting_bool('adsterra.require_consent', true))>
            <span><b>Require advertising consent</b><br><span class="help">In consent regions (EEA/UK/CH) Adsterra loads only after the visitor accepts advertising cookies. US visitors are not affected.</span></span></label>
        <p class="help">Admins and bots never see ads (Monetization Settings), so open the site in a private window to see them.</p>
        <button class="btn-primary w-full">Save settings</button>
    </form>

    <div class="card space-y-3 text-sm lg:col-span-2">
        <h2 class="text-base font-semibold">Code kahan se milega</h2>
        <ol class="list-decimal space-y-1.5 pl-5">
            <li>Adsterra dashboard → <b>Websites</b> mein pinecasttv.com add karein.</li>
            <li><b>Get code</b> par har format ka code copy karein.</li>
            <li><b>Banner / Native Banner</b>: neeche matching position mein paste karein. 728x90 header/footer, 300x250 in-article ya below content, 160x600 / 160x300 sidebar.</li>
            <li><b>Mobile code</b>: phones ke liye 320x50 ya 300x250 alag paste karein. 336px se chaude banner mobile par khud skip ho jate hain.</li>
            <li><b>Popunder / Social Bar / Smartlink</b>: neeche "Site-wide formats" section mein.</li>
            <li>Adsterra ne ads.txt line di ho to <a href="{{ route('admin.monetization.ads-txt') }}" class="underline">Ads.txt</a> mein add karein.</li>
        </ol>
        <p class="help">Position aur Desktop / Tablet / Mobile settings <a href="{{ route('admin.monetization.ad-units') }}" class="underline">Ad Units</a> se aati hain. Jis slot par AdSense active ho wahan AdSense dikhega, warna Adsterra.</p>
    </div>
</div>

{{-- Site-wide formats --}}
<form method="post" action="{{ route('admin.monetization.adsterra.formats') }}" class="card mt-5 space-y-4">@csrf @method('PUT')
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold">Site-wide formats: Popunder, Social Bar, Smartlink</h2>
            <p class="text-xs text-slate-500">Popunder & Social Bar: <span class="font-semibold {{ $scriptStatus[1] }}">{{ $scriptStatus[0] }}</span></p>
        </div>
    </div>
    <p class="alert-warning mb-0">Popunder aur Social Bar sabse zyada paise dete hain, lekin US visitors ko annoying lagte hain aur AdSense ke saath nahi chal sakte. AdSense apply karne se pehle inhe band karein.</p>
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="popunder_enabled" value="1" class="checkbox" @checked(setting_bool('adsterra.popunder_enabled'))> Popunder</label>
            <textarea name="popunder_code" rows="4" class="textarea font-mono text-xs" placeholder="<script src=&quot;//…/xx/yy/zz/….js&quot;></script>">{{ old('popunder_code', setting('adsterra.popunder_code')) }}</textarea>
            <p class="help">Opens a new tab behind the page on a visitor's click. Loaded before &lt;/body&gt;.</p>
        </div>
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="socialbar_enabled" value="1" class="checkbox" @checked(setting_bool('adsterra.socialbar_enabled'))> Social Bar</label>
            <textarea name="socialbar_code" rows="4" class="textarea font-mono text-xs" placeholder="<script src=&quot;//…/xx/yy/zz/….js&quot;></script>">{{ old('socialbar_code', setting('adsterra.socialbar_code')) }}</textarea>
            <p class="help">Notification-style in-page bubble. Loaded before &lt;/body&gt;.</p>
        </div>
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="smartlink_enabled" value="1" class="checkbox" @checked(setting_bool('adsterra.smartlink_enabled'))> Smartlink</label>
            <input name="smartlink_url" value="{{ old('smartlink_url', setting('adsterra.smartlink_url')) }}" class="input font-mono text-xs" placeholder="https://www.….com/…?key=…">
            <label class="label mt-2">Link text</label>
            <input name="smartlink_label" value="{{ old('smartlink_label', setting('adsterra.smartlink_label', 'Sponsored offer')) }}" class="input" maxlength="60">
            <p class="help">Shown under each article as a link labelled "Sponsored" (rel="sponsored"). It is never disguised as a Download, Play or Next button.</p>
        </div>
    </div>
    <button class="btn-primary">Save site-wide formats</button>
</form>

<h2 class="mt-8 text-base font-semibold">Banner & Native Banner by position</h2>
<div class="mt-3 grid gap-5 lg:grid-cols-2">
@foreach($slots as $s)
    @php $problems = \App\Models\AdSlot::adsterraCodeProblems($s->adsterra_code); $size = $s->adsterraSize($s->adsterra_code); $mSize = $s->adsterraSize($s->adsterra_code_mobile); @endphp
    <form method="post" action="{{ route('admin.monetization.adsterra.slot', $s) }}" class="card space-y-3" x-data="{ on: {{ $s->adsterra_enabled ? 'true' : 'false' }} }">@csrf @method('PUT')
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">{{ $s->name }}</h2>
                <p class="text-xs text-slate-500">{{ $s->position }} · shows on:
                    {{ collect(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'])->filter(fn ($l, $k) => $s->{$k})->implode(', ') ?: 'no devices' }}</p>
            </div>
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold" :class="on ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-rose-300 bg-rose-50 text-rose-700'">
                <input type="checkbox" name="adsterra_enabled" value="1" class="checkbox" x-model="on" @checked($s->adsterra_enabled)>
                <span x-text="on ? 'ON: showing on site' : 'OFF: tick to show'">{{ $s->adsterra_enabled ? 'ON: showing on site' : 'OFF: tick to show' }}</span>
            </label>
        </div>
        @foreach($problems as $p)<p class="alert-warning mb-0">{{ $p }}</p>@endforeach
        @if($s->enabled && $adsenseOn)<p class="alert-info mb-0">AdSense is active on this slot, so AdSense will be shown here instead of Adsterra.</p>@endif
        @if($size && $size[0] > \App\Models\AdSlot::MOBILE_MAX_WIDTH && $s->mobile && ! $s->adsterra_code_mobile)<p class="alert-info mb-0">{{ $size[0] }}×{{ $size[1] }} is too wide for phones and is skipped on mobile. Add a 320x50 or 300x250 mobile code.</p>@endif
        <div>
            <label class="label">Banner / Native code @if($size)<span class="font-normal text-slate-500">— {{ $size[0] }}×{{ $size[1] }}</span>@endif</label>
            <textarea name="adsterra_code" rows="5" class="textarea font-mono text-xs" placeholder="<script>&#10;  atOptions = { 'key' : '…', 'format' : 'iframe', 'height' : 60, 'width' : 468, 'params' : {} };&#10;</script>&#10;<script src=&quot;https://…/invoke.js&quot;></script>">{{ old('adsterra_code', $s->adsterra_code) }}</textarea>
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
