<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\GoogleOAuthService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings, protected AuditLogger $audit)
    {
    }

    /** Field definitions per tab: key => [label, type, help, options] */
    public static function tabs(): array
    {
        return [
            'general' => ['title' => 'General', 'fields' => [
                'site.name' => ['Site name', 'text'],
                'site.tagline' => ['Tagline', 'text'],
                'site.description' => ['Site description', 'textarea', 'Used as default meta description.'],
                'site.email' => ['Contact email', 'email'],
                'site.timezone' => ['Timezone', 'text', 'e.g. UTC, Asia/Karachi'],
                'site.posts_per_page' => ['Posts per page', 'number'],
                'site.footer_text' => ['Footer text', 'text'],
                'content.vlogs_enabled' => ['Show Vlogs on the site', 'bool', 'Off = Vlogs menu link, /vlogs listing, vlog pages, home sections, search and sitemap entries are all hidden. Content stays in the admin panel.'],
                'content.articles_enabled' => ['Show Articles on the site', 'bool', 'Same as above, for articles.'],
                'site.social_links' => ['Social links (JSON)', 'textarea', '{"youtube":"","facebook":"","instagram":"","twitter":"","tiktok":""}'],
            ]],
            'branding' => ['title' => 'Branding', 'fields' => [
                'site.logo' => ['Logo', 'image'],
                'site.favicon' => ['Favicon', 'image'],
                'site.organization_name' => ['Organization name (schema.org)', 'text'],
                'site.organization_logo' => ['Organization logo (schema.org, 112x112+)', 'image'],
                'brand.primary_color' => ['Primary colour', 'color'],
                'brand.accent_color' => ['Accent colour', 'color'],
            ]],
            'analytics' => ['title' => 'Analytics', 'fields' => [
                'analytics.internal_enabled' => ['Enable first-party analytics', 'bool'],
                'analytics.track_admins' => ['Track logged-in admins', 'bool', 'Off by default so your own visits are excluded.'],
                'analytics.city_tracking' => ['Record city (where headers/GeoIP provide it)', 'bool', 'Keep off unless your privacy policy covers it.'],
                'analytics.heartbeat_seconds' => ['Engagement heartbeat interval (seconds)', 'number'],
                'analytics.retention_days' => ['Raw analytics retention (days)', 'select', 'Aggregates are kept; raw sessions/page views are deleted after this period.', ['30' => '30 days', '90' => '90 days', '180' => '180 days', '365' => '1 year', '730' => '2 years']],
                'analytics.security_retention_days' => ['Security & admin log retention (days)', 'select', 'IPs are anonymised after this period.', ['30' => '30 days', '90' => '90 days', '180' => '180 days', '365' => '1 year']],
                'analytics.ga4_enabled' => ['Enable Google Analytics 4', 'bool'],
                'analytics.ga4_id' => ['GA4 Measurement ID', 'text', 'G-XXXXXXXXXX'],
            ]],
            'google' => ['title' => 'Google Integrations', 'fields' => [
                'google.client_id' => ['OAuth Client ID', 'text', 'From Google Cloud Console → Credentials (Web application).'],
                'google.client_secret' => ['OAuth Client Secret', 'password', 'Stored encrypted. Leave blank to keep the current value.'],
                'gsc.site_url' => ['Search Console property (optional override)', 'text', 'e.g. sc-domain:example.com or https://example.com/'],
                'gsc.auto_sync' => ['Auto-sync Search Console every 6 hours', 'bool'],
                'adsense.auto_sync' => ['Auto-sync AdSense every 6 hours', 'bool'],
                'seo.google_verification' => ['Google site verification code', 'text'],
                'seo.bing_verification' => ['Bing site verification code', 'text'],
            ]],
            'email' => ['title' => 'Email', 'fields' => [
                'mail.from_name' => ['From name', 'text'],
                'mail.from_address' => ['From address', 'email'],
                'mail.contact_recipient' => ['Contact form recipient', 'email'],
            ]],
            'consent' => ['title' => 'Cookie Consent', 'fields' => [
                'consent.enabled' => ['Enable cookie consent banner', 'bool'],
                'consent.mode' => ['When to require consent', 'select', 'Auto = only for visitors from the regions below (EEA/UK/CH). Unknown locations are treated as requiring consent.', ['auto' => 'Auto (regions below)', 'always' => 'Always', 'never' => 'Never']],
                'consent.regions' => ['Consent regions', 'text', 'Comma-separated: EEA, UK, CH, or ISO country codes.'],
                'consent.consent_mode_v2' => ['Google Consent Mode v2', 'bool', 'Sets default denied states and updates after choice.'],
                'consent.cmp' => ['Consent management platform', 'select', 'For EEA/UK/CH traffic Google requires a Google-certified CMP (IAB TCF). Paste its script below and select "External".', ['builtin' => 'Built-in preference centre', 'external' => 'External certified CMP (TCF)']],
                'consent.cmp_script' => ['External CMP script tag', 'textarea', 'Script tag from your certified CMP. Loaded in <head> before ads.'],
                'consent.banner_title' => ['Banner title', 'text'],
                'consent.banner_text' => ['Banner text', 'textarea'],
                'consent.retention_days' => ['Consent record retention (days)', 'number'],
            ]],
            'seo' => ['title' => 'SEO', 'fields' => [
                'seo.home_title' => ['Home page title', 'text'],
                'seo.home_description' => ['Home page meta description', 'textarea'],
                'seo.title_separator' => ['Title separator', 'text'],
                'seo.twitter_handle' => ['Twitter/X handle', 'text', '@yourhandle'],
                'seo.keywords' => ['Default meta keywords', 'text', 'Comma-separated. Posts use their focus keyword, tags and category instead.'],
                'seo.locale' => ['Open Graph locale', 'text', 'en_US for a US audience'],
                'seo.facebook_page' => ['Facebook page URL (article:publisher)', 'text', 'https://www.facebook.com/yourpage'],
                'seo.noindex_thin' => ['Noindex thin / placeholder pages', 'bool'],
                'seo.sitemap_enabled' => ['Enable sitemap.xml', 'bool'],
                'seo.robots_extra' => ['Extra robots.txt rules', 'textarea'],
            ]],
            'backup' => ['title' => 'Backup', 'fields' => [
                'backup.auto_database' => ['Automatic database backups', 'bool'],
                'backup.auto_media' => ['Automatic media backups', 'bool'],
                'backup.frequency' => ['Frequency', 'select', '', ['daily' => 'Daily', 'weekly' => 'Weekly']],
                'backup.keep' => ['Backups to keep (per type)', 'number'],
            ]],
            'performance' => ['title' => 'Performance', 'fields' => [
                'perf.cache_pages' => ['Cache home page sections', 'bool'],
                'perf.cache_ttl' => ['Cache TTL (seconds)', 'number'],
                'perf.lazy_images' => ['Lazy-load images', 'bool'],
                'perf.webp' => ['Generate WebP variants on upload', 'bool'],
                'perf.cdn_url' => ['CDN base URL for /storage assets (optional)', 'text', 'https://cdn.example.com'],
            ]],
            'security' => ['title' => 'Security', 'fields' => [
                'security.max_login_attempts' => ['Max failed logins before lockout', 'number'],
                'security.lockout_minutes' => ['Lockout duration (minutes)', 'number'],
                'security.rate_limit_per_minute' => ['Public requests per minute per client', 'number', 'Verified search engines are never limited.'],
                'security.bot_block_threshold' => ['Requests/minute that trigger a 30-minute block', 'number'],
                'notify.traffic_milestones' => ['Traffic milestone notifications (page views)', 'text', 'Comma-separated e.g. 1000,10000,100000'],
            ]],
        ];
    }

    public function edit(Request $request, GoogleOAuthService $oauth, string $tab = 'general')
    {
        $tabs = static::tabs();
        if (! isset($tabs[$tab])) {
            abort(404);
        }
        $values = [];
        foreach ($tabs[$tab]['fields'] as $key => $def) {
            $values[$key] = $def[1] === 'password' ? '' : setting($key);
        }
        $extra = [];
        if ($tab === 'google') {
            $extra = ['redirectUri' => $oauth->redirectUri(), 'configured' => $oauth->isConfigured(), 'hasSecret' => (bool) setting('google.client_secret'), 'tokens' => \App\Models\GoogleToken::all()->keyBy('service')];
        }
        return view('admin.settings.edit', ['tab' => $tab, 'tabs' => $tabs, 'fields' => $tabs[$tab]['fields'], 'values' => $values] + $extra);
    }

    public function update(Request $request, string $tab)
    {
        $tabs = static::tabs();
        if (! isset($tabs[$tab])) {
            abort(404);
        }
        $rules = [];
        foreach ($tabs[$tab]['fields'] as $key => $def) {
            $rules[str_replace('.', '__', $key)] = match ($def[1]) {
                'bool' => 'nullable|boolean',
                'number' => 'nullable|integer|min:0',
                'email' => 'nullable|email|max:190',
                'select' => ['nullable', Rule::in(array_keys($def[3] ?? []))],
                'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                default => 'nullable|string|max:20000',
            };
        }
        $data = $request->validate($rules);
        if ($tab === 'analytics' && ! empty($data['analytics__ga4_id']) && ! preg_match('/^G-[A-Z0-9]{4,20}$/i', $data['analytics__ga4_id'])) {
            return back()->withErrors(['analytics__ga4_id' => 'GA4 Measurement IDs look like G-XXXXXXXXXX.'])->withInput();
        }
        if ($tab === 'general' && ! empty($data['site__social_links']) && json_decode($data['site__social_links'], true) === null) {
            return back()->withErrors(['site__social_links' => 'Social links must be valid JSON.'])->withInput();
        }
        $before = [];
        $after = [];
        foreach ($tabs[$tab]['fields'] as $key => $def) {
            $field = str_replace('.', '__', $key);
            $value = $def[1] === 'bool' ? $request->boolean($field) : ($data[$field] ?? null);
            if ($def[1] === 'password') {
                if ($value === null || $value === '') {
                    continue; // keep existing secret
                }
                $before[$key] = '[REDACTED]';
                $after[$key] = '[REDACTED]';
            } else {
                $before[$key] = setting($key);
                $after[$key] = $value;
            }
            $this->settings->set($key, $value ?? '', $tab);
        }
        Cache::forget('site.nav');
        Cache::forget('home.sections');
        Cache::forget('sitemap.xml');
        $this->audit->log('settings_changed', 'settings', null, ucfirst($tab).' settings updated', $before, $after);
        return back()->with('success', 'Settings saved.');
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        $this->settings->flush();
        $this->audit->log('cache_cleared', 'settings', null, 'Application cache cleared');
        return back()->with('success', 'Cache cleared.');
    }
}
