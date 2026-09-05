<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    protected ?array $cache = null;

    /** Keys that must always be stored encrypted and never shown in audit logs. */
    public const SECRET_KEYS = [
        'google.client_secret', 'mail.password', 'smtp.password', 'adsense.api_key',
    ];

    public static function defaults(): array
    {
        return [
            // General
            'site.name' => config('app.name', 'PineCast TV'),
            'site.tagline' => 'Stories worth watching',
            'site.description' => 'A modern vlogging and content publishing platform.',
            'site.logo' => '',
            'site.favicon' => '',
            'site.email' => '',
            'site.timezone' => 'UTC',
            'site.posts_per_page' => 12,
            'site.footer_text' => '',
            'content.vlogs_enabled' => '1',
            'content.articles_enabled' => '1',
            'site.organization_name' => '',
            'site.organization_logo' => '',
            'site.social_links' => json_encode(['youtube' => '', 'facebook' => '', 'instagram' => '', 'twitter' => '', 'tiktok' => '']),
            // Branding
            'brand.primary_color' => '#e11d48',
            'brand.accent_color' => '#0f172a',
            'brand.font' => 'Inter',
            // Analytics
            'analytics.internal_enabled' => '1',
            'analytics.ga4_id' => '',
            'analytics.ga4_enabled' => '0',
            'analytics.track_admins' => '0',
            'analytics.retention_days' => '365',
            'analytics.security_retention_days' => '180',
            'analytics.city_tracking' => '0',
            'analytics.heartbeat_seconds' => '15',
            // Google integrations
            'google.client_id' => '',
            'google.client_secret' => '',
            'gsc.site_url' => '',
            'gsc.auto_sync' => '1',
            'adsense.auto_sync' => '1',
            'adsense.currency' => 'USD',
            // Monetization
            'adsense.enabled' => '0',
            'adsense.publisher_id' => '',
            'adsense.client_id' => '',
            'adsense.auto_ads' => '0',
            'adsense.manual_ads' => '1',
            'adsense.lazy_load' => '1',
            'adsense.label' => 'Advertisement',
            'adsense.show_label' => '1',
            'adsense.hide_for_admins' => '1',
            'adsense.hide_for_bots' => '1',
            'adsense.ads_txt' => '',
            'adsense.ads_txt_updated_at' => '',
            'adsense.min_words_for_ads' => '150',
            // Cookie consent
            'consent.enabled' => '1',
            'consent.mode' => 'auto',        // auto|always|never  (auto = required regions only)
            'consent.regions' => 'EEA,UK,CH',
            'consent.consent_mode_v2' => '1',
            'consent.cmp' => 'builtin',     // builtin|external
            'consent.cmp_script' => '',
            'consent.banner_title' => 'We value your privacy',
            'consent.banner_text' => 'We use cookies to analyse traffic and, with your consent, to show personalised advertising from Google and its partners. You can change your choice at any time.',
            'consent.retention_days' => '365',
            // SEO
            'seo.title_separator' => '|',
            'seo.home_title' => '',
            'seo.home_description' => '',
            'seo.robots_extra' => '',
            'seo.twitter_handle' => '',
            'seo.keywords' => '',
            'seo.locale' => 'en_US',
            'seo.facebook_page' => '',
            'seo.google_verification' => '',
            'seo.bing_verification' => '',
            'seo.noindex_thin' => '1',
            'seo.sitemap_enabled' => '1',
            // Performance
            'perf.cache_pages' => '1',
            'perf.cache_ttl' => '600',
            'perf.lazy_images' => '1',
            'perf.webp' => '1',
            'perf.cdn_url' => '',
            // Email
            'mail.from_name' => '',
            'mail.from_address' => '',
            'mail.contact_recipient' => '',
            // Backup
            'backup.auto_database' => '1',
            'backup.auto_media' => '0',
            'backup.frequency' => 'daily',
            'backup.keep' => '7',
            // Security
            'security.max_login_attempts' => '5',
            'security.lockout_minutes' => '15',
            'security.rate_limit_per_minute' => '120',
            'security.bot_block_threshold' => '300',
            // Notifications
            'notify.traffic_milestones' => '1000,10000,100000,1000000',
            'notify.email' => '',
        ];
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $this->cache = Cache::rememberForever('settings.all', function () {
            $rows = [];
            try {
                if (! Schema::hasTable('settings')) {
                    return [];
                }
                foreach (Setting::all() as $s) {
                    $rows[$s->key] = $s->is_encrypted ? $this->decrypt($s->value) : $s->value;
                }
            } catch (\Throwable) {
                return [];
            }
            return $rows;
        });
        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }
        return $default ?? (static::defaults()[$key] ?? null);
    }

    public function set(string $key, mixed $value, ?string $group = null): void
    {
        $encrypted = in_array($key, self::SECRET_KEYS, true);
        if (is_array($value)) {
            $value = json_encode($value);
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        $group = $group ?: (str_contains($key, '.') ? explode('.', $key, 2)[0] : 'general');
        Setting::updateOrCreate(['key' => $key], [
            'group' => $group,
            'value' => $encrypted && $value !== null && $value !== '' ? Crypt::encryptString((string) $value) : $value,
            'is_encrypted' => $encrypted,
        ]);
        $this->flush();
    }

    public function setMany(array $pairs, ?string $group = null): void
    {
        foreach ($pairs as $k => $v) {
            $this->set($k, $v, $group);
        }
    }

    public function flush(): void
    {
        $this->cache = null;
        Cache::forget('settings.all');
    }

    public function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    protected function decrypt(?string $v): ?string
    {
        try {
            return $v ? Crypt::decryptString($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
