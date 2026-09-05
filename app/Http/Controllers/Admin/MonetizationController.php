<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use App\Services\AdSenseService;
use App\Services\AdsTxtService;
use App\Services\AuditLogger;
use App\Services\GoogleOAuthService;
use App\Services\SettingsService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class MonetizationController extends Controller
{
    public function __construct(
        protected AdSenseService $adsense,
        protected AdsTxtService $adsTxt,
        protected SettingsService $settings,
        protected AuditLogger $audit,
        protected GoogleOAuthService $oauth,
    ) {
    }

    public function dashboard(Request $request)
    {
        $range = DateRange::fromRequest($request, '30d');
        $connected = $this->adsense->isConnected();
        $token = $this->adsense->token();
        $granularity = $request->query('granularity');
        $granularity = in_array($granularity, ['day', 'week', 'month', 'year'], true) ? $granularity : ($range->days() > 92 ? 'week' : 'day');
        $summary = $connected ? $this->adsense->earningsSummary() : null;
        $totals = $connected ? $this->adsense->totals($range->from, $range->to) : null;
        $prev = $connected ? $this->adsense->totals($range->prevFrom, $range->prevTo) : null;
        $series = $connected ? $this->adsense->series($range->from, $range->to, $granularity) : null;
        $platforms = $connected ? $this->adsense->breakdown('platform', $range->from, $range->to) : [];
        $countries = $connected ? $this->adsense->breakdown('country', $range->from, $range->to) : [];
        $adUnits = $connected ? $this->adsense->breakdown('ad_unit', $range->from, $range->to) : [];
        $daily = $connected ? \App\Models\AdsenseReport::where('dimension_type', 'date')->whereBetween('report_date', [$range->from->toDateString(), $range->to->toDateString()])->orderByDesc('report_date')->limit(60)->get() : collect();
        return view('admin.monetization.dashboard', compact('range', 'connected', 'token', 'granularity', 'summary', 'totals', 'prev', 'series', 'platforms', 'countries', 'adUnits', 'daily'));
    }

    public function adUnits()
    {
        $slots = AdSlot::orderBy('sort_order')->get();
        return view('admin.monetization.ad-units', compact('slots'));
    }

    public function updateAdUnit(Request $request, AdSlot $slot)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'ad_slot_id' => 'nullable|string|max:50|regex:/^[0-9]*$/',
            'ad_format' => ['required', Rule::in(['auto', 'rectangle', 'horizontal', 'vertical', 'fluid'])],
            'code' => 'nullable|string|max:5000',
            'enabled' => 'nullable|boolean', 'desktop' => 'nullable|boolean', 'tablet' => 'nullable|boolean', 'mobile' => 'nullable|boolean',
            'paragraph_offset' => 'nullable|integer|min:1|max:30',
        ]);
        foreach (['enabled', 'desktop', 'tablet', 'mobile'] as $b) {
            $data[$b] = $request->boolean($b);
        }
        $data['paragraph_offset'] = (int) ($data['paragraph_offset'] ?? ($slot->paragraph_offset ?: 3));
        $original = $slot->getOriginal();
        $slot->fill($data);
        $warnings = $slot->policyWarnings();
        // Hard restriction: never save code that auto-clicks / auto-refreshes / hides ads
        if (preg_match('/\.click\(\)|setInterval|location\.reload|window\.open|display\s*:\s*none/i', (string) $slot->code)) {
            return back()->withErrors(['code' => 'This ad code contains auto-click, auto-refresh, popup or hidden-ad logic which violates AdSense policies and cannot be saved.'])->withInput();
        }
        $slot->save();
        Cache::forget('site.nav');
        $this->audit->logModelChange('adsense_changed', 'monetization', $slot, $original, 'Ad unit updated: '.$slot->name);
        $msg = 'Ad unit saved.';
        if ($warnings) {
            return back()->with('success', $msg)->with('warning', implode(' ', $warnings));
        }
        return back()->with('success', $msg);
    }

    public function placement()
    {
        $slots = AdSlot::orderBy('sort_order')->get();
        return view('admin.monetization.placement', compact('slots'));
    }

    public function updatePlacement(Request $request)
    {
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer', 'safe' => 'nullable|array']);
        foreach ($data['order'] as $i => $id) {
            AdSlot::whereKey($id)->update(['sort_order' => $i]);
        }
        Cache::forget('site.nav');
        $this->audit->log('adsense_changed', 'monetization', null, 'Ad placement order changed');
        return response()->json(['ok' => true]);
    }

    public function adsTxt()
    {
        $content = (string) setting('adsense.ads_txt', '');
        $publisher = setting('adsense.publisher_id');
        $validation = $this->adsTxt->validate($content !== '' ? $content : $this->adsTxt->content(), $publisher);
        return view('admin.monetization.ads-txt', ['content' => $content !== '' ? $content : rtrim($this->adsTxt->content()), 'validation' => $validation, 'updatedAt' => setting('adsense.ads_txt_updated_at'), 'publisher' => $publisher, 'suggested' => $publisher ? $this->adsTxt->googleLine($publisher) : null]);
    }

    public function updateAdsTxt(Request $request)
    {
        $data = $request->validate(['ads_txt' => 'nullable|string|max:20000']);
        $content = str_replace("\r\n", "\n", (string) $data['ads_txt']);
        $validation = $this->adsTxt->validate($content, setting('adsense.publisher_id'));
        if ($validation['errors']) {
            return back()->withErrors(['ads_txt' => implode(' ', $validation['errors'])])->withInput();
        }
        $before = setting('adsense.ads_txt');
        $this->settings->set('adsense.ads_txt', $content, 'adsense');
        $this->settings->set('adsense.ads_txt_updated_at', now()->toDateTimeString(), 'adsense');
        $this->audit->log('ads_txt_changed', 'monetization', null, 'ads.txt updated', ['ads_txt' => $before], ['ads_txt' => $content]);
        return back()->with('success', 'ads.txt saved and live at /ads.txt.')->with('warning', $validation['warnings'] ? implode(' ', $validation['warnings']) : null);
    }

    public function settings()
    {
        $keys = ['adsense.enabled', 'adsense.publisher_id', 'adsense.client_id', 'adsense.auto_ads', 'adsense.manual_ads', 'adsense.lazy_load', 'adsense.label', 'adsense.show_label', 'adsense.hide_for_admins', 'adsense.hide_for_bots', 'adsense.min_words_for_ads', 'adsense.auto_sync', 'adsense.currency'];
        $values = [];
        foreach ($keys as $k) {
            $values[$k] = setting($k);
        }
        return view('admin.monetization.settings', ['values' => $values, 'connected' => $this->adsense->isConnected(), 'token' => $this->adsense->token(), 'oauthConfigured' => $this->oauth->isConfigured()]);
    }

    public function updateSettings(Request $request)
    {
        // Form fields use underscores (PHP converts dots in POST names to underscores).
        $data = $request->validate([
            'adsense_publisher_id' => 'nullable|string|max:40|regex:/^(ca-)?pub-\d{10,20}$/i',
            'adsense_client_id' => 'nullable|string|max:40|regex:/^(ca-)?pub-\d{10,20}$/i',
            'adsense_label' => 'nullable|string|max:40',
            'adsense_min_words_for_ads' => 'nullable|integer|min:0|max:2000',
            'adsense_currency' => 'nullable|string|size:3',
        ]);
        $label = (string) ($data['adsense_label'] ?? 'Advertisement');
        if (preg_match('/click|support us|sponsor us|help us/i', $label)) {
            return back()->withErrors(['adsense_label' => 'The ad label must not encourage clicks. Use a neutral label such as "Advertisement" or "Sponsored".'])->withInput();
        }
        $publisher = $data['adsense_publisher_id'] ?? '';
        $pairs = [
            'adsense.enabled' => $request->boolean('adsense_enabled'),
            'adsense.publisher_id' => $publisher,
            'adsense.client_id' => ($data['adsense_client_id'] ?? '') ?: $publisher,
            'adsense.auto_ads' => $request->boolean('adsense_auto_ads'),
            'adsense.manual_ads' => $request->boolean('adsense_manual_ads'),
            'adsense.lazy_load' => $request->boolean('adsense_lazy_load'),
            'adsense.label' => $label ?: 'Advertisement',
            'adsense.show_label' => $request->boolean('adsense_show_label'),
            'adsense.hide_for_admins' => $request->boolean('adsense_hide_for_admins'),
            'adsense.hide_for_bots' => $request->boolean('adsense_hide_for_bots'),
            'adsense.min_words_for_ads' => (int) ($data['adsense_min_words_for_ads'] ?? 150),
            'adsense.auto_sync' => $request->boolean('adsense_auto_sync'),
            'adsense.currency' => strtoupper($data['adsense_currency'] ?? 'USD'),
        ];
        $before = [];
        foreach ($pairs as $k => $v) {
            $before[$k] = setting($k);
        }
        $this->settings->setMany($pairs, 'adsense');
        Cache::forget('site.nav');
        $this->audit->log('adsense_changed', 'monetization', null, 'AdSense settings updated', $before, $pairs);
        return back()->with('success', 'Monetization settings saved.');
    }

    public function sync()
    {
        try {
            $counts = $this->adsense->sync(now()->subDays(60), now());
            $this->audit->log('adsense_sync', 'monetization', null, 'AdSense manual sync: '.json_encode($counts));
            return back()->with('success', 'AdSense data synced from the API.');
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => 'Sync failed: '.$e->getMessage()]);
        }
    }

    /** Pre-launch policy review checklist */
    public function checklist()
    {
        $slots = AdSlot::all();
        $pages = \App\Models\Page::whereIn('slug', ['privacy-policy', 'cookie-policy', 'terms-and-conditions', 'disclaimer', 'about-us', 'contact-us'])->pluck('status', 'slug');
        $checks = [
            ['label' => 'Privacy Policy page published and linked in the footer', 'ok' => ($pages['privacy-policy'] ?? null) === 'published'],
            ['label' => 'Cookie Policy, Terms, Disclaimer, About and Contact pages published', 'ok' => collect(['cookie-policy', 'terms-and-conditions', 'disclaimer', 'about-us', 'contact-us'])->every(fn ($s) => ($pages[$s] ?? null) === 'published')],
            ['label' => 'ads.txt is served and validates without errors', 'ok' => ! $this->adsTxt->validate($this->adsTxt->content(), setting('adsense.publisher_id'))['errors'] && setting('adsense.publisher_id')],
            ['label' => 'AdSense publisher id configured', 'ok' => (bool) setting('adsense.publisher_id')],
            ['label' => 'Cookie consent + Google Consent Mode enabled', 'ok' => setting_bool('consent.enabled') && setting_bool('consent.consent_mode_v2')],
            ['label' => 'No ad unit contains policy-violating code', 'ok' => $slots->every(fn ($s) => empty($s->policyWarnings()))],
            ['label' => 'Ads hidden for logged-in admins (protects against accidental self-clicks)', 'ok' => setting_bool('adsense.hide_for_admins', true)],
            ['label' => 'Ads not served to bots', 'ok' => setting_bool('adsense.hide_for_bots', true)],
            ['label' => 'Minimum content requirement for ads set (thin pages get no ads)', 'ok' => (int) setting('adsense.min_words_for_ads', 150) >= 100],
            ['label' => 'Ad label is neutral ("Advertisement")', 'ok' => ! preg_match('/click|support/i', (string) setting('adsense.label', 'Advertisement'))],
            ['label' => 'At least 10 published, original, non-thin posts', 'ok' => \App\Models\Post::published()->where('word_count', '>=', 300)->count() >= 10],
            ['label' => 'Sitemap enabled and robots.txt served', 'ok' => setting_bool('seo.sitemap_enabled', true)],
            ['label' => 'Sidebar ad disabled on mobile (readability)', 'ok' => ! ($slots->firstWhere('key', 'sidebar')?->mobile ?? false)],
        ];
        return view('admin.monetization.checklist', compact('checks'));
    }
}
