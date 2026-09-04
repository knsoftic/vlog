<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Country / city resolution with graceful degradation:
 *  1. CDN-provided headers (Cloudflare CF-IPCountry, Vercel, Fastly, etc.)
 *  2. Optional MaxMind GeoLite2 database (GEOIP_DATABASE in .env)
 *  3. Unknown
 *
 * City resolution is only performed when explicitly enabled in settings (privacy).
 */
class GeoService
{
    protected static ?\GeoIp2\Database\Reader $reader = null;
    protected static bool $readerChecked = false;

    /** @return array{country: ?string, city: ?string} */
    public function resolve(Request $request): array
    {
        $country = null;
        $city = null;

        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'X-Country-Code', 'Fastly-Client-Country', 'X-AppEngine-Country'] as $h) {
            $v = strtoupper((string) $request->header($h));
            if (preg_match('/^[A-Z]{2}$/', $v) && $v !== 'XX' && $v !== 'T1') {
                $country = $v;
                break;
            }
        }
        if (setting_bool('analytics.city_tracking')) {
            foreach (['CF-IPCity', 'X-Vercel-IP-City'] as $h) {
                $v = (string) $request->header($h);
                if ($v !== '') {
                    $city = mb_substr(urldecode($v), 0, 100);
                    break;
                }
            }
        }

        if (! $country) {
            $reader = $this->reader();
            if ($reader) {
                try {
                    $rec = $reader->city($request->ip());
                    $country = $rec->country->isoCode ?: null;
                    if (setting_bool('analytics.city_tracking')) {
                        $city = $rec->city->name ? mb_substr($rec->city->name, 0, 100) : null;
                    }
                } catch (\Throwable) {
                    try {
                        $country = $reader->country($request->ip())->country->isoCode ?: null;
                    } catch (\Throwable) {
                    }
                }
            }
        }

        return ['country' => $country, 'city' => $city];
    }

    /** EEA + UK + CH detection for consent requirements. */
    public function requiresConsent(?string $country): bool
    {
        $mode = setting('consent.mode', 'auto');
        if ($mode === 'always') {
            return true;
        }
        if ($mode === 'never') {
            return false;
        }
        if (! $country) {
            return true; // unknown location => be safe and ask
        }
        return in_array($country, self::consentCountries(), true);
    }

    public static function consentCountries(): array
    {
        $eea = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];
        $regions = array_map('trim', explode(',', (string) setting('consent.regions', 'EEA,UK,CH')));
        $out = [];
        foreach ($regions as $r) {
            $r = strtoupper($r);
            if ($r === 'EEA' || $r === 'EU') {
                $out = array_merge($out, $eea);
            } elseif ($r === 'UK') {
                $out[] = 'GB';
            } elseif (preg_match('/^[A-Z]{2}$/', $r)) {
                $out[] = $r;
            }
        }
        return array_values(array_unique($out));
    }

    protected function reader(): ?\GeoIp2\Database\Reader
    {
        if (self::$readerChecked) {
            return self::$reader;
        }
        self::$readerChecked = true;
        $path = config('services.geoip.database') ?: env('GEOIP_DATABASE');
        if ($path && is_file($path) && class_exists(\GeoIp2\Database\Reader::class)) {
            try {
                self::$reader = new \GeoIp2\Database\Reader($path);
            } catch (\Throwable) {
                self::$reader = null;
            }
        }
        return self::$reader;
    }

    public static function countryName(?string $code): string
    {
        if (! $code) {
            return 'Unknown';
        }
        static $names = null;
        if ($names === null) {
            $names = [
                'US' => 'United States', 'GB' => 'United Kingdom', 'PK' => 'Pakistan', 'IN' => 'India', 'CA' => 'Canada', 'AU' => 'Australia',
                'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'BR' => 'Brazil', 'MX' => 'Mexico',
                'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China', 'RU' => 'Russia', 'TR' => 'Turkey', 'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates',
                'EG' => 'Egypt', 'NG' => 'Nigeria', 'ZA' => 'South Africa', 'ID' => 'Indonesia', 'PH' => 'Philippines', 'MY' => 'Malaysia', 'SG' => 'Singapore',
                'BD' => 'Bangladesh', 'LK' => 'Sri Lanka', 'NP' => 'Nepal', 'IR' => 'Iran', 'IQ' => 'Iraq', 'PL' => 'Poland', 'SE' => 'Sweden', 'NO' => 'Norway',
                'DK' => 'Denmark', 'FI' => 'Finland', 'IE' => 'Ireland', 'PT' => 'Portugal', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria',
                'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia', 'PE' => 'Peru', 'NZ' => 'New Zealand', 'TH' => 'Thailand', 'VN' => 'Vietnam',
                'UA' => 'Ukraine', 'CZ' => 'Czechia', 'RO' => 'Romania', 'HU' => 'Hungary', 'GR' => 'Greece', 'IL' => 'Israel', 'QA' => 'Qatar', 'KW' => 'Kuwait',
                'MA' => 'Morocco', 'DZ' => 'Algeria', 'KE' => 'Kenya', 'GH' => 'Ghana', 'ET' => 'Ethiopia', 'HK' => 'Hong Kong', 'TW' => 'Taiwan',
            ];
        }
        return $names[strtoupper($code)] ?? strtoupper($code);
    }
}
