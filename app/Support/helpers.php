<?php

use App\Services\SettingsService;

if (! function_exists('setting')) {
    /**
     * Get a site setting value (cached).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('setting_bool')) {
    function setting_bool(string $key, bool $default = false): bool
    {
        $v = setting($key, $default);
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}

if (! function_exists('compact_number')) {
    /** 12500 => 12.5K */
    function compact_number(int|float|null $n): string
    {
        $n = (float) ($n ?? 0);
        if ($n >= 1_000_000_000) {
            return round($n / 1_000_000_000, 1).'B';
        }
        if ($n >= 1_000_000) {
            return round($n / 1_000_000, 1).'M';
        }
        if ($n >= 1_000) {
            return round($n / 1_000, 1).'K';
        }
        return number_format($n);
    }
}

if (! function_exists('human_duration')) {
    /** seconds => 1h 12m / 3m 20s / 45s */
    function human_duration(int|float|null $seconds): string
    {
        $s = (int) round($seconds ?? 0);
        if ($s < 60) {
            return $s.'s';
        }
        if ($s < 3600) {
            return floor($s / 60).'m '.($s % 60).'s';
        }
        $h = floor($s / 3600);
        $m = floor(($s % 3600) / 60);
        return $h.'h '.$m.'m';
    }
}

if (! function_exists('percent_change')) {
    function percent_change(int|float|null $current, int|float|null $previous): ?float
    {
        $current = (float) ($current ?? 0);
        $previous = (float) ($previous ?? 0);
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : null;
        }
        return round(($current - $previous) / $previous * 100, 1);
    }
}

if (! function_exists('media_url')) {
    function media_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return $path;
        }
        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('active_class')) {
    function active_class(string|array $patterns, string $class = 'active'): string
    {
        return request()->routeIs($patterns) ? $class : '';
    }
}
