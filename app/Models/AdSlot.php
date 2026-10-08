<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSlot extends Model
{
    protected $fillable = [
        'key', 'name', 'position', 'description', 'code', 'ad_slot_id', 'ad_format', 'enabled', 'desktop', 'tablet', 'mobile',
        'is_safe_zone', 'safety_note', 'sort_order', 'paragraph_offset',
        'adsterra_enabled', 'adsterra_code', 'adsterra_code_mobile',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'desktop' => 'boolean', 'tablet' => 'boolean', 'mobile' => 'boolean', 'is_safe_zone' => 'boolean',
        'adsterra_enabled' => 'boolean',
    ];

    /**
     * Recommended, policy-safe placement zones. Placements outside of these are flagged in the admin UI.
     */
    public static function defaults(): array
    {
        return [
            ['key' => 'header', 'name' => 'Header Ad', 'position' => 'Header, after navigation', 'ad_format' => 'horizontal', 'sort_order' => 1,
                'description' => 'Displayed below the main navigation, above the page content. Kept at a safe distance from nav links.'],
            ['key' => 'in_article', 'name' => 'In-Article Ad', 'position' => 'Between article paragraphs', 'ad_format' => 'fluid', 'sort_order' => 2, 'paragraph_offset' => 3,
                'description' => 'Inserted after the Nth paragraph of the article body. Never placed directly next to the video player.'],
            ['key' => 'between_content', 'name' => 'Between Content Ad', 'position' => 'Between content sections / listing rows', 'ad_format' => 'auto', 'sort_order' => 3,
                'description' => 'Shown between content sections on the home page and between listing rows.'],
            ['key' => 'sidebar', 'name' => 'Sidebar Ad', 'position' => 'Sidebar (desktop / tablet)', 'ad_format' => 'rectangle', 'sort_order' => 4, 'mobile' => false,
                'description' => 'Sticky sidebar rectangle on wide screens. Hidden on mobile by default to protect readability.'],
            ['key' => 'below_content', 'name' => 'Below Content Ad', 'position' => 'After article / vlog content', 'ad_format' => 'auto', 'sort_order' => 5,
                'description' => 'Displayed after the main content ends, before related content.'],
            ['key' => 'related', 'name' => 'Related Content Ad', 'position' => 'Related-content area', 'ad_format' => 'auto', 'sort_order' => 6,
                'description' => 'Placed inside the related-content grid, visually separated with an "Advertisement" label.'],
            ['key' => 'footer', 'name' => 'Footer Ad', 'position' => 'Footer area', 'ad_format' => 'horizontal', 'sort_order' => 7,
                'description' => 'Displayed above the footer, away from footer links.'],
        ];
    }

    public function isVisibleFor(string $device): bool
    {
        return $this->enabled && (bool) ($this->{$device} ?? false);
    }

    /** Device toggle only (shared by AdSense and Adsterra). */
    public function deviceAllowed(string $device): bool
    {
        return (bool) ($this->{$device} ?? false);
    }

    /** Max banner width that still fits a phone screen. */
    public const MOBILE_MAX_WIDTH = 336;

    /** Adsterra code to render for a device, or null when nothing suitable is configured. */
    public function adsterraCodeFor(string $device): ?string
    {
        $mobileCode = trim((string) $this->adsterra_code_mobile);
        $code = trim((string) $this->adsterra_code);
        if ($device === 'mobile' && $mobileCode !== '') {
            $code = $mobileCode;
        }
        if ($code === '' || static::adsterraCodeProblems($code)) {
            return null;
        }
        $size = $this->adsterraSize($code);
        if ($device === 'mobile' && $size && $size[0] > self::MOBILE_MAX_WIDTH) {
            return null; // a 728px banner would break the mobile layout
        }
        return $code;
    }

    /** [width, height] from an Adsterra banner "atOptions" block, null for native banners. */
    public function adsterraSize(?string $code): ?array
    {
        $code = (string) $code;
        if (preg_match("/['\"]?width['\"]?\s*:\s*(\d+)/i", $code, $w) && preg_match("/['\"]?height['\"]?\s*:\s*(\d+)/i", $code, $h)) {
            return [(int) $w[1], (int) $h[1]];
        }
        return null;
    }

    /**
     * Only Adsterra Banner ("atOptions" + invoke.js) and Native Banner (invoke.js + container div) codes pass.
     * Popunder, Social Bar and Smartlink codes are rejected because AdSense forbids pop-unders on the site.
     */
    public static function adsterraCodeProblems(?string $code): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [];
        }
        $problems = [];
        if (mb_strlen($code) > 5000) {
            $problems[] = 'Code is too long (max 5000 characters).';
        }
        $hasInvoke = (bool) preg_match('~/invoke\.js~i', $code);
        $isBanner = (bool) preg_match('/atOptions\s*=/', $code);
        $isNative = (bool) preg_match('/id\s*=\s*["\']container-[A-Za-z0-9]+["\']/', $code);
        if (! $hasInvoke || (! $isBanner && ! $isNative)) {
            $problems[] = 'This is not an Adsterra Banner or Native Banner code. Banner codes contain "atOptions" and ".../invoke.js"; Native Banner codes contain ".../invoke.js" and a <div id="container-…">. Popunder, Social Bar and Smartlink codes are not allowed.';
        }
        if (preg_match('/window\.open|location\.(href|replace|assign)|\.click\(\)|onclick\s*=|setInterval|addEventListener\(\s*[\'"](click|mousedown|touchstart)/i', $code)) {
            $problems[] = 'Code contains redirect, auto-click or popup logic, which is not allowed.';
        }
        if (preg_match('/<a\s[^>]*href=/i', $code) && ! $hasInvoke) {
            $problems[] = 'Smartlink / direct links are not allowed as ad code.';
        }
        return $problems;
    }

    /** Simple static policy checks for admin warnings. */
    public function policyWarnings(): array
    {
        $w = [];
        $code = (string) $this->code;
        if ($code !== '') {
            if (preg_match('/<script(?![^>]*pagead2\.googlesyndication\.com)[^>]*src=/i', $code)) {
                $w[] = 'Ad code contains a third-party script that is not the Google AdSense loader. Verify it is permitted.';
            }
            if (preg_match('/onclick|\.click\(\)|setInterval|location\.reload|window\.open/i', $code)) {
                $w[] = 'Ad code contains click/refresh/popup JavaScript. Auto-clicks, auto-refresh and pop-unders violate AdSense policies.';
            }
            if (preg_match('/click (here|the ad)|support us by clicking|click ads/i', $code)) {
                $w[] = 'Ad code contains wording that encourages clicks. This is not allowed.';
            }
            if (preg_match('/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0/i', $code)) {
                $w[] = 'Ad code hides the ad unit. Hidden ads are a policy violation.';
            }
        }
        if (! $this->is_safe_zone) {
            $w[] = 'This placement is outside the recommended safe zones. Review it against the AdSense ad placement policies.';
        }
        return $w;
    }
}
