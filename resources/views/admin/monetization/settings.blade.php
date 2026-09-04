@extends('layouts.admin')
@section('title', 'Monetization Settings')
@section('content')
<form method="post" action="{{ route('admin.monetization.settings.update') }}" class="grid gap-5 lg:grid-cols-3">@csrf @method('PUT')
    <div class="card space-y-4 lg:col-span-2">
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="adsense_enabled" value="1" class="checkbox" @checked($values['adsense.enabled'])> Enable Google AdSense on the site</label>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Publisher ID</label><input name="adsense_publisher_id" value="{{ $values['adsense.publisher_id'] }}" placeholder="pub-0000000000000000" class="input"><p class="help">Used for ads.txt and the AdSense loader.</p></div>
            <div><label class="label">Client ID (ca-pub-…)</label><input name="adsense_client_id" value="{{ $values['adsense.client_id'] }}" placeholder="ca-pub-0000000000000000" class="input"><p class="help">Defaults to the publisher id.</p></div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_auto_ads" value="1" class="checkbox" @checked($values['adsense.auto_ads'])> Auto Ads <span class="text-xs text-slate-400">(Google places ads automatically)</span></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_manual_ads" value="1" class="checkbox" @checked($values['adsense.manual_ads'])> Manual ad units <span class="text-xs text-slate-400">(configured under Ad Units)</span></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_lazy_load" value="1" class="checkbox" @checked($values['adsense.lazy_load'])> Lazy-load ad units near viewport <span class="text-xs text-slate-400">(better Core Web Vitals)</span></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_show_label" value="1" class="checkbox" @checked($values['adsense.show_label'])> Show "Advertisement" label above ads</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_hide_for_admins" value="1" class="checkbox" @checked($values['adsense.hide_for_admins'])> Hide ads for logged-in admins <span class="text-xs text-slate-400">(prevents invalid self-traffic)</span></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_hide_for_bots" value="1" class="checkbox" @checked($values['adsense.hide_for_bots'])> Do not serve ads to bots/crawlers</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="adsense_auto_sync" value="1" class="checkbox" @checked($values['adsense.auto_sync'])> Auto-sync AdSense reports every 6 hours</label>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label">Ad label text</label><input name="adsense_label" value="{{ $values['adsense.label'] }}" class="input"><p class="help">Must stay neutral — no "click here / support us".</p></div>
            <div><label class="label">Minimum words for ads</label><input type="number" name="adsense_min_words_for_ads" value="{{ $values['adsense.min_words_for_ads'] }}" min="0" class="input"><p class="help">Thin pages show no ads.</p></div>
            <div><label class="label">Reporting currency</label><input name="adsense_currency" value="{{ $values['adsense.currency'] }}" maxlength="3" class="input"></div>
        </div>
        <button class="btn-primary">Save settings</button>
    </div>
    <div class="space-y-5">
        <div class="card">
            <h2 class="card-title">AdSense Management API</h2>
            @if($connected)<p class="mt-2 text-sm"><span class="badge-green">Connected</span> {{ $token->account_label ?: $token->account_id }}</p><p class="help">Last sync {{ $token->last_synced_at?->diffForHumans() ?? 'never' }}.</p>
            @else<p class="mt-2 text-sm text-slate-600">Connect to show real earnings and performance data. {{ $oauthConfigured ? '' : 'Add OAuth credentials first under Settings → Google Integrations.' }}</p><a href="{{ $oauthConfigured ? route('admin.google.connect', 'adsense') : route('admin.settings.edit', 'google') }}" class="btn-secondary mt-3">{{ $oauthConfigured ? 'Connect with Google' : 'Configure OAuth' }}</a>@endif
        </div>
        <div class="card text-sm text-slate-600">
            <h2 class="card-title">Policy guardrails built in</h2>
            <ul class="mt-2 space-y-1"><li>• No fake traffic, artificial views, auto-clicks or self-click tooling exists in this system.</li><li>• No wording that encourages clicks; label validated on save.</li><li>• No pop-unders, forced redirects or auto-refresh ads.</li><li>• Consent Mode v2 + region-aware consent before advertising storage.</li><li>• Earnings shown only from the authorised API; otherwise "Data unavailable".</li></ul>
            <p class="help mt-2">Google policies change over time — everything here is configurable. Approval is never guaranteed.</p>
        </div>
    </div>
</form>
@endsection
