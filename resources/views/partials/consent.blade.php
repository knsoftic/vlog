<div id="consent-banner" class="consent-banner" hidden role="dialog" aria-live="polite" aria-label="Cookie consent">
    <div class="consent-card">
        <h2 class="text-base font-bold text-slate-900">{{ setting('consent.banner_title') }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ setting('consent.banner_text') }} Read our <a href="{{ route('page.show', 'privacy-policy') }}" class="underline">Privacy Policy</a> and <a href="{{ route('page.show', 'cookie-policy') }}" class="underline">Cookie Policy</a>.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn-primary" data-consent-accept>Accept all</button>
            <button type="button" class="btn-ghost" data-consent-reject>Reject non-essential</button>
            <button type="button" class="btn-ghost" data-consent-open>Manage preferences</button>
        </div>
    </div>
</div>

<div id="consent-prefs" class="consent-banner" hidden role="dialog" aria-label="Cookie preferences">
    <div class="consent-card">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Cookie preferences</h2>
                <p class="mt-1 text-sm text-slate-600">Choose which cookies you allow. Necessary cookies are always on.</p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700" data-consent-close aria-label="Close">✕</button>
        </div>
        <div class="mt-4 divide-y divide-slate-100">
            <div class="flex items-center justify-between py-3">
                <div><p class="text-sm font-semibold">Necessary</p><p class="text-xs text-slate-500">Required for the site to work (session, security, your preferences).</p></div>
                <span class="toggle" aria-checked="true" aria-disabled="true" style="opacity:.6"><span></span></span>
            </div>
            <div class="flex items-center justify-between py-3">
                <div><p class="text-sm font-semibold">Analytics</p><p class="text-xs text-slate-500">Helps us understand which content is popular (first-party analytics{{ setting('analytics.ga4_id') ? ' and Google Analytics' : '' }}).</p></div>
                <button type="button" class="toggle" role="switch" aria-checked="false" data-key="analytics"><span></span></button>
            </div>
            <div class="flex items-center justify-between py-3">
                <div><p class="text-sm font-semibold">Advertising</p><p class="text-xs text-slate-500">Allows Google AdSense and its partners to show personalised ads and measure them.</p></div>
                <button type="button" class="toggle" role="switch" aria-checked="false" data-key="advertising"><span></span></button>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn-primary" data-consent-save>Save preferences</button>
            <button type="button" class="btn-ghost" data-consent-accept>Accept all</button>
        </div>
    </div>
</div>
